<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAvatarVoiceSamplesRequest;
use App\Models\Avatar;
use App\Models\ConversationVersion;
use App\Services\ConversationTree;
use App\Services\ConversationVersionBundle;
use App\Services\RemoteDeliveryClient;
use App\Services\StaticAudioPublisher;
use App\Services\VoiceSampleReference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;

class AudioStudioController extends Controller
{
    public function show(Avatar $avatar, RemoteDeliveryClient $delivery): View
    {
        $this->ensureStudio();
        $destinations = [];
        $syncError = null;

        try {
            $destinations = $delivery->avatars();
        } catch (RuntimeException $exception) {
            $syncError = $exception->getMessage();
        }

        return view('admin.audio-studio.show', [
            'avatar' => $avatar->load(['voiceSamples', 'conversationVersions' => fn ($query) => $query->withCount('audioAssets')->latest()]),
            'destinations' => $destinations,
            'syncError' => $syncError,
        ]);
    }

    public function storeSamples(StoreAvatarVoiceSamplesRequest $request, Avatar $avatar, VoiceSampleReference $references): RedirectResponse
    {
        $this->ensureStudio();
        $references->replace($avatar, $request->file('samples'));

        return back()->with('success', 'Las muestras se guardaron como referencia privada local. Ahora prueba los cinco audios antes del lote completo.');
    }

    public function preview(Avatar $avatar, ConversationVersion $version, StaticAudioPublisher $publisher): Response
    {
        $this->ensureStudio();
        abort_unless($version->avatar_id === $avatar->id, 404);
        $lines = app(ConversationTree::class)->lines($version->tree);
        $key = request()->string('asset_key')->toString();
        abort_unless(isset($lines[$key]), 422);

        $speech = $publisher->synthesize($avatar, $lines[$key]);

        return response($speech['bytes'], 200, [
            'Content-Type' => 'audio/mpeg',
            'Content-Length' => (string) strlen($speech['bytes']),
            'Cache-Control' => 'no-store',
        ]);
    }

    public function approve(Avatar $avatar, ConversationVersion $version): RedirectResponse
    {
        $this->ensureStudio();
        abort_unless($version->avatar_id === $avatar->id, 404);
        $version->update(['preview_approved_at' => now()]);

        return back()->with('success', 'Pruebas de voz aprobadas. Ya puedes generar el lote completo.');
    }

    public function upload(Avatar $avatar, ConversationVersion $version, ConversationVersionBundle $bundle, RemoteDeliveryClient $delivery): RedirectResponse
    {
        $this->ensureStudio();
        abort_unless($version->avatar_id === $avatar->id, 404);
        if ($version->status !== 'ready_to_upload') {
            return back()->withErrors(['upload' => 'Esta versión debe tener todos sus MP3 listos antes de enviarla al VPS.']);
        }
        if (! $avatar->delivery_slug) {
            return back()->withErrors(['upload' => 'Selecciona primero el avatar destino del VPS.']);
        }

        $path = sprintf('avatar-sync/exports/%s-%d.zip', $avatar->slug, $version->id);
        Storage::disk('local')->makeDirectory('avatar-sync/exports');

        try {
            $archivePath = Storage::disk('local')->path($path);
            $bundle->export($version, $archivePath, $avatar->delivery_slug);
            $delivery->upload($archivePath);
            $version->update(['status' => 'uploaded']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['upload' => $exception->getMessage()]);
        } finally {
            Storage::disk('local')->delete($path);
        }

        return back()->with('success', 'Paquete verificado y publicado en el VPS.');
    }

    public function destination(Avatar $avatar): RedirectResponse
    {
        $this->ensureStudio();
        $data = request()->validate(['delivery_slug' => ['nullable', 'string', 'regex:/^[a-z0-9-]{2,80}$/']]);
        $avatar->update(['delivery_slug' => $data['delivery_slug'] ?: null]);

        return back()->with('success', 'Destino del VPS actualizado.');
    }

    private function ensureStudio(): void
    {
        abort_unless(config('avatar.audio_role') === 'studio', 404);
    }
}
