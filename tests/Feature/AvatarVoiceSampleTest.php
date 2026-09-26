<?php

namespace Tests\Feature;

use App\Models\Avatar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AvatarVoiceSampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_studio_accepts_at_most_three_private_voice_clips(): void
    {
        config(['avatar.audio_role' => 'studio']);
        $admin = User::factory()->create(['is_admin' => true]);
        $avatar = Avatar::factory()->create(['voice_mode' => 'cloned', 'voice_profile' => 'cloned']);

        $this->actingAs($admin)->post("/admin/avatars/{$avatar->id}/audio-studio/samples", [
            'samples' => [
                UploadedFile::fake()->create('one.mp3', 100, 'audio/mpeg'),
                UploadedFile::fake()->create('two.mp3', 100, 'audio/mpeg'),
                UploadedFile::fake()->create('three.mp3', 100, 'audio/mpeg'),
                UploadedFile::fake()->create('four.mp3', 100, 'audio/mpeg'),
            ],
        ])->assertSessionHasErrors('samples');
    }
}
