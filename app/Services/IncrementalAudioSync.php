<?php

namespace App\Services;

use App\Models\AudioAsset;
use App\Models\ConversationVersion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Throwable;

class IncrementalAudioSync
{
    private const MAX_DELIVERY_BATCH_BYTES = 1_000_000;

    public function __construct(
        private readonly ConversationTree $conversationTree,
        private readonly ConversationVersionBundle $bundle,
        private readonly RemoteDeliveryClient $delivery,
    ) {}

    /**
     * Envía hasta cinco MP3 listos por vez. El límite de tamaño mantiene cada
     * carga por debajo del límite HTTP del VPS; un MP3 grande viaja solo.
     */
    public function syncAvailable(ConversationVersion $version): void
    {
        if (config('avatar.audio_role') !== 'studio') {
            return;
        }

        $version->loadMissing('avatar');
        if (! $version->avatar->delivery_slug) {
            return;
        }

        $expectedCount = count($this->conversationTree->lines($version->tree));
        if (! $version->sync_key || $version->expected_audio_assets_count !== $expectedCount) {
            $version->update([
                'sync_key' => $version->sync_key ?: "{$version->avatar->slug}:{$version->id}",
                'expected_audio_assets_count' => $expectedCount,
            ]);
            $version->refresh();
        }

        while (true) {
            $candidates = $version->audioAssets()
                ->where('status', 'ready')
                ->whereNull('synced_at')
                ->orderBy('id')
                ->limit(5)
                ->get();
            $assets = $this->deliveryBatch($candidates);
            if ($assets->isEmpty()) {
                return;
            }

            $hasUnfinishedAssets = $version->audioAssets()->whereIn('status', ['pending', 'generating'])->exists();
            if ($candidates->count() < 5 && $hasUnfinishedAssets) {
                return;
            }

            $path = sprintf('avatar-sync/incremental/%s-%d-%s.zip', $version->avatar->slug, $version->id, bin2hex(random_bytes(6)));
            try {
                Storage::disk('local')->makeDirectory('avatar-sync/incremental');
                $archivePath = Storage::disk('local')->path($path);
                $this->bundle->exportIncremental($version, $assets, $archivePath, $version->avatar->delivery_slug);
                $this->delivery->upload($archivePath);
                $version->audioAssets()->whereKey($assets->modelKeys())->update(['synced_at' => now()]);
            } catch (Throwable $exception) {
                report($exception);

                return;
            } finally {
                Storage::disk('local')->delete($path);
            }
        }
    }

    /**
     * @param  Collection<int, AudioAsset>  $candidates
     * @return Collection<int, AudioAsset>
     */
    private function deliveryBatch(Collection $candidates): Collection
    {
        $totalBytes = 0;

        return $candidates->filter(function (AudioAsset $asset) use (&$totalBytes): bool {
            $size = $asset->path && Storage::disk('public')->exists($asset->path)
                ? Storage::disk('public')->size($asset->path)
                : 0;

            if ($totalBytes > 0 && $totalBytes + $size > self::MAX_DELIVERY_BATCH_BYTES) {
                return false;
            }

            $totalBytes += $size;

            return true;
        })->values();
    }
}
