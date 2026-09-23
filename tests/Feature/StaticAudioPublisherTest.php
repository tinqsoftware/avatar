<?php

namespace Tests\Feature;

use App\Models\AudioAsset;
use App\Models\Avatar;
use App\Models\ConversationVersion;
use App\Services\StaticAudioPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StaticAudioPublisherTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_a_ready_salad_health_check_before_batching(): void
    {
        config([
            'avatar.voicebox_enabled' => true,
            'avatar.voicebox_url' => 'https://voice.test',
            'avatar.voicebox_token' => 'test-token',
        ]);
        Http::preventStrayRequests();
        Http::fake(['https://voice.test/health' => Http::response(['models_loaded' => true])]);

        $this->assertTrue(app(StaticAudioPublisher::class)->isHealthy());
    }

    public function test_rejects_an_incomplete_salad_health_check(): void
    {
        config([
            'avatar.voicebox_enabled' => true,
            'avatar.voicebox_url' => 'https://voice.test',
            'avatar.voicebox_token' => 'test-token',
        ]);
        Http::preventStrayRequests();
        Http::fake(['https://voice.test/health' => Http::response(['models_loaded' => false])]);

        $this->assertFalse(app(StaticAudioPublisher::class)->isHealthy());
    }

    public function test_publishes_a_salad_mp3_once_with_a_viseme_timeline(): void
    {
        config([
            'avatar.voicebox_enabled' => true,
            'avatar.voicebox_url' => 'https://voice.test',
            'avatar.voicebox_token' => 'test-token',
        ]);
        Storage::fake('public');
        $avatar = Avatar::factory()->create(['slug' => 'ica-demo']);
        $version = ConversationVersion::factory()->create(['avatar_id' => $avatar->id]);
        $asset = AudioAsset::factory()->create([
            'conversation_version_id' => $version->id,
            'asset_key' => 'greeting.0',
            'text' => 'Hola Ica.',
        ]);
        Http::preventStrayRequests();
        Http::fake(['https://voice.test/v1/anita/speech' => Http::response([
            'audio_base64' => base64_encode('demo-mp3'),
            'duration_ms' => 1000,
            'words' => [['text' => 'Hola', 'start_ms' => 0, 'end_ms' => 500]],
        ])]);

        app(StaticAudioPublisher::class)->publish($asset->fresh(['conversationVersion.avatar']));

        $asset->refresh();
        $this->assertSame('ready', $asset->status);
        $this->assertSame(1000, $asset->duration_ms);
        $this->assertNotEmpty($asset->visemes);
        Storage::disk('public')->assertExists($asset->path);
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://voice.test/v1/anita/speech'
                && $request->header('Authorization')[0] === 'Bearer test-token'
                && $request['input'] === 'Hola Ica.';
        });
    }
}
