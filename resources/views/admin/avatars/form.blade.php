@extends('admin.layout')

@section('content')
@php($studio = config('avatar.audio_role') === 'studio')
@php($voiceMode = old('voice_mode', $avatar->voice_mode ?? 'synthetic'))
<div class="page-title"><div><span class="eyebrow">{{ $avatar->exists ? 'EDITAR' : 'NUEVO' }} AVATAR</span><h1>{{ $avatar->exists ? $avatar->name : 'Crea un avatar' }}</h1></div><a class="text-link" href="{{ route('admin.avatars.index') }}">← Volver</a></div>
<form class="card form-grid" method="POST" enctype="multipart/form-data" action="{{ $avatar->exists ? route('admin.avatars.update', $avatar) : route('admin.avatars.store') }}">
    @csrf @if($avatar->exists) @method('PUT') @endif
    <label>Nombre interno<input name="name" value="{{ old('name', $avatar->name) }}" required><small>Solo para administrar el avatar.</small></label>
    <label>Slug para el subdominio<input name="slug" pattern="[a-z0-9-]{2,80}" value="{{ old('slug', $avatar->slug) }}" required><small>Ejemplo: juanito → juanito.ia.tinq.pe. El DNS y HTTPS se configuran una vez en el VPS; este campo por sí solo no crea un subdominio.</small></label>
    <label>Título público<input name="public_title" value="{{ old('public_title', $avatar->public_title) }}" required></label>
    @if($studio)
        <section class="voice-setup" aria-labelledby="voiceSetupTitle">
            <div><h2 id="voiceSetupTitle">Voz para el estudio local</h2><p>Elige cómo se generarán los MP3. Las muestras privadas se cargan después, en el Estudio de audio.</p></div>
            <label>Tipo de voz<select name="voice_mode" id="voiceMode"><option value="synthetic" @selected($voiceMode === 'synthetic')>Sintética instalada</option><option value="cloned" @selected($voiceMode === 'cloned')>Clonada con mis muestras</option></select></label>
            <section data-voice-profile @if($voiceMode === 'cloned') hidden @endif><label>Perfil sintético<select name="voice_profile" data-voice-profile-select @disabled($voiceMode === 'cloned')><option value="anita" @selected(old('voice_profile', $avatar->voice_profile) === 'anita')>Anita</option></select></label><p class="field-hint">Una voz ya disponible en Voicebox local.</p></section>
            <section data-voice-sample-note @if($voiceMode !== 'cloned') hidden @endif><p class="field-hint">Luego carga de 1 a 3 clips del mismo locutor, que sumen 20 a 30 segundos de español peruano limpio. Permanecen solo en esta Mac y podrás oír cinco pruebas antes del lote.</p></section>
        </section>
    @else
        <section class="voice-setup"><h2>Entrega de audio</h2><p>Este VPS no guarda muestras ni genera voces. Recibe MP3, visemas y el árbol validados desde el Estudio local.</p></section>
    @endif
    <label>Archivo Rive (.riv)<input type="file" name="rive" accept=".riv"><small>Opcional. Debe incluir Avatar, AvatarStateMachine, ViewModel1 y viseme.</small></label>
    <label>Fondo de la llamada<input type="file" name="background" accept="image/jpeg,image/png,image/webp"><small>Opcional. JPG, PNG o WebP hasta 10 MB; se muestra detrás del avatar.</small></label>
    <button class="button" type="submit">{{ $avatar->exists ? 'Guardar cambios' : 'Crear avatar' }}</button>
</form>
@if($studio)
<script>
(() => {
    const mode = document.getElementById('voiceMode');
    const profile = document.querySelector('[data-voice-profile]');
    const note = document.querySelector('[data-voice-sample-note]');
    const profileSelect = document.querySelector('[data-voice-profile-select]');
    const update = () => { const cloned = mode.value === 'cloned'; profile.hidden = cloned; note.hidden = !cloned; profileSelect.disabled = cloned; };
    mode.addEventListener('change', update); update();
})();
</script>
@endif
@endsection
