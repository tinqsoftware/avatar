@extends('admin.layout')

@section('content')
<div class="page-title"><div><span class="eyebrow">{{ $avatar->exists ? 'EDITAR' : 'NUEVO' }} AVATAR</span><h1>{{ $avatar->exists ? $avatar->name : 'Crea un avatar' }}</h1></div><a class="text-link" href="{{ route('admin.avatars.index') }}">← Volver</a></div>
<form class="card form-grid" method="POST" enctype="multipart/form-data" action="{{ $avatar->exists ? route('admin.avatars.update', $avatar) : route('admin.avatars.store') }}">
    @csrf @if($avatar->exists) @method('PUT') @endif
    <label>Nombre interno<input name="name" value="{{ old('name', $avatar->name) }}" required></label>
    <label>Slug para el subdominio<input name="slug" pattern="[a-z0-9-]{2,80}" value="{{ old('slug', $avatar->slug) }}" required><small>Ejemplo: anita-ica → anita-ica.ia.tinq.pe</small></label>
    <label>Título público<input name="public_title" value="{{ old('public_title', $avatar->public_title) }}" required></label>
    <label>Perfil de voz<select name="voice_profile"><option value="anita" @selected(old('voice_profile', $avatar->voice_profile) === 'anita')>Anita</option></select></label>
    <label>Archivo Rive (.riv)<input type="file" name="rive" accept=".riv"><small>Debe incluir Avatar, AvatarStateMachine, ViewModel1 y viseme.</small></label>
    @if($avatar->exists)<p class="field-hint">El estado cambia automáticamente al publicar una versión y completar todos sus audios estáticos.</p>@endif
    <button class="button" type="submit">{{ $avatar->exists ? 'Guardar cambios' : 'Crear avatar' }}</button>
</form>
@endsection
