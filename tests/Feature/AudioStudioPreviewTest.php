<?php

namespace Tests\Feature;

use App\Models\Avatar;
use App\Models\ConversationVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AudioStudioPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_audio_studio_renders_inline_preview_controls(): void
    {
        config([
            'avatar.audio_role' => 'studio',
            'avatar.sync_url' => '',
            'avatar.sync_token' => '',
        ]);
        $admin = User::factory()->create(['is_admin' => true]);
        $avatar = Avatar::factory()->create();
        ConversationVersion::factory()->create(['avatar_id' => $avatar->id]);

        $this->actingAs($admin)->get(route('admin.audio-studio.show', $avatar))
            ->assertOk()
            ->assertSee('data-audio-preview-url', false)
            ->assertDontSee('target="_blank"', false);
    }

    public function test_preview_returns_a_clear_json_error_when_voicebox_rejects_the_connection(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('voice-references/anita.wav', 'anita-reference');
        config([
            'avatar.audio_role' => 'studio',
            'avatar.voicebox_enabled' => true,
            'avatar.voicebox_url' => 'https://voice.test',
            'avatar.voicebox_token' => 'test-token',
            'avatar.voicebox_synthetic_reference_path' => Storage::disk('local')->path('voice-references/anita.wav'),
        ]);
        Http::preventStrayRequests();
        Http::fake(['https://voice.test/v1/cloned/speech' => Http::response([], 401)]);
        $admin = User::factory()->create(['is_admin' => true]);
        $avatar = Avatar::factory()->create();
        $version = ConversationVersion::factory()->create(['avatar_id' => $avatar->id]);

        $this->actingAs($admin)->getJson(route('admin.audio-studio.preview', [$avatar, $version, 'asset_key' => 'greeting.0']))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Voicebox local rechazó la conexión. Reinicia el servicio local e inténtalo nuevamente.');
    }
}
