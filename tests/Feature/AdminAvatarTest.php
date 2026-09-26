<?php

namespace Tests\Feature;

use App\Models\Avatar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminAvatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_redirects_to_admin_login(): void
    {
        $this->get('/admin/avatars')->assertRedirect(route('admin.login'));
    }

    public function test_admin_creates_an_avatar_with_a_safe_slug(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post('/admin/avatars', [
            'name' => 'Sofía',
            'slug' => 'sofia-ica',
            'public_title' => 'Demostración Ica',
            'voice_mode' => 'synthetic',
            'voice_profile' => 'anita',
        ])->assertRedirect();

        $this->assertDatabaseHas('avatars', ['slug' => 'sofia-ica', 'status' => 'draft']);
    }

    public function test_non_admin_cannot_create_an_avatar(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->post('/admin/avatars', [
            'name' => 'Sofía',
            'slug' => 'sofia-ica',
            'public_title' => 'Demostración Ica',
            'voice_mode' => 'synthetic',
            'voice_profile' => 'anita',
        ])->assertRedirect(route('admin.login'));

        $this->assertDatabaseMissing('avatars', ['slug' => 'sofia-ica']);
    }

    public function test_admin_can_store_a_public_background_for_an_avatar(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post('/admin/avatars', [
            'name' => 'Sofía',
            'slug' => 'sofia-ica',
            'public_title' => 'Demostración Ica',
            'voice_mode' => 'synthetic',
            'voice_profile' => 'anita',
            'background' => UploadedFile::fake()->image('fondo.jpg', 1200, 800),
        ])->assertRedirect();

        $avatar = Avatar::where('slug', 'sofia-ica')->firstOrFail();

        $this->assertNotNull($avatar->background_path);
        Storage::disk('public')->assertExists($avatar->background_path);
    }

    public function test_admin_cannot_upload_a_non_image_avatar_background(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post('/admin/avatars', [
            'name' => 'Sofía',
            'slug' => 'sofia-ica',
            'public_title' => 'Demostración Ica',
            'voice_mode' => 'synthetic',
            'voice_profile' => 'anita',
            'background' => UploadedFile::fake()->create('fondo.pdf', 25, 'application/pdf'),
        ])->assertSessionHasErrors('background');
    }

    public function test_admin_replaces_the_previous_avatar_background(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('avatars/sofia-ica/backgrounds/old.jpg', 'old background');
        $admin = User::factory()->create(['is_admin' => true]);
        $avatar = Avatar::factory()->create([
            'slug' => 'sofia-ica',
            'background_path' => 'avatars/sofia-ica/backgrounds/old.jpg',
        ]);

        $this->actingAs($admin)->put("/admin/avatars/{$avatar->id}", [
            'name' => $avatar->name,
            'slug' => $avatar->slug,
            'public_title' => $avatar->public_title,
            'status' => $avatar->status,
            'voice_mode' => 'synthetic',
            'voice_profile' => 'anita',
            'background' => UploadedFile::fake()->image('nuevo.jpg', 1200, 800),
        ])->assertRedirect();

        $avatar->refresh();

        $this->assertNotSame('avatars/sofia-ica/backgrounds/old.jpg', $avatar->background_path);
        Storage::disk('public')->assertMissing('avatars/sofia-ica/backgrounds/old.jpg');
        Storage::disk('public')->assertExists($avatar->background_path);
    }
}
