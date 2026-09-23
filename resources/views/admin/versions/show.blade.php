@extends('admin.layout')

@section('content')
<div class="page-title"><div><span class="eyebrow">{{ strtoupper($version->status) }}</span><h1>{{ $version->label }}</h1><p>{{ $avatar->name }}</p></div><a class="text-link" href="{{ route('admin.avatars.show', $avatar) }}">← Volver</a></div>
<section class="card"><h2>Medios estáticos</h2><div class="asset-list">@foreach($version->audioAssets as $asset)<div><code>{{ $asset->asset_key }}</code><span class="status {{ $asset->status }}">{{ $asset->status }}</span><small>{{ $asset->duration_ms ? $asset->duration_ms.' ms' : 'Sin duración' }}</small></div>@endforeach</div></section>
<section class="card"><h2>Árbol publicado</h2><pre>{{ json_encode($version->tree, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></section>
@endsection
