<?php

namespace App\Services;

use App\Models\Avatar;
use App\Models\ConversationVersion;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class ConversationRouterService
{
    public function __construct(private readonly ConversationTree $conversationTree) {}

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function route(Avatar $avatar, ConversationVersion $version, string $transcript, array $state): array
    {
        $local = $this->localRoute($version->tree, $transcript, $state);
        if ($this->isDirectSelection($version->tree, $transcript, $local)) {
            return $local;
        }

        if (! config('avatar.router_enabled')) {
            return $local;
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
                    'topics' => $this->topicsForRouter($version->tree['topics']),
                    'social' => $this->socialForRouter($version->tree),
                ]);
        } catch (Throwable) {
            return $local;
        }

        if ($response->status() === 202 && is_string($response->json('ticket'))) {
            return ['status' => 'queued', 'ticket' => $response->json('ticket')];
        }

        $selection = $this->validatedSelection($version->tree, $response->json());

        return $selection['status'] === 'ready' ? $selection : $local;
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
        $normalized = trim(preg_replace('/[^a-z0-9]+/', ' ', Str::ascii(Str::lower($transcript))) ?? '');
        $matches = $this->matchedTopics($tree['topics'], $normalized);
        $isContinuation = Str::contains($normalized, ['mas', 'amplia', 'detalle', 'continua', 'sigue']);
        $socialIntent = $this->matchedSocialIntent($tree, $normalized);

        if ($socialIntent === 'farewell') {
            return $this->socialSelection($socialIntent);
        }

        if ($socialIntent === 'clarification' && is_string($state['last_topic_id'] ?? null)) {
            return [
                'status' => 'ready',
                'items' => [
                    ['kind' => 'social', 'intent' => 'clarification'],
                    [
                        'kind' => 'topic',
                        'topic_id' => $state['last_topic_id'],
                        'stage' => $state['topics'][$state['last_topic_id']]['stage'] ?? 'summary',
                        'action' => 'rephrase',
                    ],
                ],
            ];
        }

        if ($matches !== []) {
            $items = [];
            if ($socialIntent === 'greeting') {
                $items[] = ['kind' => 'social', 'intent' => 'greeting'];
            }
            foreach ($matches as $topic) {
                $items[] = [
                    'kind' => 'topic',
                    'topic_id' => $topic['id'],
                    'stage' => 'summary',
                    'action' => $isContinuation ? 'continue' : 'select',
                ];
            }

            return [
                'status' => 'ready',
                'items' => $items,
            ];
        }

        if ($isContinuation && is_string($state['last_topic_id'] ?? null)) {
            return [
                'status' => 'ready',
                'items' => [[
                    'kind' => 'topic',
                    'topic_id' => $state['last_topic_id'],
                    'stage' => 'detail',
                    'action' => 'continue',
                ]],
            ];
        }

        if ($socialIntent !== null) {
            return $this->socialSelection($socialIntent);
        }

        return ['status' => 'fallback'];
    }

    /** @return array{status: string, items: list<array{kind: string, intent: string}>} */
    private function socialSelection(string $intent): array
    {
        return ['status' => 'ready', 'items' => [['kind' => 'social', 'intent' => $intent]]];
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
     * @param  array<string, mixed>  $local
     */
    private function isDirectSelection(array $tree, string $transcript, array $local): bool
    {
        if ($local['status'] !== 'ready' || ! is_array($local['items'] ?? null)) {
            return false;
        }

        if (collect($local['items'])->contains(fn (mixed $item): bool => is_array($item) && ($item['kind'] ?? 'topic') === 'social')) {
            return true;
        }

        $remaining = trim(preg_replace('/[^a-z0-9]+/', ' ', Str::ascii(Str::lower($transcript))) ?? '');
        foreach ($this->matchedTopics($tree['topics'], $remaining) as $topic) {
            foreach ($topic['keywords'] as $keyword) {
                $remaining = str_replace(Str::ascii(Str::lower($keyword)), ' ', $remaining);
            }
        }
        $remaining = preg_replace('/\\b(y|e|o|sobre|tema|de|del|la|el|los|las)\\b/', ' ', $remaining) ?? $remaining;

        return trim($remaining) === '';
    }

    /**
     * @param  list<array<string, mixed>>  $topics
     * @return list<array{id: string, title: string, description: string, examples: list<string>, keywords: list<string>}>
     */
    private function topicsForRouter(array $topics): array
    {
        return array_map(function (array $topic): array {
            $keywords = array_values(array_filter($topic['keywords'], 'is_string'));
            $examples = array_values(array_filter($topic['examples'] ?? $keywords, 'is_string'));

            return [
                'id' => $topic['id'],
                'title' => $topic['title'],
                'description' => is_string($topic['description'] ?? null) ? $topic['description'] : $topic['title'],
                'examples' => $examples,
                'keywords' => $keywords,
            ];
        }, $topics);
    }

    /**
     * @param  array<string, mixed>  $tree
     * @return list<array{id: string, description: string, examples: list<string>, keywords: list<string>}>
     */
    private function socialForRouter(array $tree): array
    {
        return collect($this->conversationTree->socialFamilies($tree))
            ->map(fn (array $intent, string $id): array => [
                'id' => $id,
                'description' => $intent['description'],
                'examples' => $intent['examples'],
                'keywords' => $intent['keywords'],
            ])
            ->values()
            ->all();
    }

    /** @param array<string, mixed> $tree */
    private function matchedSocialIntent(array $tree, string $normalized): ?string
    {
        $social = $this->conversationTree->socialFamilies($tree);
        $priority = ['farewell', 'clarification', 'capabilities', 'greeting', 'gratitude', 'acknowledgement', 'change-topic', 'small-talk'];
        foreach ($priority as $intent) {
            if (! isset($social[$intent])) {
                continue;
            }

            foreach ($social[$intent]['keywords'] as $keyword) {
                if (Str::contains($normalized, Str::ascii(Str::lower($keyword)))) {
                    return $intent;
                }
            }
        }

        return null;
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

        $allowedTopics = collect($tree['topics'])->keyBy('id');
        $allowedSocial = $this->conversationTree->socialFamilies($tree);
        $items = [];
        $seenTopicIds = [];
        $socialCount = 0;
        foreach (array_slice($payload['items'], 0, 4) as $item) {
            if (! is_array($item)) {
                return ['status' => 'fallback'];
            }

            if (($item['kind'] ?? 'topic') === 'social') {
                if (! is_string($item['intent'] ?? null) || ! array_key_exists($item['intent'], $allowedSocial) || ++$socialCount > 1) {
                    return ['status' => 'fallback'];
                }
                $items[] = ['kind' => 'social', 'intent' => $item['intent']];

                continue;
            }

            if (($item['kind'] ?? 'topic') !== 'topic' || ! is_string($item['topic_id'] ?? null) || ! $allowedTopics->has($item['topic_id'])) {
                return ['status' => 'fallback'];
            }
            if (isset($seenTopicIds[$item['topic_id']])) {
                return ['status' => 'fallback'];
            }

            $stage = $item['stage'] ?? 'summary';
            $action = $item['action'] ?? 'select';
            if (! in_array($stage, ['summary', 'detail', 'next'], true) || ! in_array($action, ['select', 'continue', 'rephrase'], true)) {
                return ['status' => 'fallback'];
            }

            $items[] = ['kind' => 'topic', 'topic_id' => $item['topic_id'], 'stage' => $stage, 'action' => $action];
            $seenTopicIds[$item['topic_id']] = true;
        }

        return ['status' => 'ready', 'items' => $items];
    }
}
