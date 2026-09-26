<?php

namespace Tests\Unit;

use App\Services\ConversationTree;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ConversationTreeTest extends TestCase
{
    public function test_validates_the_complete_juanito_conversation_tree(): void
    {
        $tree = json_decode(
            file_get_contents(__DIR__.'/../../resources/conversation-trees/juanito-somos-peru-ica-2027-2030.json'),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        $lines = (new ConversationTree)->lines((new ConversationTree)->validate($tree));

        $this->assertCount(15, $tree['topics']);
        $this->assertCount(305, $lines);
        $this->assertSame('Salud regional', $tree['topics'][0]['title']);
        $this->assertSame('Grandes proyectos regionales', $tree['topics'][14]['title']);
    }

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
