<?php

namespace Tests\Feature;

use App\Models\Avatar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AudioStudioSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivery_lists_destination_avatars_only_for_a_valid_sync_token(): void
    {
        config([
            'avatar.audio_role' => 'delivery',
            'avatar.sync_token' => 'sync-test-token',
        ]);
        $avatar = Avatar::factory()->create(['slug' => 'juanito-ica']);

        $this->getJson('/api/internal/audio-sync/avatars')->assertUnauthorized();

        $this->withToken('sync-test-token')->getJson('/api/internal/audio-sync/avatars')
            ->assertOk()
            ->assertJsonPath('data.0.slug', $avatar->slug);
    }

    public function test_studio_does_not_expose_sync_endpoints(): void
    {
        config([
            'avatar.audio_role' => 'studio',
            'avatar.sync_token' => 'sync-test-token',
        ]);

        $this->withToken('sync-test-token')->getJson('/api/internal/audio-sync/avatars')->assertUnauthorized();
    }
}
