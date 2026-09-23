<?php

namespace App\Services;

use App\Models\Avatar;
use App\Models\ConversationVersion;

class StaticConversationService
{
    public function __construct(private readonly ConversationRouterService $router) {}

    /**
     * @return array<string, mixed>
     */
    public function greeting(Avatar $avatar): array
    {
        return $this->line($avatar, $this->version($avatar), 'greeting', null, null);
    }

    /**
     * @return array<string, mixed>
     */
    public function respond(Avatar $avatar, string $transcript): array
    {
        $version = $this->version($avatar);
        $state = session()->get($this->stateKey($avatar), []);
        $selection = $this->router->route($avatar, $version, $transcript, $state);

        if ($selection['status'] === 'queued') {
            return [
                'status' => 'queued',
                'ticket' => $selection['ticket'],
                'connector' => $this->line($avatar, $version, 'connector', null, null),
            ];
        }

        return $this->selection($avatar, $version, $selection);
    }

    /**
     * @return array<string, mixed>
     */
    public function poll(Avatar $avatar, string $ticket): array
    {
        $version = $this->version($avatar);
        $selection = $this->router->poll($version, $ticket, session()->get($this->stateKey($avatar), []));
        if ($selection['status'] === 'queued') {
            return ['status' => 'queued'];
        }

        return $this->selection($avatar, $version, $selection);
    }

    private function version(Avatar $avatar): ConversationVersion
    {
        $version = $avatar->publishedConversation();
        abort_unless($version, 503, 'Este avatar todavía está preparando sus respuestas.');

        return $version;
    }

    /**
     * @param  array<string, mixed>  $selection
     * @return array<string, mixed>
     */
    private function selection(Avatar $avatar, ConversationVersion $version, array $selection): array
    {
        if ($selection['status'] !== 'ready') {
            return $this->line($avatar, $version, 'fallback', null, null);
        }

        $topic = collect($version->tree['topics'])->firstWhere('id', $selection['topic_id']);
        if (! is_array($topic)) {
            return $this->line($avatar, $version, 'fallback', null, null);
        }

        session()->put($this->stateKey($avatar), [
            'topic_id' => $topic['id'],
            'stage' => $selection['stage'],
        ]);

        return $this->line($avatar, $version, 'topic', $topic['id'], $selection['stage']);
    }

    /**
     * @return array<string, mixed>
     */
    private function line(Avatar $avatar, ConversationVersion $version, string $kind, ?string $topicId, ?string $stage): array
    {
        $variants = match ($kind) {
            'topic' => collect($version->tree['topics'])->firstWhere('id', $topicId)[$stage]['variants'],
            'connector' => $version->tree['connectors'],
            default => $version->tree[$kind]['variants'],
        };
        $prefix = $kind === 'topic' ? "topic.{$topicId}.{$stage}" : $kind;
        $index = $this->nextVariant($avatar, $prefix, count($variants));
        $key = "{$prefix}.{$index}";
        $asset = $version->audioAssets()->where('asset_key', $key)->first();

        return [
            'status' => 'ready',
            'text' => $variants[$index],
            // Keep the public host that served the current avatar. APP_URL may be
            // a deployment default and must not redirect the browser to another
            // local host, subdomain, or private storage endpoint.
            'audio_url' => $asset?->status === 'ready' && $asset->path ? '/storage/'.ltrim($asset->path, '/') : null,
            'mime_type' => $asset?->mime_type,
            'duration_ms' => $asset?->duration_ms,
            'visemes' => $asset?->visemes ?? [],
        ];
    }

    private function nextVariant(Avatar $avatar, string $prefix, int $count): int
    {
        $key = "{$this->stateKey($avatar)}.variants.{$prefix}";
        $last = session()->get($key);
        $index = is_int($last) ? ($last + 1) % $count : random_int(0, $count - 1);
        session()->put($key, $index);

        return $index;
    }

    private function stateKey(Avatar $avatar): string
    {
        return "avatar-call.{$avatar->id}";
    }
}
