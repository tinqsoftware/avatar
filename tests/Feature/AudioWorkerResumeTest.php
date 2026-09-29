<?php

namespace Tests\Feature;

use App\Jobs\GenerateStaticAudio;
use App\LocalAudioWorker;
use App\Models\AudioAsset;
use App\Models\Avatar;
use App\Models\ConversationVersion;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AudioWorkerResumeTest extends TestCase
{
    use RefreshDatabase;

    public function test_resuming_a_local_batch_preserves_ready_assets_and_requeues_failures(): void
    {
        config(['avatar.audio_role' => 'studio']);
        $this->withoutMiddleware(PreventRequestForgery::class);
        Queue::fake();
        $this->mock(LocalAudioWorker::class, function ($mock): void {
            $mock->shouldReceive('start')->once();
        });

        $admin = User::factory()->create(['is_admin' => true]);
        $avatar = Avatar::factory()->create();
        $version = ConversationVersion::factory()->create([
            'avatar_id' => $avatar->id,
            'status' => 'generating',
        ]);
        $readyAsset = AudioAsset::factory()->create([
            'conversation_version_id' => $version->id,
            'asset_key' => 'greeting.0',
            'status' => 'ready',
        ]);
        $failedAsset = AudioAsset::factory()->create([
            'conversation_version_id' => $version->id,
            'asset_key' => 'fallback.0',
            'status' => 'failed',
            'error' => 'La Mac se apagó.',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.versions.resume', [$avatar, $version]))
            ->assertRedirect();

        $this->assertDatabaseHas('audio_assets', [
            'id' => $readyAsset->id,
            'status' => 'ready',
        ]);
        $this->assertDatabaseHas('audio_assets', [
            'id' => $failedAsset->id,
            'status' => 'pending',
            'error' => null,
        ]);
        Queue::assertPushed(GenerateStaticAudio::class, fn (GenerateStaticAudio $job): bool => $job->audioAssetId === $failedAsset->id);
    }
}
