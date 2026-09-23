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
                'summary' => ['variants' => ['Resumen.']], 'detail' => ['variants' => ['Detalle.']], 'next' => ['variants' => ['Siguiente.']],
            ]],
        ];

        $lines = (new ConversationTree)->lines((new ConversationTree)->validate($tree));

        $this->assertSame('Resumen.', $lines['topic.agua.summary.0']);
        $this->assertCount(6, $lines);
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
}
