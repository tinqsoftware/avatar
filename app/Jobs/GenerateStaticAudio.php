<?php

namespace App\Jobs;

use App\Models\AudioAsset;
use App\Services\StaticAudioPublisher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class GenerateStaticAudio implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public readonly int $audioAssetId) {}

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
            $version->update(['status' => 'published', 'published_at' => now()]);
            $version->avatar->update(['status' => 'published']);
        } elseif ($version->audioAssets()->whereIn('status', ['pending', 'generating'])->doesntExist()) {
            $version->update(['status' => 'draft', 'published_at' => null]);
            $version->avatar->update(['status' => 'draft']);
        }
    }
}
