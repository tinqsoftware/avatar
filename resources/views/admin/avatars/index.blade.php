@extends('admin.layout')

@section('content')
<div class="page-title"><div><span class="eyebrow">PLATAFORMA MULTI-AVATAR</span><h1>Avatares publicados</h1><p>Cada avatar vive en su propio subdominio y usa respuestas aprobadas.</p></div><a class="button" href="{{ route('admin.avatars.create') }}">Crear avatar</a></div>
<div class="avatar-grid">
@forelse ($avatars as $avatar)
    <article class="card avatar-card-admin"><span class="status {{ $avatar->status }}">{{ $avatar->status }}</span><h2>{{ $avatar->name }}</h2><p>{{ $avatar->public_title }}</p><code>{{ $avatar->slug }}.{{ config('avatar.public_domain_suffix') }}</code><small>{{ $avatar->conversation_versions_count }} versión(es)</small><a class="text-link" href="{{ route('admin.avatars.show', $avatar) }}">Administrar →</a></article>
@empty
    <div class="empty">Aún no has creado avatares.</div>
@endforelse
</div>
@endsection
