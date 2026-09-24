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
        $reply = $this->line($avatar, $version, 'greeting', null, null, $state);
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
        $items = collect($selection['items'])
            ->take(3)
            ->filter(fn (mixed $item): bool => is_array($item) && is_string($item['topic_id'] ?? null) && $topics->has($item['topic_id']))
            ->values();

        if ($items->isEmpty()) {
            $reply = $this->line($avatar, $version, 'fallback', null, null, $state);
            $this->storeState($avatar, $state);

            return $reply;
        }

        $playlist = [];
        if ($items->count() > 1) {
            $playlist[] = $this->line($avatar, $version, 'connector', 'multi_intro', null, $state);
        }

        foreach ($items as $index => $item) {
            $topic = $topics->get($item['topic_id']);
            $stage = $this->stageFor($item, $state);
            if (($item['action'] ?? 'select') === 'continue' && $items->count() === 1) {
                $playlist[] = $this->line($avatar, $version, 'connector', 'continue_last', null, $state);
            }

            $playlist[] = $this->line($avatar, $version, 'topic', $topic['id'], $stage, $state);
            $state['topics'][$topic['id']] = ['stage' => $stage];

            if ($index < $items->count() - 1) {
                $playlist[] = $this->line($avatar, $version, 'connector', 'multi_bridge', null, $state);
            }
        }

        if ($items->count() > 1) {
            $playlist[] = $this->line($avatar, $version, 'connector', 'multi_outro', null, $state);
        }

        $state['last_topic_id'] = $items->last()['topic_id'];
        $state['last_topic_ids'] = $items->pluck('topic_id')->all();
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
}
