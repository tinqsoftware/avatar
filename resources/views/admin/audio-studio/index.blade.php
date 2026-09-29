@extends('admin.layout')

@section('content')
<div class="page-title">
    <div>
        <span class="eyebrow">ESTUDIO LOCAL</span>
        <h1>Elige un avatar del VPS</h1>
        <p>El avatar público vive en la nube. Aquí solo se crea su proyecto privado de JSON, voz y MP3.</p>
    </div>
    <a class="text-link" href="{{ route('admin.avatars.index') }}">Ver proyectos locales</a>
</div>

@if ($syncError)
    <div class="flash error">{{ $syncError }}</div>
@endif

<div class="avatar-grid">
@forelse ($remoteAvatars as $remoteAvatar)
    @php($project = $localProjects->get($remoteAvatar['slug']))
    <article class="card avatar-card-admin">
        <span class="status {{ $remoteAvatar['status'] }}">VPS · {{ $remoteAvatar['status'] }}</span>
        <h2>{{ $remoteAvatar['name'] }}</h2>
        <p>{{ $remoteAvatar['public_title'] }}</p>
        <code>{{ $remoteAvatar['slug'] }}</code>
        @if ($project)
            <a class="button small" href="{{ route('admin.audio-studio.show', $project) }}">Abrir proyecto de audio</a>
        @else
            <form method="POST" action="{{ route('admin.audio-studio.import') }}">
                @csrf
                <input type="hidden" name="slug" value="{{ $remoteAvatar['slug'] }}">
                <button class="button small" type="submit">Crear proyecto de audio local</button>
            </form>
        @endif
    </article>
@empty
    <div class="empty">No se encontraron avatares en el VPS.</div>
@endforelse
</div>
@endsection
