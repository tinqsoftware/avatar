<?php

namespace Database\Factories;

use App\Models\Avatar;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Avatar>
 */
class AvatarFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'slug' => fake()->unique()->slug(2),
            'delivery_slug' => null,
            'public_title' => fake()->sentence(3),
            'voice_mode' => 'synthetic',
            'voice_profile' => 'anita',
            'voice_locale' => 'es-PE',
            'voice_sample_path' => null,
            'rive_path' => 'assets/avatar/anita.riv',
            'status' => 'draft',
        ];
    }
}
