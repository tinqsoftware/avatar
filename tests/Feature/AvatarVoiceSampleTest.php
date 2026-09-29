<?php

namespace Tests\Feature;

use App\Models\Avatar;
use App\Models\User;
use App\Services\VoiceSampleReference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
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

    public function test_studio_does_not_restrict_the_duration_of_private_voice_clips(): void
    {
        Storage::fake('local');
        Process::fake([
            '*' => Process::sequence()
                ->push(Process::result('3600'))
                ->push(Process::result()),
        ]);
        $avatar = Avatar::factory()->create(['voice_mode' => 'cloned', 'voice_profile' => 'cloned']);
        $reference = app(VoiceSampleReference::class);

        $reference->replace($avatar, [UploadedFile::fake()->create('voz-larga.mp3', 100, 'audio/mpeg')]);

        $this->assertDatabaseHas('avatar_voice_samples', [
            'avatar_id' => $avatar->id,
            'original_name' => 'voz-larga.mp3',
            'duration_ms' => 3_600_000,
        ]);
    }
}
