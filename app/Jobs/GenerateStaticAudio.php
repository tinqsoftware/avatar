<?php

namespace App\Jobs;

use App\Models\AudioAsset;
use App\Services\StaticAudioPublisher;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class GenerateStaticAudio implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $uniqueFor = 3600;

    public function __construct(public readonly int $audioAssetId) {}

    public function uniqueId(): string
    {
        return (string) $this->audioAssetId;
    }

    /**
     * Execute the job.
     */
    public function handle(StaticAudioPublisher $publisher): void
    {
        $asset = AudioAsset::with('conversationVersion.avatar')->find($this->audioAssetId);
        if (! $asset || $asset->status === 'ready') {
            return;
        }

        $asset->update(['status' => 'generating', 'error' => null]);

        try {
            $publisher->publish($asset);
        } catch (Throwable) {
            $asset->update([
                'status' => 'failed',
                'error' => 'No se pudo generar este audio. Vuelve a publicarlo cuando la voz esté disponible.',
            ]);
        }

        $version = $asset->fresh()->conversationVersion;
        if ($version->audioAssets()->where('status', '!=', 'ready')->doesntExist()) {
            DB::transaction(function () use ($version): void {
                $lockedVersion = $version->newQuery()->with('avatar')->lockForUpdate()->findOrFail($version->id);
                if ($lockedVersion->audioAssets()->where('status', '!=', 'ready')->exists()) {
                    return;
                }

                if (config('avatar.audio_role') === 'studio') {
                    $lockedVersion->update(['status' => 'ready_to_upload', 'published_at' => null]);

                    return;
                }

                $lockedVersion->avatar->conversationVersions()
                    ->where('status', 'published')
                    ->whereKeyNot($lockedVersion->id)
                    ->update(['status' => 'archived']);
                $lockedVersion->update(['status' => 'published', 'published_at' => now()]);
                $lockedVersion->avatar->update(['status' => 'published']);
            }, attempts: 3);
        } elseif ($version->audioAssets()->whereIn('status', ['pending', 'generating'])->doesntExist()) {
            $version->update(['status' => 'draft', 'published_at' => null]);
            if (! $version->avatar->publishedConversation()) {
                $version->avatar->update(['status' => 'draft']);
            }
        }
    }
}
