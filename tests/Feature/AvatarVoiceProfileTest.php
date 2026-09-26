<?php

namespace Tests\Feature;

use App\Models\Avatar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvatarVoiceProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_studio_creates_a_cloned_avatar_without_uploading_a_sample_in_the_avatar_form(): void
    {
        config(['avatar.audio_role' => 'studio']);
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post('/admin/avatars', [
            'name' => 'Sofía',
            'slug' => 'sofia-ica',
            'public_title' => 'Demostración Ica',
            'voice_mode' => 'cloned',
        ])->assertRedirect();

        $avatar = Avatar::where('slug', 'sofia-ica')->firstOrFail();
        $this->assertSame('cloned', $avatar->voice_mode);
        $this->assertSame('cloned', $avatar->voice_profile);
        $this->assertNull($avatar->voice_sample_path);
    }

    public function test_delivery_form_hides_audio_generation_controls(): void
    {
        config(['avatar.audio_role' => 'delivery']);
        $admin = User::factory()->create(['is_admin' => true]);
        $avatar = Avatar::factory()->create();

        $this->actingAs($admin)->get("/admin/avatars/{$avatar->id}")
            ->assertOk()
            ->assertSee('Audios recibidos')
            ->assertDontSee('Generar y publicar')
            ->assertDontSee('Subir árbol JSON');
    }
}
