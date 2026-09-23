<?php

namespace Database\Factories;

use App\Models\AudioAsset;
use App\Models\ConversationVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AudioAsset>
 */
class AudioAssetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_version_id' => ConversationVersion::factory(),
            'asset_key' => 'greeting.0',
            'text' => 'Hola.',
            'status' => 'pending',
        ];
    }
}
