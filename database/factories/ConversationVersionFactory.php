<?php

namespace Database\Factories;

use App\Models\Avatar;
use App\Models\ConversationVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConversationVersion>
 */
class ConversationVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'avatar_id' => Avatar::factory(),
            'label' => 'Versión demo',
            'tree' => [
                'greeting' => ['variants' => ['Hola.']],
                'fallback' => ['variants' => ['No encontré ese tema.']],
                'connectors' => ['Un momento, por favor.'],
                'topics' => [],
            ],
            'status' => 'draft',
        ];
    }
}
