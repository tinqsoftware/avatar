@extends('admin.layout')

@section('content')
<div class="page-title"><div><span class="eyebrow">{{ $avatar->exists ? 'EDITAR' : 'NUEVO' }} AVATAR</span><h1>{{ $avatar->exists ? $avatar->name : 'Crea un avatar' }}</h1></div><a class="text-link" href="{{ route('admin.avatars.index') }}">← Volver</a></div>
<form class="card form-grid" method="POST" enctype="multipart/form-data" action="{{ $avatar->exists ? route('admin.avatars.update', $avatar) : route('admin.avatars.store') }}">
    @csrf @if($avatar->exists) @method('PUT') @endif
    <label>Nombre interno<input name="name" value="{{ old('name', $avatar->name) }}" required></label>
    <label>Slug para el subdominio<input name="slug" pattern="[a-z0-9-]{2,80}" value="{{ old('slug', $avatar->slug) }}" required><small>Ejemplo: anita-ica → anita-ica.ia.tinq.pe</small></label>
    <label>Título público<input name="public_title" value="{{ old('public_title', $avatar->public_title) }}" required></label>
    <label>Modo de voz<select name="voice_mode" id="voiceMode"><option value="synthetic" @selected(old('voice_mode', $avatar->voice_mode ?? 'synthetic') === 'synthetic')>Sintética</option><option value="cloned" @selected(old('voice_mode', $avatar->voice_mode) === 'cloned')>Clonada aprobada</option></select><small>La muestra de voz clonada pertenece exclusivamente a este avatar.</small></label>
    <label data-voice-profile>Perfil sintético<select name="voice_profile"><option value="anita" @selected(old('voice_profile', $avatar->voice_profile) === 'anita')>Anita</option></select></label>
    <label data-voice-sample>Muestra privada de voz<input type="file" name="voice_sample" accept="audio/mpeg,audio/wav,audio/x-wav,audio/mp4,audio/x-m4a"><small>Solo carga muestras con consentimiento y derechos ya verificados. Nunca se publicará esta muestra.</small></label>
    <label>Archivo Rive (.riv)<input type="file" name="rive" accept=".riv"><small>Debe incluir Avatar, AvatarStateMachine, ViewModel1 y viseme.</small></label>
    <label>Fondo de la llamada<input type="file" name="background" accept="image/jpeg,image/png,image/webp"><small>JPG, PNG o WebP, hasta 10 MB y 4096 × 4096 px. Se mostrará detrás del avatar.</small></label>
    @if($avatar->background_path)<p class="field-hint">Este avatar ya tiene un fondo personalizado. Sube otro archivo para reemplazarlo.</p>@endif
    @if($avatar->exists)<p class="field-hint">El estado cambia automáticamente al publicar una versión y completar todos sus audios estáticos.</p>@endif
    <button class="button" type="submit">{{ $avatar->exists ? 'Guardar cambios' : 'Crear avatar' }}</button>
</form>
<script>
    (() => {
        const mode = document.getElementById('voiceMode');
        const profile = document.querySelector('[data-voice-profile]');
        const sample = document.querySelector('[data-voice-sample]');
        const update = () => {
            const cloned = mode.value === 'cloned';
            profile.hidden = cloned;
            sample.hidden = !cloned;
        };
        mode.addEventListener('change', update);
        update();
    })();
</script>
@endsection
