<?php

namespace Tests\Feature;

use App\Models\AudioAsset;
use App\Models\Avatar;
use App\Models\ConversationVersion;
use App\Services\ConversationTree;
use App\Services\ConversationVersionBundle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class ConversationVersionBundleTest extends TestCase
{
    use RefreshDatabase;

    public function test_imports_a_verified_bundle_and_only_then_replaces_the_published_version(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        [$avatar, $source] = $this->readyVersion();
        $archive = Storage::disk('local')->path('avatar-exports/ica-demo.zip');

        app(ConversationVersionBundle::class)->export($source, $archive);
        $result = app(ConversationVersionBundle::class)->import($archive);

        $source->refresh();
        $imported = $result['version']->fresh();
        $this->assertSame('archived', $source->status);
        $this->assertSame('published', $imported->status);
        $this->assertSame('published', $avatar->fresh()->status);
        $this->assertCount(11, $imported->audioAssets);
        $this->assertDatabaseHas('audio_assets', [
            'conversation_version_id' => $imported->id,
            'asset_key' => 'social.greeting.0',
            'status' => 'ready',
        ]);
        Storage::disk('public')->assertExists($imported->audioAssets()->where('asset_key', 'social.greeting.0')->value('path'));
    }

    public function test_rejects_a_tampered_bundle_without_archiving_the_current_version(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        [, $source] = $this->readyVersion('ica-demo-tampered');
        $archive = Storage::disk('local')->path('avatar-exports/ica-demo-tampered.zip');
        app(ConversationVersionBundle::class)->export($source, $archive);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($archive) === true);
        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($zip->addFromString($manifest['assets'][0]['file'], 'tampered-audio'));
        $zip->close();

        try {
            app(ConversationVersionBundle::class)->import($archive);
            $this->fail('Expected the tampered package to be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('verificación', $exception->getMessage());
        }

        $this->assertSame('published', $source->fresh()->status);
        $this->assertSame(1, ConversationVersion::where('avatar_id', $source->avatar_id)->where('status', 'published')->count());
    }

    /** @return array{Avatar, ConversationVersion} */
    private function readyVersion(string $slug = 'ica-demo'): array
    {
        $avatar = Avatar::factory()->create(['slug' => $slug, 'status' => 'published']);
        $tree = [
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
                    'description' => 'Inicio amable.',
                    'examples' => ['Hola.'],
                    'keywords' => ['hola'],
                    'variants' => ['Hola, qué gusto conversar contigo.'],
                ],
            ],
            'topics' => [[
                'id' => 'agua',
                'title' => 'Agua',
                'description' => 'Agua segura.',
                'examples' => ['Quiero saber sobre agua.'],
                'keywords' => ['agua'],
                'summary' => ['variants' => ['Resumen.']],
                'detail' => ['variants' => ['Detalle.']],
                'next' => ['variants' => ['Siguiente.']],
            ]],
        ];
        $version = ConversationVersion::factory()->create([
            'avatar_id' => $avatar->id,
            'tree' => $tree,
            'status' => 'published',
            'published_at' => now(),
        ]);
        foreach (app(ConversationTree::class)->lines($tree) as $assetKey => $text) {
            $path = "avatars/{$slug}/source/".str_replace('.', '-', $assetKey).'.mp3';
            Storage::disk('public')->put($path, "audio-{$assetKey}");
            AudioAsset::factory()->create([
                'conversation_version_id' => $version->id,
                'asset_key' => $assetKey,
                'text' => $text,
                'path' => $path,
                'duration_ms' => 1000,
                'visemes' => [['at_ms' => 0, 'value' => 0]],
                'status' => 'ready',
            ]);
        }

        return [$avatar, $version];
    }
}
