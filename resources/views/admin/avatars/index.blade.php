@extends('admin.layout')

@section('content')
@php($studio = config('avatar.audio_role') === 'studio')
<div class="page-title"><div><span class="eyebrow">{{ $studio ? 'ESTUDIO LOCAL' : 'PLATAFORMA MULTI-AVATAR' }}</span><h1>{{ $studio ? 'Proyectos de audio' : 'Avatares publicados' }}</h1><p>{{ $studio ? 'Selecciona un avatar del VPS para preparar aquí su JSON, voz y MP3.' : 'Cada avatar vive en su propio subdominio y usa respuestas aprobadas.' }}</p></div><a class="button" href="{{ $studio ? route('admin.audio-studio.index') : route('admin.avatars.create') }}">{{ $studio ? 'Abrir Estudio de audio' : 'Crear avatar' }}</a></div>
<div class="avatar-grid">
@forelse ($avatars as $avatar)
    <article class="card avatar-card-admin"><span class="status {{ $avatar->status }}">{{ $studio ? 'Proyecto local' : $avatar->status }}</span><h2>{{ $avatar->name }}</h2><p>{{ $avatar->public_title }}</p><code>{{ $studio ? 'VPS: '.$avatar->delivery_slug : $avatar->slug.'.'.config('avatar.public_domain_suffix') }}</code><small>{{ $avatar->conversation_versions_count }} versión(es)</small><a class="text-link" href="{{ $studio ? route('admin.audio-studio.show', $avatar) : route('admin.avatars.show', $avatar) }}">{{ $studio ? 'Abrir Estudio →' : 'Administrar →' }}</a></article>
@empty
    <div class="empty">{{ $studio ? 'Aún no has abierto un proyecto. Usa “Abrir Estudio de audio” y elige un avatar del VPS.' : 'Aún no has creado avatares.' }}</div>
@endforelse
</div>
@endsection
