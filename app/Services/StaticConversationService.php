<?php

namespace App\Services;

use App\Models\Avatar;
use App\Models\ConversationVersion;

class StaticConversationService
{
    public function __construct(
        private readonly ConversationRouterService $router,
        private readonly ConversationTree $conversationTree,
        private readonly ConversationCoverage $coverage,
    ) {}

    /** @return array<string, mixed> */
    public function greeting(Avatar $avatar, bool $preview = false): array
    {
        $version = $this->version($avatar, $preview);
        $state = $this->state($avatar, $preview);
        $reply = array_key_exists('greeting', $this->conversationTree->socialFamilies($version->tree))
            ? $this->line($avatar, $version, 'social', 'greeting', null, $state)
            : $this->line($avatar, $version, 'greeting', null, null, $state);
        $state['closed'] = false;
        $this->storeState($avatar, $state, $preview);

        return $reply;
    }

    /** @return array<string, mixed> */
    public function respond(Avatar $avatar, string $transcript, bool $preview = false): array
    {
        $version = $this->version($avatar, $preview);
        $state = $this->state($avatar, $preview);
        $selection = $this->router->route($avatar, $version, $transcript, $state);

        if ($selection['status'] === 'queued') {
            $connector = $this->line($avatar, $version, 'connector', 'queue', null, $state);
            $this->storeState($avatar, $state, $preview);

            return ['status' => 'queued', 'ticket' => $selection['ticket'], 'connector' => $connector];
        }

        return $this->selection($avatar, $version, $selection, $state, $preview);
    }

    /** @return array<string, mixed> */
    public function poll(Avatar $avatar, string $ticket, bool $preview = false): array
    {
        $version = $this->version($avatar, $preview);
        $state = $this->state($avatar, $preview);
        $selection = $this->router->poll($version, $ticket, $state);
        if ($selection['status'] === 'queued') {
            return ['status' => 'queued'];
        }

        return $this->selection($avatar, $version, $selection, $state, $preview);
    }

    public function canStart(Avatar $avatar, bool $preview = false): bool
    {
        $version = $this->availableVersion($avatar, $preview);

        if (! $version) {
            return false;
        }

        $tree = $version->tree;
        $key = array_key_exists('greeting', $this->conversationTree->socialFamilies($tree))
            ? 'social.greeting.0'
            : 'greeting.0';

        return $version->audioAssets()->where('asset_key', $key)->where('status', 'ready')->exists();
    }

    /** @return list<string> */
    public function topicTitles(Avatar $avatar, bool $preview = false): array
    {
        $version = $this->availableVersion($avatar, $preview);
        $topics = $version?->tree['topics'] ?? [];

        if (! is_array($topics)) {
            return [];
        }

        return collect($topics)
            ->pluck('title')
            ->filter(fn (mixed $title): bool => is_string($title) && trim($title) !== '')
            ->map(fn (string $title): string => trim($title))
            ->values()
            ->all();
    }

    private function version(Avatar $avatar, bool $preview): ConversationVersion
    {
        $version = $this->availableVersion($avatar, $preview);
        abort_unless($version, 503, 'Este avatar todavía está preparando sus respuestas.');

        $version->loadMissing('audioAssets');

        return $version;
    }

    private function availableVersion(Avatar $avatar, bool $preview): ?ConversationVersion
    {
        $stagingVersion = $avatar->conversationVersions()
            ->whereIn('status', ['staging', 'published'])
            ->latest('id')
            ->first();

        if ($preview || ! $stagingVersion) {
            return $preview ? $stagingVersion : $avatar->publishedConversation();
        }

        $progress = $this->coverage->summarize($stagingVersion);
        if ($progress['topics_total'] > 0 && $progress['topics_covered'] === $progress['topics_total']) {
            return $stagingVersion;
        }

        return $avatar->publishedConversation();
    }

    /**
     * @param  array<string, mixed>  $selection
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private function selection(Avatar $avatar, ConversationVersion $version, array $selection, array $state, bool $preview): array
    {
        if ($selection['status'] !== 'ready' || ! is_array($selection['items'] ?? null) || $selection['items'] === []) {
            $reply = $this->line($avatar, $version, 'fallback', null, null, $state);
            $this->storeState($avatar, $state, $preview);

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
            $this->storeState($avatar, $state, $preview);

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
        $this->storeState($avatar, $state, $preview);

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

        $availableIndexes = collect($variants)
            ->keys()
            ->filter(fn (int $index): bool => $version->audioAssets->contains(fn ($asset): bool => $asset->asset_key === "{$prefix}.{$index}" && $asset->status === 'ready'))
            ->values()
            ->all();
        if ($availableIndexes === [] && $version->status === 'published') {
            $availableIndexes = array_keys($variants);
        }
        if ($availableIndexes === []) {
            if ($kind !== 'fallback') {
                return $this->line($avatar, $version, 'fallback', null, null, $state);
            }

            abort(503, 'El primer audio de prueba todavía está preparándose.');
        }

        $index = $this->nextVariant($prefix, $availableIndexes, $state);
        $key = "{$prefix}.{$index}";
        $asset = $version->audioAssets->firstWhere('asset_key', $key);

        return [
            'text' => $variants[$index],
            'audio_url' => $asset?->status === 'ready' && $asset->path ? '/storage/'.ltrim($asset->path, '/') : null,
            'mime_type' => $asset?->mime_type,
            'duration_ms' => $asset?->duration_ms,
            'visemes' => $asset?->visemes ?? [],
        ];
    }

    /** @param array<string, mixed> $state */
    /** @param list<int> $availableIndexes */
    private function nextVariant(string $prefix, array $availableIndexes, array &$state): int
    {
        $last = $state['variants'][$prefix] ?? null;
        $position = is_int($last) ? array_search($last, $availableIndexes, true) : false;
        $index = $position === false
            ? $availableIndexes[array_rand($availableIndexes)]
            : $availableIndexes[($position + 1) % count($availableIndexes)];
        $state['variants'][$prefix] = $index;

        return $index;
    }

    /** @return array<string, mixed> */
    private function state(Avatar $avatar, bool $preview): array
    {
        $state = session()->get($this->stateKey($avatar, $preview), []);

        return is_array($state) ? $state : [];
    }

    /** @param array<string, mixed> $state */
    private function storeState(Avatar $avatar, array $state, bool $preview): void
    {
        session()->put($this->stateKey($avatar, $preview), $state);
    }

    private function stateKey(Avatar $avatar, bool $preview): string
    {
        return "avatar-call.{$avatar->id}.".($preview ? 'preview' : 'published');
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
