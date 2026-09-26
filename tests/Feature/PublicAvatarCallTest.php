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
            ->assertSee('id="callHint"', false)
            ->assertDontSee('Pulsa “Iniciar conversación” para escuchar a Anita.')
            ->assertDontSee('id="speakerButton"', false)
            ->assertDontSee('id="interactionBubble"', false)
            ->assertDontSee('data-transcription-url', false)
            ->assertDontSee('/asistente/transcripcion', false)
            ->assertSee('disabled', false);
    }

    public function test_call_renders_the_published_avatar_background(): void
    {
        [$avatar] = $this->publishedAvatar();
        $avatar->update(['background_path' => 'avatars/ica-demo/backgrounds/plaza.webp']);

        $this->get('/llamada')
            ->assertOk()
            ->assertSee('/storage/avatars/ica-demo/backgrounds/plaza.webp', false);
    }

    public function test_returns_404_when_audio_transcription_is_requested(): void
    {
        $this->publishedAvatar();

        $this->postJson('/asistente/transcripcion')->assertNotFound();
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

    public function test_uses_a_local_topic_when_the_remote_router_fails(): void
    {
        $this->publishedAvatar();
        config([
            'avatar.router_enabled' => true,
            'avatar.router_url' => 'https://router.test',
            'avatar.router_token' => 'router-token',
        ]);
        Http::preventStrayRequests();
        Http::fake(['https://router.test/v1/route' => Http::failedConnection()]);

        $this->postJson('/asistente/mensaje', ['message' => 'Necesito información sobre seguridad para mi barrio'])
            ->assertOk()
            ->assertJsonPath('playlist.0.text', 'Resumen de seguridad.');
    }

    public function test_sends_topic_descriptions_and_examples_to_qwen_for_a_natural_phrase(): void
    {
        $this->publishedAvatar();
        config([
            'avatar.router_enabled' => true,
            'avatar.router_url' => 'https://router.test',
            'avatar.router_token' => 'router-token',
        ]);
        Http::preventStrayRequests();
        Http::fake(['https://router.test/v1/route' => Http::response([
            'items' => [['topic_id' => 'seguridad', 'stage' => 'summary', 'action' => 'select']],
        ])]);

        $this->postJson('/asistente/mensaje', ['message' => 'En mi barrio me preocupa mucho salir por las noches'])
            ->assertOk()
            ->assertJsonPath('playlist.0.text', 'Resumen de seguridad.');

        Http::assertSent(fn ($request): bool => $request->data()['topics'][1]['description'] === 'Prevención y seguridad ciudadana.'
            && $request->data()['topics'][1]['examples'] === ['Me preocupa la seguridad de mi barrio.']
            && collect($request->data()['social'])->contains(
                fn (array $intent): bool => $intent['id'] === 'greeting',
            ),
        );
    }

    public function test_adds_a_friendly_greeting_before_a_local_topic_response(): void
    {
        $this->publishedAvatar();

        $this->postJson('/asistente/mensaje', ['message' => 'Hola, quisiera saber sobre seguridad'])
            ->assertOk()
            ->assertJsonPath('playlist.0.text', 'Hola, qué gusto conversar contigo.')
            ->assertJsonPath('playlist.1.text', 'Resumen de seguridad.');
    }

    public function test_rephrases_the_last_topic_after_a_clarification_request(): void
    {
        [, $version] = $this->publishedAvatar();
        $tree = $version->tree;
        $tree['topics'][0]['summary']['variants'] = ['Resumen uno.', 'Resumen dos.'];
        $version->update(['tree' => $tree]);

        $first = $this->postJson('/asistente/mensaje', ['message' => 'agua'])
            ->assertOk()
            ->json('playlist.0.text');

        $response = $this->postJson('/asistente/mensaje', ['message' => 'No entiendo, explícamelo de otra forma'])
            ->assertOk()
            ->assertJsonPath('playlist.0.text', 'Claro, te lo explico de otra manera.');

        $this->assertNotSame($first, $response->json('playlist.1.text'));
    }

    public function test_ends_the_conversation_with_a_friendly_farewell(): void
    {
        $this->publishedAvatar();

        $this->postJson('/asistente/mensaje', ['message' => 'No, gracias'])
            ->assertOk()
            ->assertJsonPath('playlist.0.text', 'De acuerdo, gracias por conversar conmigo.');
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
                'social' => [
                    'greeting' => [
                        'description' => 'Inicio amistoso.',
                        'examples' => ['Hola.'],
                        'keywords' => ['hola'],
                        'variants' => ['Hola, qué gusto conversar contigo.'],
                    ],
                    'gratitude' => [
                        'description' => 'Agradecimiento.',
                        'examples' => ['Gracias.'],
                        'keywords' => ['gracias'],
                        'variants' => ['Con gusto.'],
                    ],
                    'acknowledgement' => [
                        'description' => 'Confirmación.',
                        'examples' => ['Está bien.'],
                        'keywords' => ['esta bien'],
                        'variants' => ['Perfecto.'],
                    ],
                    'capabilities' => [
                        'description' => 'Temas disponibles.',
                        'examples' => ['¿De qué temas podemos hablar?'],
                        'keywords' => ['que temas'],
                        'variants' => ['Podemos hablar de agua y seguridad.'],
                    ],
                    'clarification' => [
                        'description' => 'Reformular una explicación.',
                        'examples' => ['No entiendo.'],
                        'keywords' => ['no entiendo', 'explicamelo de otra forma'],
                        'variants' => ['Claro, te lo explico de otra manera.'],
                    ],
                    'change-topic' => [
                        'description' => 'Cambio de tema.',
                        'examples' => ['Otro tema.'],
                        'keywords' => ['otro tema'],
                        'variants' => ['Claro, cambiemos de tema.'],
                    ],
                    'small-talk' => [
                        'description' => 'Conversación amable.',
                        'examples' => ['¿Cómo estás?'],
                        'keywords' => ['como estas'],
                        'variants' => ['Estoy bien, gracias.'],
                    ],
                    'farewell' => [
                        'description' => 'Cierre cordial.',
                        'examples' => ['No, gracias.'],
                        'keywords' => ['no gracias'],
                        'variants' => ['De acuerdo, gracias por conversar conmigo.'],
                    ],
                ],
                'topics' => [
                    [
                        'id' => 'agua',
                        'title' => 'Agua',
                        'description' => 'Agua segura y saneamiento.',
                        'examples' => ['Quiero saber sobre el agua.'],
                        'keywords' => ['agua'],
                        'summary' => ['variants' => ['Resumen.']],
                        'detail' => ['variants' => ['Detalle.']],
                        'next' => ['variants' => ['Siguiente.']],
                    ],
                    [
                        'id' => 'seguridad',
                        'title' => 'Seguridad',
                        'description' => 'Prevención y seguridad ciudadana.',
                        'examples' => ['Me preocupa la seguridad de mi barrio.'],
                        'keywords' => ['seguridad'],
                        'summary' => ['variants' => ['Resumen de seguridad.']],
                        'detail' => ['variants' => ['Detalle de seguridad.']],
                        'next' => ['variants' => ['Siguiente de seguridad.']],
                    ],
                    [
                        'id' => 'empleo',
                        'title' => 'Empleo',
                        'description' => 'Empleo y emprendimiento local.',
                        'examples' => ['Busco oportunidades de trabajo.'],
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
