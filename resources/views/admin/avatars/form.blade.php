@extends('admin.layout')

@section('content')
@php($voiceMode = old('voice_mode', $avatar->voice_mode ?? 'synthetic'))
<div class="page-title"><div><span class="eyebrow">{{ $avatar->exists ? 'EDITAR' : 'NUEVO' }} AVATAR</span><h1>{{ $avatar->exists ? $avatar->name : 'Crea un avatar' }}</h1></div><a class="text-link" href="{{ route('admin.avatars.index') }}">← Volver</a></div>
<form class="card form-grid" method="POST" enctype="multipart/form-data" action="{{ $avatar->exists ? route('admin.avatars.update', $avatar) : route('admin.avatars.store') }}">
    @csrf @if($avatar->exists) @method('PUT') @endif
    <label>Nombre interno<input name="name" value="{{ old('name', $avatar->name) }}" required></label>
    <label>Slug para el subdominio<input name="slug" pattern="[a-z0-9-]{2,80}" value="{{ old('slug', $avatar->slug) }}" required><small>Ejemplo: anita-ica → anita-ica.ia.tinq.pe</small></label>
    <label>Título público<input name="public_title" value="{{ old('public_title', $avatar->public_title) }}" required></label>
    <section class="voice-setup" aria-labelledby="voiceSetupTitle">
        <div>
            <h2 id="voiceSetupTitle">Voz del avatar</h2>
            <p>Elige una sola alternativa. La voz se usa únicamente cuando generes los MP3 de una versión conversacional.</p>
        </div>
        <label>Tipo de voz<select name="voice_mode" id="voiceMode"><option value="synthetic" @selected($voiceMode === 'synthetic')>Voz sintética disponible</option><option value="cloned" @selected($voiceMode === 'cloned')>Voz clonada aprobada</option></select></label>
        <section data-voice-profile @if($voiceMode === 'cloned') hidden @endif>
            <label>Voz sintética disponible<select name="voice_profile" data-voice-profile-select @disabled($voiceMode === 'cloned')><option value="anita" @selected(old('voice_profile', $avatar->voice_profile) === 'anita')>Anita</option></select></label>
            <p class="field-hint">Esta opción utiliza una voz ya instalada en Voicebox. Hoy el único perfil sintético disponible es Anita.</p>
        </section>
        <section data-voice-sample @if($voiceMode !== 'cloned') hidden @endif>
            <label>Muestra privada para clonar<input type="file" name="voice_sample" data-voice-sample-input accept="audio/mpeg,audio/wav,audio/x-wav,audio/mp4,audio/x-m4a" @disabled($voiceMode !== 'cloned')></label>
            <p class="field-hint">Recomendado: 3 a 5 minutos de una sola voz, limpia, sin música ni otras personas. Solo carga muestras con consentimiento y derechos verificados; nunca se publicarán.</p>
            @if($avatar->voice_sample_path)
                <p class="field-hint">Este avatar ya conserva una muestra privada. Sube un archivo solo si deseas reemplazarla.</p>
            @endif
            <div class="voice-sample-preview" data-voice-sample-preview hidden>
                <strong>Escucha la muestra seleccionada</strong>
                <audio controls preload="metadata" data-voice-sample-audio></audio>
                <small>Esta vista previa reproduce el archivo original en tu navegador; no es todavía la voz clonada.</small>
            </div>
        </section>
    </section>
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
        const profileSelect = document.querySelector('[data-voice-profile-select]');
        const sampleInput = document.querySelector('[data-voice-sample-input]');
        const preview = document.querySelector('[data-voice-sample-preview]');
        const previewAudio = document.querySelector('[data-voice-sample-audio]');
        let previewUrl = null;

        const update = () => {
            const cloned = mode.value === 'cloned';
            profile.hidden = cloned;
            sample.hidden = !cloned;
            profileSelect.disabled = cloned;
            sampleInput.disabled = !cloned;

            if (!cloned) {
                preview.hidden = true;
                previewAudio.removeAttribute('src');
                previewAudio.load();
                if (previewUrl) {
                    URL.revokeObjectURL(previewUrl);
                    previewUrl = null;
                }
            }
        };
        const updatePreview = () => {
            const file = sampleInput.files[0];
            if (previewUrl) {
                URL.revokeObjectURL(previewUrl);
                previewUrl = null;
            }
            if (!file) {
                preview.hidden = true;
                previewAudio.removeAttribute('src');
                previewAudio.load();

                return;
            }

            previewUrl = URL.createObjectURL(file);
            previewAudio.src = previewUrl;
            preview.hidden = false;
        };

        mode.addEventListener('change', update);
        sampleInput.addEventListener('change', updatePreview);
        update();
    })();
</script>
@endsection
