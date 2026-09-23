<?php

namespace App\Services;

use App\Models\Avatar;
use App\Models\ConversationVersion;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class ConversationRouterService
{
    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function route(Avatar $avatar, ConversationVersion $version, string $transcript, array $state): array
    {
        if (! config('avatar.router_enabled')) {
            return $this->localRoute($version->tree, $transcript, $state);
        }

        try {
            $response = Http::acceptJson()
                ->connectTimeout(3)
                ->timeout(config('avatar.router_timeout_seconds'))
                ->withHeaders(['X-Router-Token' => config('avatar.router_token')])
                ->post(config('avatar.router_url').'/v1/route', [
                    'avatar' => $avatar->slug,
                    'transcript' => $transcript,
                    'state' => $state,
                    'topics' => array_map(fn (array $topic): array => [
                        'id' => $topic['id'],
                        'title' => $topic['title'],
                        'keywords' => $topic['keywords'],
                    ], $version->tree['topics']),
                ]);
        } catch (Throwable) {
            return ['status' => 'failed'];
        }

        if ($response->status() === 202 && is_string($response->json('ticket'))) {
            return ['status' => 'queued', 'ticket' => $response->json('ticket')];
        }

        return $this->validatedSelection($version->tree, $response->json(), $state);
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function poll(ConversationVersion $version, string $ticket, array $state): array
    {
        if (! config('avatar.router_enabled')) {
            return ['status' => 'failed'];
        }

        try {
            $response = Http::acceptJson()
                ->connectTimeout(3)
                ->timeout(5)
                ->withHeaders(['X-Router-Token' => config('avatar.router_token')])
                ->get(config('avatar.router_url').'/v1/route/'.rawurlencode($ticket));
        } catch (Throwable) {
            return ['status' => 'failed'];
        }

        if ($response->status() === 202) {
            return ['status' => 'queued'];
        }

        return $this->validatedSelection($version->tree, $response->json(), $state);
    }

    /**
     * @param  array<string, mixed>  $tree
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function localRoute(array $tree, string $transcript, array $state): array
    {
        $normalized = Str::ascii(Str::lower($transcript));
        $isContinuation = Str::contains($normalized, ['mas', 'amplia', 'detalle', 'continua', 'sigue']);
        if ($isContinuation && is_string($state['topic_id'] ?? null)) {
            return [
                'status' => 'ready',
                'topic_id' => $state['topic_id'],
                'stage' => match ($state['stage'] ?? 'summary') {
                    'summary' => 'detail',
                    default => 'next',
                },
            ];
        }

        $bestTopic = null;
        $bestScore = 0;
        foreach ($tree['topics'] as $topic) {
            $score = collect($topic['keywords'])
                ->filter(fn (string $keyword): bool => Str::contains($normalized, Str::ascii(Str::lower($keyword))))
                ->count();
            if ($score > $bestScore) {
                $bestTopic = $topic;
                $bestScore = $score;
            }
        }

        if (! is_array($bestTopic)) {
            return ['status' => 'fallback'];
        }

        return ['status' => 'ready', 'topic_id' => $bestTopic['id'], 'stage' => 'summary'];
    }

    /**
     * @param  array<string, mixed>  $tree
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function validatedSelection(array $tree, mixed $payload, array $state): array
    {
        if (! is_array($payload) || ! is_string($payload['topic_id'] ?? null)) {
            return ['status' => 'fallback'];
        }

        $topic = collect($tree['topics'])->firstWhere('id', $payload['topic_id']);
        if (! is_array($topic)) {
            return ['status' => 'fallback'];
        }

        $stage = $payload['stage'] ?? 'summary';
        if (! in_array($stage, ['summary', 'detail', 'next'], true)) {
            return ['status' => 'fallback'];
        }
        if (($payload['action'] ?? null) === 'continue' && ($state['topic_id'] ?? null) === $topic['id']) {
            $stage = match ($state['stage'] ?? 'summary') {
                'summary' => 'detail',
                default => 'next',
            };
        }

        return ['status' => 'ready', 'topic_id' => $topic['id'], 'stage' => $stage];
    }
}
