<?php

namespace Tests\Feature;

use App\Models\Avatar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AudioStudioCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_studio_lists_remote_avatars_and_imports_one_local_audio_project(): void
    {
        config(['avatar.audio_role' => 'studio']);
        Http::fake([
            '*' => Http::response(['data' => [[
                'id' => 2,
                'slug' => 'juanito-ica',
                'name' => 'Juanito Ica',
                'public_title' => 'Juanito · Plan de Gobierno Regional de Ica 2027–2030',
                'status' => 'draft',
            ]]]),
        ]);
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get('/admin/audio-studio')
            ->assertOk()
            ->assertSee('Juanito Ica')
            ->assertSee('Crear proyecto de audio local');

        $this->actingAs($admin)
            ->post('/admin/audio-studio/import', ['slug' => 'juanito-ica'])
            ->assertRedirect();

        $this->assertDatabaseHas('avatars', [
            'name' => 'Juanito Ica',
            'slug' => 'juanito-ica',
            'delivery_slug' => 'juanito-ica',
        ]);

        $this->actingAs($admin)->post('/admin/audio-studio/import', ['slug' => 'juanito-ica']);

        $this->assertSame(1, Avatar::count());
    }
}
