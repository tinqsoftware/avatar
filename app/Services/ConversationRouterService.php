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

        return $this->validatedSelection($version->tree, $response->json());
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

        return $this->validatedSelection($version->tree, $response->json());
    }

    /**
     * @param  array<string, mixed>  $tree
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function localRoute(array $tree, string $transcript, array $state): array
    {
        $normalized = Str::ascii(Str::lower($transcript));
        $matches = $this->matchedTopics($tree['topics'], $normalized);
        $isContinuation = Str::contains($normalized, ['mas', 'amplia', 'detalle', 'continua', 'sigue']);

        if ($matches !== []) {
            return [
                'status' => 'ready',
                'items' => array_map(fn (array $topic): array => [
                    'topic_id' => $topic['id'],
                    'stage' => 'summary',
                    'action' => $isContinuation ? 'continue' : 'select',
                ], $matches),
            ];
        }

        if ($isContinuation && is_string($state['last_topic_id'] ?? null)) {
            return [
                'status' => 'ready',
                'items' => [[
                    'topic_id' => $state['last_topic_id'],
                    'stage' => 'detail',
                    'action' => 'continue',
                ]],
            ];
        }

        return ['status' => 'fallback'];
    }

    /**
     * @param  list<array<string, mixed>>  $topics
     * @return list<array<string, mixed>>
     */
    private function matchedTopics(array $topics, string $normalized): array
    {
        $matches = [];
        foreach ($topics as $topic) {
            $positions = collect($topic['keywords'])
                ->map(fn (string $keyword): int|false => mb_strpos($normalized, Str::ascii(Str::lower($keyword))))
                ->filter(fn (int|false $position): bool => $position !== false);

            if ($positions->isNotEmpty()) {
                $matches[] = ['topic' => $topic, 'position' => $positions->min()];
            }
        }

        usort($matches, fn (array $left, array $right): int => $left['position'] <=> $right['position']);

        return array_map(fn (array $match): array => $match['topic'], array_slice($matches, 0, 3));
    }

    /**
     * @param  array<string, mixed>  $tree
     * @return array<string, mixed>
     */
    private function validatedSelection(array $tree, mixed $payload): array
    {
        if (! is_array($payload) || ! is_array($payload['items'] ?? null) || $payload['items'] === []) {
            return ['status' => 'fallback'];
        }

        $allowed = collect($tree['topics'])->keyBy('id');
        $items = [];
        $seenTopicIds = [];
        foreach (array_slice($payload['items'], 0, 3) as $item) {
            if (! is_array($item) || ! is_string($item['topic_id'] ?? null) || ! $allowed->has($item['topic_id'])) {
                return ['status' => 'fallback'];
            }
            if (isset($seenTopicIds[$item['topic_id']])) {
                return ['status' => 'fallback'];
            }

            $stage = $item['stage'] ?? 'summary';
            $action = $item['action'] ?? 'select';
            if (! in_array($stage, ['summary', 'detail', 'next'], true) || ! in_array($action, ['select', 'continue'], true)) {
                return ['status' => 'fallback'];
            }

            $items[] = ['topic_id' => $item['topic_id'], 'stage' => $stage, 'action' => $action];
            $seenTopicIds[$item['topic_id']] = true;
        }

        return ['status' => 'ready', 'items' => $items];
    }
}
