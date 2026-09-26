<?php

namespace Database\Factories;

use App\Models\Avatar;
use App\Models\AvatarVoiceSample;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AvatarVoiceSample>
 */
class AvatarVoiceSampleFactory extends Factory
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
            'path' => 'avatars/demo/voice-samples/sample.wav',
            'original_name' => 'sample.wav',
            'duration_ms' => 10_000,
            'locale' => 'es-PE',
            'sha256' => hash('sha256', fake()->uuid()),
        ];
    }
}
