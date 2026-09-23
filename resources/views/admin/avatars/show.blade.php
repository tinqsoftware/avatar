@extends('admin.layout')

@section('content')
<div class="page-title"><div><span class="eyebrow">{{ strtoupper($avatar->status) }}</span><h1>{{ $avatar->name }}</h1><p>{{ $avatar->public_title }}</p></div><div class="actions"><a class="text-link" href="{{ route('admin.avatars.edit', $avatar) }}">Editar avatar</a><a class="button" href="{{ route('admin.versions.create', $avatar) }}">Subir árbol JSON</a></div></div>
<section class="card public-address"><strong>Dirección pública</strong><code>https://{{ $avatar->slug }}.{{ config('avatar.public_domain_suffix') }}</code><p>Disponible cuando exista una versión publicada con todos sus MP3 listos.</p></section>
<section class="section-heading"><h2>Versiones conversacionales</h2><p>El modelo solo selecciona estas respuestas; no genera propuestas nuevas.</p></section>
<div class="version-list">
@forelse($avatar->conversationVersions as $version)
    <article class="card version-row"><div><strong>{{ $version->label }}</strong><span class="status {{ $version->status }}">{{ $version->status }}</span><small>{{ $version->audio_assets_count }} audios definidos · {{ optional($version->published_at)->format('d/m/Y H:i') }}</small></div><div class="actions"><a class="text-link" href="{{ route('admin.versions.show', [$avatar, $version]) }}">Ver</a>@if($version->status !== 'generating')<form method="POST" action="{{ route('admin.versions.publish', [$avatar, $version]) }}">@csrf<button class="button small" type="submit">Generar y publicar</button></form>@endif</div></article>
@empty <div class="empty">Sube un árbol JSON para crear la primera versión.</div>
@endforelse
</div>
@endsection
