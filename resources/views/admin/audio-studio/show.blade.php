@extends('admin.layout')

@section('content')
<div class="page-title"><div><span class="eyebrow">ESTUDIO LOCAL</span><h1>{{ $avatar->name }}</h1><p>Las muestras y los MP3 se quedan en esta Mac hasta el envío final.</p></div><a class="text-link" href="{{ route('admin.avatars.show', $avatar) }}">← Avatar</a></div>

<section class="card form-grid">
    <div><h2>1. Árbol conversacional</h2><p>Carga el JSON solo aquí. El VPS lo recibirá dentro del paquete verificado.</p></div>
    <a class="button" href="{{ route('admin.versions.create', $avatar) }}">Cargar árbol JSON</a>
</section>

<section class="card form-grid">
    <div><h2>2. Destino de entrega</h2><p>Selecciona el avatar que ya creaste en el VPS. No se sube nada mientras no haya MP3 listos.</p></div>
    @if($syncError)<p class="error">{{ $syncError }}</p>@else
    <form method="POST" action="{{ route('admin.audio-studio.destination', $avatar) }}">@csrf
        <label>Avatar del VPS<select name="delivery_slug"><option value="">Sin destino aún</option>@foreach($destinations as $destination)<option value="{{ $destination['slug'] }}" @selected($avatar->delivery_slug === $destination['slug'])>{{ $destination['name'] }} · {{ $destination['slug'] }}</option>@endforeach</select></label>
        <button class="button small" type="submit">Guardar destino</button>
    </form>@endif
</section>

@if($avatar->usesClonedVoice())
<section class="card form-grid">
    <div><h2>3. Muestras privadas</h2><p>Sube de 1 a 3 clips de una misma persona, en español peruano limpio. Deben sumar entre 20 y 30 segundos. No se exportan al VPS.</p></div>
    <form method="POST" enctype="multipart/form-data" action="{{ route('admin.audio-studio.samples.store', $avatar) }}">@csrf
        <label>Clips de voz<input type="file" name="samples[]" accept="audio/mpeg,audio/wav,audio/x-wav,audio/mp4,audio/x-m4a" multiple required></label>
        <button class="button small" type="submit">Guardar referencia privada</button>
    </form>
    @if($avatar->voiceSamples->isNotEmpty())<div><strong>Referencia local:</strong> {{ $avatar->voiceSamples->count() }} clip(s), {{ number_format($avatar->voiceSamples->sum('duration_ms') / 1000, 1) }} s. @foreach($avatar->voiceSamples as $sample)<small>{{ $sample->original_name }} · {{ number_format($sample->duration_ms / 1000, 1) }} s</small>@endforeach</div>@endif
</section>
@endif

<section class="section-heading"><h2>4. Pruebas, lote y entrega</h2><p>Escucha cinco pruebas antes de comenzar el lote. La prueba no guarda ni publica ningún audio.</p></section>
<div class="version-list">
@forelse($avatar->conversationVersions as $version)
    @php($lines = app(App\Services\ConversationTree::class)->lines($version->tree))
    <article class="card"><div class="version-row"><div><strong>{{ $version->label }}</strong><span class="status {{ $version->status }}">{{ $version->status }}</span><small>{{ $version->audio_assets_count }} / {{ count($lines) }} MP3 listos</small></div></div>
        <div class="actions">@foreach(array_slice(array_keys($lines), 0, 5) as $key)<a class="text-link" target="_blank" href="{{ route('admin.audio-studio.preview', [$avatar, $version, 'asset_key' => $key]) }}">Prueba {{ $loop->iteration }}</a>@endforeach</div>
        <div class="actions">
            @if(! $version->preview_approved_at)<form method="POST" action="{{ route('admin.audio-studio.approve', [$avatar, $version]) }}">@csrf<button class="button small" type="submit">Aprobar cinco pruebas</button></form>@endif
            @if(in_array($version->status, ['draft', 'uploaded']) && $version->preview_approved_at)<form method="POST" action="{{ route('admin.versions.publish', [$avatar, $version]) }}">@csrf<button class="button small" type="submit">Generar lote local</button></form>@endif
            @if($version->status === 'ready_to_upload')<form method="POST" action="{{ route('admin.audio-studio.upload', [$avatar, $version]) }}">@csrf<button class="button small" type="submit">Subir y publicar en VPS</button></form>@endif
        </div>
    </article>
@empty <div class="empty">Carga el árbol JSON para habilitar las pruebas de voz.</div>
@endforelse
</div>
@endsection
