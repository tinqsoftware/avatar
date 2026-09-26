@extends('admin.layout')

@section('content')
@php($studio = config('avatar.audio_role') === 'studio')
<div class="page-title"><div><span class="eyebrow">{{ strtoupper($avatar->status) }}</span><h1>{{ $avatar->name }}</h1><p>{{ $avatar->public_title }}</p></div><div class="actions"><a class="text-link" href="{{ route('admin.avatars.edit', $avatar) }}">Editar avatar</a>@if($studio)<a class="button" href="{{ route('admin.audio-studio.show', $avatar) }}">Abrir Estudio de audio</a>@endif</div></div>
<section class="card public-address"><strong>Dirección pública</strong><code>https://{{ $avatar->slug }}.{{ config('avatar.public_domain_suffix') }}</code><p>El subdominio se entrega cuando el DNS comodín y HTTPS del VPS están configurados. La versión pública cambia al recibir un paquete íntegro.</p></section>
@if(! $studio)
<section class="section-heading"><h2>Audios recibidos</h2><p>El VPS solo verifica y publica paquetes preparados por el Estudio local.</p></section>
@endif
<section class="section-heading"><h2>Versiones conversacionales</h2><p>{{ $studio ? 'El estudio genera localmente; el VPS nunca recibe muestras privadas.' : 'Checksums verificados antes de cualquier publicación.' }}</p></section>
<div class="version-list">
@forelse($avatar->conversationVersions as $version)
    <article class="card version-row"><div><strong>{{ $version->label }}</strong><span class="status {{ $version->status }}">{{ $version->status }}</span><small>{{ $version->audio_assets_count }} audios · {{ optional($version->published_at)->format('d/m/Y H:i') ?? 'pendiente de entrega' }}</small></div><div class="actions"><a class="text-link" href="{{ route('admin.versions.show', [$avatar, $version]) }}">Ver</a>@if($studio)<a class="button small" href="{{ route('admin.audio-studio.show', $avatar) }}">Gestionar en estudio</a>@endif</div></article>
@empty <div class="empty">{{ $studio ? 'Crea el avatar y carga su árbol dentro del Estudio de audio.' : 'Aún no se recibió un paquete de audio para este avatar.' }}</div>
@endforelse
</div>
@endsection
