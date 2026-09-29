<?php

namespace Tests\Feature;

use App\Jobs\GenerateStaticAudio;
use App\Models\AudioAsset;
use App\Models\Avatar;
use App\Models\ConversationVersion;
use App\Models\User;
use App\Services\ConversationTree;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AudioStudioLineEditorTest extends TestCase
{
    public function test_it_requeues_only_the_edited_audio_line(): void
    {
        config(['avatar.audio_role' => 'studio']);
        $this->withoutMiddleware(PreventRequestForgery::class);
        Queue::fake();
        $admin = User::factory()->create(['is_admin' => true]);
        $avatar = Avatar::factory()->create();
        $tree = $this->tree();
        $version = ConversationVersion::factory()->create([
            'avatar_id' => $avatar->id,
            'tree' => $tree,
            'status' => 'generating',
        ]);
        $lines = app(ConversationTree::class)->lines($tree);
        foreach ($lines as $assetKey => $text) {
            AudioAsset::factory()->create([
                'conversation_version_id' => $version->id,
                'asset_key' => $assetKey,
                'text' => $text,
                'status' => 'ready',
                'synced_at' => now(),
            ]);
        }

        $this->actingAs($admin)
            ->patch(route('admin.audio-studio.line.update', [$avatar, $version]), [
                'asset_key' => 'topic.agua.summary.0',
                'text' => 'Te explico el nuevo contenido aprobado sobre agua.',
            ])
            ->assertRedirect();

        $version->refresh();
        $asset = $version->audioAssets()->where('asset_key', 'topic.agua.summary.0')->firstOrFail();
        $this->assertSame('Te explico el nuevo contenido aprobado sobre agua.', $version->tree['topics'][0]['summary']['variants'][0]);
        $this->assertSame('pending', $asset->status);
        $this->assertNull($asset->synced_at);
        Queue::assertPushed(GenerateStaticAudio::class, fn (GenerateStaticAudio $job): bool => $job->audioAssetId === $asset->id);
        $this->assertSame(count($lines) - 1, $version->audioAssets()->where('status', 'ready')->count());
    }

    /** @return array<string, mixed> */
    private function tree(): array
    {
        return [
            'greeting' => ['variants' => ['Hola.']],
            'fallback' => ['variants' => ['No encontré ese tema.']],
            'connectors' => [
                'queue' => ['variants' => ['Un momento.']],
                'multi_intro' => ['variants' => ['Varios temas.']],
                'multi_bridge' => ['variants' => ['Siguiente tema.']],
                'multi_outro' => ['variants' => ['Cierre.']],
                'continue_last' => ['variants' => ['Continuación.']],
            ],
            'topics' => [[
                'id' => 'agua',
                'title' => 'Agua',
                'description' => 'Información sobre agua.',
                'examples' => ['Quiero saber de agua.'],
                'keywords' => ['agua'],
                'summary' => ['variants' => ['Resumen de agua.']],
                'detail' => ['variants' => ['Detalle de agua.']],
                'next' => ['variants' => ['Siguiente sobre agua.']],
            ]],
        ];
    }
}
