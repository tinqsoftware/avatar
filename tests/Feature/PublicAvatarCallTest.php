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
            ->assertJsonPath('playlist.0.audio_url', '/storage/avatars/ica-demo/answer.mp3');
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
            ->assertJsonPath('playlist.0.text', 'Resumen.');

        $this->postJson('/asistente/mensaje', ['message' => 'cuéntame más'])
            ->assertOk()
            ->assertJsonPath('playlist.1.text', 'Detalle.');
    }

    public function test_builds_a_playlist_for_two_topics_in_the_order_they_are_mentioned(): void
    {
        [$avatar] = $this->publishedAvatar();

        $this->postJson('/asistente/mensaje', ['message' => 'Quiero saber sobre seguridad y agua'])
            ->assertOk()
            ->assertJsonPath('playlist.0.text', 'Varios temas.')
            ->assertJsonPath('playlist.1.text', 'Resumen de seguridad.')
            ->assertJsonPath('playlist.2.text', 'Siguiente tema.')
            ->assertJsonPath('playlist.3.text', 'Resumen.')
            ->assertJsonPath('playlist.4.text', 'Cierre.')
            ->assertSessionMissing("avatar-call.{$avatar->id}.transcript");
    }

    public function test_continues_the_last_topic_after_a_multi_topic_response(): void
    {
        $this->publishedAvatar();

        $this->postJson('/asistente/mensaje', ['message' => 'agua y seguridad'])->assertOk();

        $this->postJson('/asistente/mensaje', ['message' => 'cuéntame más'])
            ->assertOk()
            ->assertJsonPath('playlist.1.text', 'Detalle de seguridad.');
    }

    public function test_limits_a_local_multi_topic_playlist_to_three_topics(): void
    {
        $this->publishedAvatar();

        $this->postJson('/asistente/mensaje', ['message' => 'agua, seguridad y empleo'])
            ->assertOk()
            ->assertJsonCount(7, 'playlist')
            ->assertJsonPath('playlist.5.text', 'Resumen de empleo.');
    }

    public function test_does_not_repeat_a_topic_variant_immediately(): void
    {
        [, $version] = $this->publishedAvatar();
        $tree = $version->tree;
        $tree['topics'][0]['summary']['variants'] = ['Resumen uno.', 'Resumen dos.'];
        $version->update(['tree' => $tree]);

        $first = $this->postJson('/asistente/mensaje', ['message' => 'agua'])->json('playlist.0.text');
        $second = $this->postJson('/asistente/mensaje', ['message' => 'agua'])->json('playlist.0.text');

        $this->assertNotSame($first, $second);
    }

    public function test_accepts_an_ordered_multi_topic_selection_from_the_remote_router(): void
    {
        $this->publishedAvatar();
        config([
            'avatar.router_enabled' => true,
            'avatar.router_url' => 'https://router.test',
            'avatar.router_token' => 'router-token',
        ]);
        Http::fake(['https://router.test/v1/route' => Http::response([
            'status' => 'ready',
            'items' => [
                ['topic_id' => 'empleo', 'stage' => 'summary', 'action' => 'select'],
                ['topic_id' => 'agua', 'stage' => 'summary', 'action' => 'select'],
            ],
        ])]);

        $this->postJson('/asistente/mensaje', ['message' => 'trabajo y agua'])
            ->assertOk()
            ->assertJsonPath('playlist.1.text', 'Resumen de empleo.')
            ->assertJsonPath('playlist.3.text', 'Resumen.');
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
                'connectors' => [
                    'queue' => ['variants' => ['Un momento.']],
                    'multi_intro' => ['variants' => ['Varios temas.']],
                    'multi_bridge' => ['variants' => ['Siguiente tema.']],
                    'multi_outro' => ['variants' => ['Cierre.']],
                    'continue_last' => ['variants' => ['Continuación.']],
                ],
                'topics' => [
                    [
                        'id' => 'agua',
                        'title' => 'Agua',
                        'keywords' => ['agua'],
                        'summary' => ['variants' => ['Resumen.']],
                        'detail' => ['variants' => ['Detalle.']],
                        'next' => ['variants' => ['Siguiente.']],
                    ],
                    [
                        'id' => 'seguridad',
                        'title' => 'Seguridad',
                        'keywords' => ['seguridad'],
                        'summary' => ['variants' => ['Resumen de seguridad.']],
                        'detail' => ['variants' => ['Detalle de seguridad.']],
                        'next' => ['variants' => ['Siguiente de seguridad.']],
                    ],
                    [
                        'id' => 'empleo',
                        'title' => 'Empleo',
                        'keywords' => ['empleo'],
                        'summary' => ['variants' => ['Resumen de empleo.']],
                        'detail' => ['variants' => ['Detalle de empleo.']],
                        'next' => ['variants' => ['Siguiente de empleo.']],
                    ],
                ],
            ],
        ]);

        return [$avatar, $version];
    }
}
