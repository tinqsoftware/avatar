<?php

namespace Tests\Unit;

use App\Services\ConversationTree;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ConversationTreeTest extends TestCase
{
    public function test_extracts_every_static_line_from_a_valid_tree(): void
    {
        $tree = [
            'greeting' => ['variants' => ['Hola.']],
            'fallback' => ['variants' => ['No.']],
            'connectors' => ['Espera.'],
            'topics' => [[
                'id' => 'agua', 'title' => 'Agua', 'keywords' => ['agua'],
                'description' => 'Información sobre agua segura.', 'examples' => ['Quiero saber sobre el agua.'],
                'summary' => ['variants' => ['Resumen.']], 'detail' => ['variants' => ['Detalle.']], 'next' => ['variants' => ['Siguiente.']],
            ]],
        ];

        $lines = (new ConversationTree)->lines((new ConversationTree)->validate($tree));

        $this->assertSame('Resumen.', $lines['topic.agua.summary.0']);
        $this->assertCount(10, $lines);
    }

    public function test_rejects_a_tree_without_connectors(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('connectors');

        (new ConversationTree)->validate([
            'greeting' => ['variants' => ['Hola.']],
            'fallback' => ['variants' => ['No.']],
            'topics' => [],
        ]);
    }

    public function test_rejects_a_topic_without_examples_for_natural_language_routing(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('examples');

        (new ConversationTree)->validate([
            'greeting' => ['variants' => ['Hola.']],
            'fallback' => ['variants' => ['No.']],
            'connectors' => ['Espera.'],
            'topics' => [[
                'id' => 'agua', 'title' => 'Agua', 'description' => 'Información de agua.', 'keywords' => ['agua'],
                'summary' => ['variants' => ['Resumen.']], 'detail' => ['variants' => ['Detalle.']], 'next' => ['variants' => ['Siguiente.']],
            ]],
        ]);
    }

    public function test_extracts_social_lines_when_the_tree_has_friendly_intents(): void
    {
        $tree = [
            'greeting' => ['variants' => ['Hola.']],
            'fallback' => ['variants' => ['No.']],
            'connectors' => ['Espera.'],
            'social' => [
                'gratitude' => [
                    'description' => 'Respuesta a un agradecimiento.',
                    'examples' => ['Gracias.'],
                    'keywords' => ['gracias'],
                    'variants' => ['Con gusto.'],
                ],
            ],
            'topics' => [[
                'id' => 'agua', 'title' => 'Agua', 'keywords' => ['agua'],
                'description' => 'Información sobre agua segura.', 'examples' => ['Quiero saber sobre el agua.'],
                'summary' => ['variants' => ['Resumen.']], 'detail' => ['variants' => ['Detalle.']], 'next' => ['variants' => ['Siguiente.']],
            ]],
        ];

        $lines = (new ConversationTree)->lines((new ConversationTree)->validate($tree));

        $this->assertSame('Con gusto.', $lines['social.gratitude.0']);
    }
}
