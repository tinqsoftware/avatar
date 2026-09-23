<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAvatarRequest;
use App\Http\Requests\UpdateAvatarRequest;
use App\Models\Avatar;
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

        return redirect()->route('admin.avatars.show', $avatar)->with('success', 'Avatar creado. Ahora sube y publica su árbol conversacional.');
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
        $attributes = $request->safe()->except(['rive', 'status']);
        $attributes['status'] = $avatar?->status ?? 'draft';

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

        return $attributes;
    }
}
