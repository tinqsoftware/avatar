<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAvatarVoiceSamplesRequest;
use App\Jobs\GenerateStaticAudio;
use App\LocalAudioWorker;
use App\Models\Avatar;
use App\Models\ConversationVersion;
use App\Services\ConversationTree;
use App\Services\ConversationVersionBundle;
use App\Services\RemoteDeliveryClient;
use App\Services\StaticAudioPublisher;
use App\Services\VisemeAuditService;
use App\Services\VoiceSampleReference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;

class AudioStudioController extends Controller
{
    public function index(RemoteDeliveryClient $delivery): View
    {
        $this->ensureStudio();

        $remoteAvatars = [];
        $syncError = null;

        try {
            $remoteAvatars = $delivery->avatars();
        } catch (RuntimeException $exception) {
            $syncError = $exception->getMessage();
        }

        return view('admin.audio-studio.index', [
            'remoteAvatars' => $remoteAvatars,
            'localProjects' => Avatar::query()
                ->whereNotNull('delivery_slug')
                ->get()
                ->keyBy('delivery_slug'),
            'syncError' => $syncError,
        ]);
    }

    public function import(Request $request, RemoteDeliveryClient $delivery): RedirectResponse
    {
        $this->ensureStudio();
        $data = $request->validate(['slug' => ['required', 'string', 'regex:/^[a-z0-9-]{2,80}$/']]);

        try {
            $remoteAvatar = collect($delivery->avatars())->firstWhere('slug', $data['slug']);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['sync' => $exception->getMessage()]);
        }

        abort_unless(is_array($remoteAvatar), 404);

        $project = Avatar::query()->where('delivery_slug', $remoteAvatar['slug'])->first()
            ?? Avatar::query()->where('slug', $remoteAvatar['slug'])->first()
            ?? new Avatar([
                'slug' => $remoteAvatar['slug'],
                'voice_mode' => 'cloned',
                'voice_profile' => 'cloned',
                'voice_locale' => 'es-PE',
            ]);

        $project->fill([
            'name' => $remoteAvatar['name'],
            'public_title' => $remoteAvatar['public_title'],
            'delivery_slug' => $remoteAvatar['slug'],
            'status' => 'draft',
        ]);
        $project->save();

        return redirect()
            ->route('admin.audio-studio.show', $project)
            ->with('success', 'Proyecto local vinculado al avatar del VPS. Carga aquí el JSON, las muestras y las pruebas de voz.');
    }

    public function show(Avatar $avatar, RemoteDeliveryClient $delivery, VisemeAuditService $visemeAudits): View
    {
        $this->ensureStudio();
        $destinations = [];
        $syncError = null;

        try {
            $destinations = $delivery->avatars();
        } catch (RuntimeException $exception) {
            $syncError = $exception->getMessage();
        }

        $avatar->load([
            'voiceSamples',
            'conversationVersions' => fn ($query) => $query
                ->with(['audioAssets' => fn ($assets) => $assets->orderBy('asset_key')])
                ->withCount([
                    'audioAssets',
                    'audioAssets as ready_audio_assets_count' => fn ($assets) => $assets->where('status', 'ready'),
                    'audioAssets as generating_audio_assets_count' => fn ($assets) => $assets->where('status', 'generating'),
                    'audioAssets as failed_audio_assets_count' => fn ($assets) => $assets->where('status', 'failed'),
                ])
                ->latest(),
        ]);

        return view('admin.audio-studio.show', [
            'avatar' => $avatar,
            'riveUrl' => $avatar->rive_path ? '/storage/'.ltrim($avatar->rive_path, '/') : null,
            'visemeAudits' => $avatar->conversationVersions->mapWithKeys(fn (ConversationVersion $version): array => [
                $version->id => $version->audioAssets
                    ->mapWithKeys(fn ($asset): array => [$asset->id => $visemeAudits->audit($asset)])
                    ->all(),
            ])->all(),
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

    public function preview(Avatar $avatar, ConversationVersion $version, StaticAudioPublisher $publisher): Response|JsonResponse
    {
        $this->ensureStudio();
        abort_unless($version->avatar_id === $avatar->id, 404);
        $lines = app(ConversationTree::class)->lines($version->tree);
        $key = request()->string('asset_key')->toString();
        abort_unless(isset($lines[$key]), 422);

        try {
            $speech = $publisher->synthesize($avatar, $lines[$key]);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

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

    public function updateLine(Avatar $avatar, ConversationVersion $version, ConversationTree $conversationTree, LocalAudioWorker $worker): RedirectResponse
    {
        $this->ensureStudio();
        abort_unless($version->avatar_id === $avatar->id, 404);

        $data = request()->validate([
            'asset_key' => ['required', 'string', 'max:180'],
            'text' => ['required', 'string', 'max:900'],
        ]);

        try {
            $updatedTree = $conversationTree->replaceLine($version->tree, $data['asset_key'], $data['text']);
        } catch (\InvalidArgumentException $exception) {
            return back()->withErrors(['text' => $exception->getMessage()]);
        }

        $line = $conversationTree->lines($updatedTree)[$data['asset_key']] ?? null;
        abort_unless(is_string($line), 422);

        DB::transaction(function () use ($avatar, $version, $updatedTree, $data, $line): void {
            $asset = $version->audioAssets()->where('asset_key', $data['asset_key'])->firstOrFail();
            $asset->update([
                'text' => $line,
                'path' => null,
                'duration_ms' => null,
                'visemes' => null,
                'status' => 'pending',
                'error' => null,
                'synced_at' => null,
            ]);
            $version->update([
                'tree' => $updatedTree,
                'status' => 'generating',
                'published_at' => null,
            ]);
            $avatar->update(['status' => 'generating']);
            GenerateStaticAudio::dispatch($asset->id)->afterCommit();
        });

        try {
            $worker->start();
        } catch (RuntimeException $exception) {
            return back()->withErrors(['text' => "La frase quedó pendiente, pero no se pudo iniciar el worker local: {$exception->getMessage()}"]);
        }

        return back()->with('success', 'La frase fue actualizada. Solo ese MP3 se regenerará y se enviará al VPS en el siguiente bloque.');
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

    public function storeRive(Request $request, Avatar $avatar): RedirectResponse
    {
        $this->ensureStudio();
        $request->validate([
            'rive' => ['required', 'file', 'extensions:riv', 'max:20480'],
        ]);

        $file = $request->file('rive');
        if (! $file || ! str_starts_with($file->get(), 'RIVE')) {
            return back()->withErrors(['rive' => 'El archivo no es un Rive válido.']);
        }

        $path = $file->store("avatars/{$avatar->slug}", 'public');
        if (! $path) {
            return back()->withErrors(['rive' => 'No se pudo guardar el archivo Rive local.']);
        }

        $previousPath = $avatar->rive_path;
        $avatar->update(['rive_path' => $path]);

        if ($previousPath && str_starts_with($previousPath, 'avatars/')) {
            Storage::disk('public')->delete($previousPath);
        }

        return back()->with('success', 'Archivo Rive guardado solo en local. Ya puedes revisarlo junto con los visemas del MP3.');
    }

    private function ensureStudio(): void
    {
        abort_unless(config('avatar.audio_role') === 'studio', 404);
    }
}
