<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAvatarRequest;
use App\Http\Requests\UpdateAvatarRequest;
use App\Models\Avatar;
use App\Services\VoiceSampleReference;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AvatarController extends Controller
{
    public function index(): View
    {
        return view('admin.avatars.index', [
            'avatars' => Avatar::withCount('conversationVersions')->latest()->get(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('admin.avatars.form', ['avatar' => new Avatar]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreAvatarRequest $request): RedirectResponse
    {
        $avatar = Avatar::create($this->attributes($request));

        $message = config('avatar.audio_role') === 'studio'
            ? 'Avatar creado. Continúa en el Estudio de audio para cargar el JSON y generar localmente.'
            : 'Avatar creado. Ya puede recibir paquetes de audio desde el Estudio local.';

        return redirect()->route('admin.avatars.show', $avatar)->with('success', $message);
    }

    /**
     * Display the specified resource.
     */
    public function show(Avatar $avatar): View
    {
        return view('admin.avatars.show', [
            'avatar' => $avatar->load(['conversationVersions' => fn ($query) => $query->withCount('audioAssets')->latest()]),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Avatar $avatar): View
    {
        return view('admin.avatars.form', compact('avatar'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateAvatarRequest $request, Avatar $avatar): RedirectResponse
    {
        $avatar->update($this->attributes($request, $avatar));

        return redirect()->route('admin.avatars.show', $avatar)->with('success', 'Avatar actualizado.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Avatar $avatar): RedirectResponse
    {
        $avatar->delete();

        return redirect()->route('admin.avatars.index')->with('success', 'Avatar eliminado.');
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(StoreAvatarRequest|UpdateAvatarRequest $request, ?Avatar $avatar = null): array
    {
        $attributes = $request->safe()->except(['rive', 'background', 'status']);
        $attributes['status'] = $avatar?->status ?? 'draft';

        if (config('avatar.audio_role') !== 'studio') {
            $attributes['voice_mode'] = $avatar?->voice_mode ?? 'synthetic';
            $attributes['voice_profile'] = $avatar?->voice_profile ?? 'anita';
            $attributes['voice_locale'] = $avatar?->voice_locale ?? 'es-PE';

            return $this->storePublicFiles($request, $avatar, $attributes);
        }

        if ($attributes['voice_mode'] === 'cloned') {
            $attributes['voice_profile'] = 'cloned';
        } else {
            app(VoiceSampleReference::class)->clear($avatar);
            $attributes['voice_sample_path'] = null;
        }

        return $this->storePublicFiles($request, $avatar, $attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function storePublicFiles(StoreAvatarRequest|UpdateAvatarRequest $request, ?Avatar $avatar, array $attributes): array
    {
        if ($request->hasFile('rive')) {
            $file = $request->file('rive');
            if (! str_starts_with($file->get(), 'RIVE')) {
                abort(422, 'El archivo no es un Rive válido.');
            }
            if ($avatar?->rive_path && str_starts_with($avatar->rive_path, 'avatars/')) {
                Storage::disk('public')->delete($avatar->rive_path);
            }
            $attributes['rive_path'] = $file->store("avatars/{$request->string('slug')}", 'public');
        }

        if ($request->hasFile('background')) {
            if ($avatar?->background_path && str_starts_with($avatar->background_path, 'avatars/')) {
                Storage::disk('public')->delete($avatar->background_path);
            }

            $attributes['background_path'] = $request->file('background')->store("avatars/{$request->string('slug')}/backgrounds", 'public');
        }

        return $attributes;
    }
}
