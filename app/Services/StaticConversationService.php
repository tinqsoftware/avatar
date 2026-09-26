<?php

namespace App\Services;

use App\Models\Avatar;
use App\Models\ConversationVersion;

class StaticConversationService
{
    public function __construct(
        private readonly ConversationRouterService $router,
        private readonly ConversationTree $conversationTree,
    ) {}

    /** @return array<string, mixed> */
    public function greeting(Avatar $avatar): array
    {
        $version = $this->version($avatar);
        $state = $this->state($avatar);
        $reply = array_key_exists('greeting', $this->conversationTree->socialFamilies($version->tree))
            ? $this->line($avatar, $version, 'social', 'greeting', null, $state)
            : $this->line($avatar, $version, 'greeting', null, null, $state);
        $state['closed'] = false;
        $this->storeState($avatar, $state);

        return $reply;
    }

    /** @return array<string, mixed> */
    public function respond(Avatar $avatar, string $transcript): array
    {
        $version = $this->version($avatar);
        $state = $this->state($avatar);
        $selection = $this->router->route($avatar, $version, $transcript, $state);

        if ($selection['status'] === 'queued') {
            $connector = $this->line($avatar, $version, 'connector', 'queue', null, $state);
            $this->storeState($avatar, $state);

            return ['status' => 'queued', 'ticket' => $selection['ticket'], 'connector' => $connector];
        }

        return $this->selection($avatar, $version, $selection, $state);
    }

    /** @return array<string, mixed> */
    public function poll(Avatar $avatar, string $ticket): array
    {
        $version = $this->version($avatar);
        $state = $this->state($avatar);
        $selection = $this->router->poll($version, $ticket, $state);
        if ($selection['status'] === 'queued') {
            return ['status' => 'queued'];
        }

        return $this->selection($avatar, $version, $selection, $state);
    }

    private function version(Avatar $avatar): ConversationVersion
    {
        $version = $avatar->publishedConversation();
        abort_unless($version, 503, 'Este avatar todavía está preparando sus respuestas.');

        return $version;
    }

    /**
     * @param  array<string, mixed>  $selection
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function selection(Avatar $avatar, ConversationVersion $version, array $selection, array $state): array
    {
        if ($selection['status'] !== 'ready' || ! is_array($selection['items'] ?? null) || $selection['items'] === []) {
            $reply = $this->line($avatar, $version, 'fallback', null, null, $state);
            $this->storeState($avatar, $state);

            return $reply;
        }

        $topics = collect($version->tree['topics'])->keyBy('id');
        $social = $this->conversationTree->socialFamilies($version->tree);
        $items = collect($selection['items'])
            ->take(4)
            ->filter(fn (mixed $item): bool => $this->isValidItem($item, $topics->all(), $social))
            ->values();

        if ($items->isEmpty()) {
            $reply = $this->line($avatar, $version, 'fallback', null, null, $state);
            $this->storeState($avatar, $state);

            return $reply;
        }

        $socialItems = $items->filter(fn (array $item): bool => ($item['kind'] ?? 'topic') === 'social');
        $topicItems = $items->filter(fn (array $item): bool => ($item['kind'] ?? 'topic') === 'topic')->values();
        if ($socialItems->contains(fn (array $item): bool => $item['intent'] === 'farewell')) {
            $topicItems = collect();
            $state['closed'] = true;
        }

        $playlist = [];
        foreach ($socialItems as $item) {
            $playlist[] = $this->line($avatar, $version, 'social', $item['intent'], null, $state);
        }

        if ($topicItems->count() > 1) {
            $playlist[] = $this->line($avatar, $version, 'connector', 'multi_intro', null, $state);
        }

        foreach ($topicItems as $index => $item) {
            $topic = $topics->get($item['topic_id']);
            $stage = $this->stageFor($item, $state);
            if (($item['action'] ?? 'select') === 'continue' && $topicItems->count() === 1) {
                $playlist[] = $this->line($avatar, $version, 'connector', 'continue_last', null, $state);
            }

            $playlist[] = $this->line($avatar, $version, 'topic', $topic['id'], $stage, $state);
            $state['topics'][$topic['id']] = ['stage' => $stage];

            if ($index < $topicItems->count() - 1) {
                $playlist[] = $this->line($avatar, $version, 'connector', 'multi_bridge', null, $state);
            }
        }

        if ($topicItems->count() > 1) {
            $playlist[] = $this->line($avatar, $version, 'connector', 'multi_outro', null, $state);
        }

        if ($topicItems->isNotEmpty()) {
            $state['last_topic_id'] = $topicItems->last()['topic_id'];
            $state['last_topic_ids'] = $topicItems->pluck('topic_id')->all();
            $state['closed'] = false;
        }
        $this->storeState($avatar, $state);

        return [
            'status' => 'ready',
            'text' => collect($playlist)->pluck('text')->implode(' '),
            'playlist' => $playlist,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $state
     */
    private function stageFor(array $item, array $state): string
    {
        $stage = $item['stage'] ?? 'summary';
        if (! in_array($stage, ['summary', 'detail', 'next'], true)) {
            $stage = 'summary';
        }

        if (($item['action'] ?? 'select') === 'rephrase') {
            return $state['topics'][$item['topic_id']]['stage'] ?? 'summary';
        }

        if (($item['action'] ?? 'select') !== 'continue') {
            return $stage;
        }

        return match ($state['topics'][$item['topic_id']]['stage'] ?? 'summary') {
            'summary' => 'detail',
            default => 'next',
        };
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function line(Avatar $avatar, ConversationVersion $version, string $kind, ?string $topicId, ?string $stage, array &$state): array
    {
        if ($kind === 'topic') {
            $variants = collect($version->tree['topics'])->firstWhere('id', $topicId)[$stage]['variants'];
            $prefix = "topic.{$topicId}.{$stage}";
        } elseif ($kind === 'connector') {
            $variants = $this->conversationTree->connectorFamilies($version->tree)[$topicId];
            $prefix = "connector.{$topicId}";
        } elseif ($kind === 'social') {
            $variants = $this->conversationTree->socialFamilies($version->tree)[$topicId]['variants'];
            $prefix = "social.{$topicId}";
        } else {
            $variants = $version->tree[$kind]['variants'];
            $prefix = $kind;
        }

        $index = $this->nextVariant($prefix, count($variants), $state);
        $key = "{$prefix}.{$index}";
        $asset = $version->audioAssets()->where('asset_key', $key)->first();

        return [
            'text' => $variants[$index],
            'audio_url' => $asset?->status === 'ready' && $asset->path ? '/storage/'.ltrim($asset->path, '/') : null,
            'mime_type' => $asset?->mime_type,
            'duration_ms' => $asset?->duration_ms,
            'visemes' => $asset?->visemes ?? [],
        ];
    }

    /** @param array<string, mixed> $state */
    private function nextVariant(string $prefix, int $count, array &$state): int
    {
        $last = $state['variants'][$prefix] ?? null;
        $index = is_int($last) ? ($last + 1) % $count : random_int(0, $count - 1);
        $state['variants'][$prefix] = $index;

        return $index;
    }

    /** @return array<string, mixed> */
    private function state(Avatar $avatar): array
    {
        $state = session()->get($this->stateKey($avatar), []);

        return is_array($state) ? $state : [];
    }

    /** @param array<string, mixed> $state */
    private function storeState(Avatar $avatar, array $state): void
    {
        session()->put($this->stateKey($avatar), $state);
    }

    private function stateKey(Avatar $avatar): string
    {
        return "avatar-call.{$avatar->id}";
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $topics
     * @param  array<string, array{description: string, examples: list<string>, keywords: list<string>, variants: list<string>}>  $social
     */
    private function isValidItem(mixed $item, array $topics, array $social): bool
    {
        if (! is_array($item)) {
            return false;
        }

        if (($item['kind'] ?? 'topic') === 'social') {
            return is_string($item['intent'] ?? null) && array_key_exists($item['intent'], $social);
        }

        return is_string($item['topic_id'] ?? null)
            && array_key_exists($item['topic_id'], $topics)
            && in_array($item['stage'] ?? 'summary', ['summary', 'detail', 'next'], true)
            && in_array($item['action'] ?? 'select', ['select', 'continue', 'rephrase'], true);
    }
}
