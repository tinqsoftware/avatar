<?php

namespace Tests\Feature;

use App\Models\Avatar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AvatarVoiceProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_stores_a_cloned_voice_sample_on_the_private_disk(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post('/admin/avatars', [
            'name' => 'Sofía',
            'slug' => 'sofia-ica',
            'public_title' => 'Demostración Ica',
            'voice_mode' => 'cloned',
            'voice_sample' => UploadedFile::fake()->create('sofia.mp3', 100, 'audio/mpeg'),
        ])->assertRedirect();

        $avatar = Avatar::where('slug', 'sofia-ica')->firstOrFail();
        $this->assertSame('cloned', $avatar->voice_mode);
        $this->assertSame('cloned', $avatar->voice_profile);
        $this->assertNotNull($avatar->voice_sample_path);
        Storage::disk('local')->assertExists($avatar->voice_sample_path);
        $this->assertStringNotContainsString('/storage/', $avatar->voice_sample_path);
    }

    public function test_cloned_voice_requires_a_private_sample(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post('/admin/avatars', [
            'name' => 'Sofía',
            'slug' => 'sofia-ica',
            'public_title' => 'Demostración Ica',
            'voice_mode' => 'cloned',
        ])->assertSessionHasErrors('voice_sample');
    }
}
