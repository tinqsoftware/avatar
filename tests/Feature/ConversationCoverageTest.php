<?php

namespace Tests\Feature;

use App\Models\AudioAsset;
use App\Models\Avatar;
use App\Models\ConversationVersion;
use App\Services\ConversationCoverage;
use App\Services\ConversationTree;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationCoverageTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_topic_coverage_from_the_first_summary_variant(): void
    {
        $avatar = Avatar::factory()->create();
        $tree = $this->tree();
        $version = ConversationVersion::factory()->create(['avatar_id' => $avatar->id, 'tree' => $tree]);
        foreach (app(ConversationTree::class)->lines($tree) as $key => $text) {
            AudioAsset::factory()->create([
                'conversation_version_id' => $version->id,
                'asset_key' => $key,
                'text' => $text,
                'status' => in_array($key, ['greeting.0', 'topic.agua.summary.0'], true) ? 'ready' : 'pending',
            ]);
        }

        $coverage = app(ConversationCoverage::class)->summarize($version);

        $this->assertSame(2, $coverage['ready_assets']);
        $this->assertSame(2, $coverage['topics_total']);
        $this->assertSame(1, $coverage['topics_covered']);
        $this->assertSame(50, $coverage['topics_percent']);
        $this->assertSame(1, $coverage['variant_rounds'][0]['ready']);
        $this->assertSame(2, $coverage['variant_rounds'][0]['total']);
    }

    /** @return array<string, mixed> */
    private function tree(): array
    {
        $topic = fn (string $id): array => [
            'id' => $id,
            'title' => ucfirst($id),
            'description' => "Información sobre {$id}.",
            'examples' => ["Quiero saber de {$id}."],
            'keywords' => [$id],
            'summary' => ['variants' => ['Resumen.']],
            'detail' => ['variants' => ['Detalle.']],
            'next' => ['variants' => ['Siguiente.']],
        ];

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
            'topics' => [$topic('agua'), $topic('seguridad')],
        ];
    }
}
