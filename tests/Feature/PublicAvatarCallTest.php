<?php

namespace Tests\Feature;

use App\Models\AudioAsset;
use App\Models\Avatar;
use App\Models\ConversationVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PublicAvatarCallTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['avatar.local_default_slug' => 'ica-demo', 'avatar.router_enabled' => false]);
    }

    public function test_returns_a_static_topic_response_without_calling_salad(): void
    {
        [$avatar, $version] = $this->publishedAvatar();
        AudioAsset::factory()->create([
            'conversation_version_id' => $version->id,
            'asset_key' => 'topic.agua.summary.0',
            'text' => 'Respuesta de agua.',
            'path' => 'avatars/ica-demo/answer.mp3',
            'duration_ms' => 1000,
            'visemes' => [['at_ms' => 0, 'value' => 0]],
            'status' => 'ready',
        ]);
        Http::preventStrayRequests();

        $this->postJson('/asistente/mensaje', ['message' => 'Quiero saber sobre agua'])
            ->assertOk()
            ->assertJsonPath('status', 'ready')
            ->assertJsonPath('text', 'Resumen.')
            ->assertJsonPath('audio_url', '/storage/avatars/ica-demo/answer.mp3');
    }

    public function test_returns_not_found_for_an_unknown_avatar_subdomain(): void
    {
        config(['avatar.public_domain_suffix' => 'ia.tinq.pe']);

        $this->withServerVariables(['HTTP_HOST' => 'missing.ia.tinq.pe'])
            ->get('/')
            ->assertNotFound();
    }

    public function test_call_requires_an_explicit_user_action_before_audio_can_start(): void
    {
        $this->publishedAvatar();

        $this->get('/llamada')
            ->assertOk()
            ->assertSee('id="startConversation"', false)
            ->assertSee('id="microphoneButton"', false)
            ->assertSee('disabled', false);
    }

    public function test_advances_to_the_next_static_stage_when_user_requests_more_information(): void
    {
        [, $version] = $this->publishedAvatar();
        AudioAsset::factory()->create([
            'conversation_version_id' => $version->id,
            'asset_key' => 'topic.agua.summary.0',
            'text' => 'Resumen.',
            'status' => 'ready',
        ]);
        AudioAsset::factory()->create([
            'conversation_version_id' => $version->id,
            'asset_key' => 'topic.agua.detail.0',
            'text' => 'Detalle.',
            'status' => 'ready',
        ]);

        $this->postJson('/asistente/mensaje', ['message' => 'agua'])
            ->assertOk()
            ->assertJsonPath('text', 'Resumen.');

        $this->postJson('/asistente/mensaje', ['message' => 'cuéntame más'])
            ->assertOk()
            ->assertJsonPath('text', 'Detalle.');
    }

    /**
     * @return array{Avatar, ConversationVersion}
     */
    private function publishedAvatar(): array
    {
        $avatar = Avatar::factory()->create(['slug' => 'ica-demo', 'status' => 'published']);
        $version = ConversationVersion::factory()->create([
            'avatar_id' => $avatar->id,
            'status' => 'published',
            'published_at' => now(),
            'tree' => [
                'greeting' => ['variants' => ['Hola.']],
                'fallback' => ['variants' => ['No encontré ese tema.']],
                'connectors' => ['Un momento.'],
                'topics' => [[
                    'id' => 'agua',
                    'title' => 'Agua',
                    'keywords' => ['agua'],
                    'summary' => ['variants' => ['Resumen.']],
                    'detail' => ['variants' => ['Detalle.']],
                    'next' => ['variants' => ['Siguiente.']],
                ]],
            ],
        ]);

        return [$avatar, $version];
    }
}
