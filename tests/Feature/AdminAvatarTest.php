<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            'voice_profile' => 'anita',
        ])->assertRedirect(route('admin.login'));

        $this->assertDatabaseMissing('avatars', ['slug' => 'sofia-ica']);
    }
}
