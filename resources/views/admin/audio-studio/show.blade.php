@extends('admin.layout')

@section('content')
<div class="page-title"><div><span class="eyebrow">ESTUDIO LOCAL</span><h1>{{ $avatar->name }}</h1><p>Las muestras y los MP3 se quedan en esta Mac hasta el envío final.</p></div><a class="text-link" href="{{ route('admin.avatars.show', $avatar) }}">← Avatar</a></div>

<section class="card form-grid">
    <div><h2>1. Árbol conversacional</h2><p>Carga el JSON solo aquí. El VPS lo recibirá dentro del paquete verificado.</p></div>
    <a class="button" href="{{ route('admin.versions.create', $avatar) }}">Cargar árbol JSON</a>
</section>

<section id="animation-rive" class="card form-grid">
    <div><h2>2. Animación del avatar</h2><p>Este archivo se guarda únicamente en local para comprobar la boca y los visemas en la auditoría. No se envía al VPS.</p></div>
    <form method="POST" enctype="multipart/form-data" action="{{ route('admin.audio-studio.rive.store', $avatar) }}">@csrf
        <label>Archivo Rive (.riv)<input type="file" name="rive" accept=".riv,application/octet-stream" required></label>
        @error('rive')<p class="field-error">{{ $message }}</p>@enderror
        <button class="button small" type="submit">Guardar animación local</button>
    </form>
    @if($avatar->rive_path)<small><strong>Archivo local cargado:</strong> {{ basename($avatar->rive_path) }}. La vista de la auditoría lo usará al recargar.</small>@endif
</section>

<section class="card form-grid">
    <div><h2>3. Destino de entrega</h2><p>Selecciona el avatar que ya creaste en el VPS. No se sube nada mientras no haya MP3 listos.</p></div>
    @if($syncError)<p class="error">{{ $syncError }}</p>@else
    <form method="POST" action="{{ route('admin.audio-studio.destination', $avatar) }}">@csrf
        <label>Avatar del VPS<select name="delivery_slug"><option value="">Sin destino aún</option>@foreach($destinations as $destination)<option value="{{ $destination['slug'] }}" @selected($avatar->delivery_slug === $destination['slug'])>{{ $destination['name'] }} · {{ $destination['slug'] }}</option>@endforeach</select></label>
        <button class="button small" type="submit">Guardar destino</button>
    </form>@endif
</section>

@if($avatar->usesClonedVoice())
<section class="card form-grid">
    <div><h2>4. Muestras privadas</h2><p>Sube de 1 a 3 clips de una misma persona, en español peruano limpio. No hay un límite de segundos: prioriza una voz clara y sin música ni otras personas. No se exportan al VPS.</p></div>
    <form method="POST" enctype="multipart/form-data" action="{{ route('admin.audio-studio.samples.store', $avatar) }}">@csrf
        <label>Clips de voz<input type="file" name="samples[]" accept="audio/mpeg,audio/wav,audio/x-wav,audio/mp4,audio/x-m4a" multiple required></label>
        <button class="button small" type="submit">Guardar referencia privada</button>
    </form>
    @if($avatar->voiceSamples->isNotEmpty())<div><strong>Referencia local:</strong> {{ $avatar->voiceSamples->count() }} clip(s), {{ number_format($avatar->voiceSamples->sum('duration_ms') / 1000, 1) }} s. @foreach($avatar->voiceSamples as $sample)<small>{{ $sample->original_name }} · {{ number_format($sample->duration_ms / 1000, 1) }} s</small>@endforeach</div>@endif
</section>
@endif

<section class="section-heading"><h2>5. Pruebas, lote y entrega</h2><p>Escucha cinco pruebas antes de comenzar el lote. La prueba no guarda ni publica ningún audio.</p></section>
<div class="version-list">
@forelse($avatar->conversationVersions as $version)
    @php($lines = app(App\Services\ConversationTree::class)->lines($version->tree))
    @php($coverage = app(App\Services\ConversationCoverage::class)->summarize($version))
    @php($remaining = max(0, $coverage['total_assets'] - $coverage['ready_assets'] - $coverage['failed_assets']))
    <article class="card"><div class="version-row"><div><strong>{{ $version->label }}</strong><span class="status {{ $version->status }}">{{ $version->status }}</span>
        <small>
            Avance total: {{ $coverage['total_percent'] }}% · {{ $coverage['ready_assets'] }} / {{ $coverage['total_assets'] }} MP3 listos · {{ $coverage['generating_assets'] }} generándose · {{ $remaining }} por completar
            @if ($coverage['failed_assets'])
                · {{ $coverage['failed_assets'] }} con error
            @endif
        </small>
        <small>Cobertura temática: {{ $coverage['topics_percent'] }}% · {{ $coverage['topics_covered'] }}/{{ $coverage['topics_total'] }} temas con su primera variante lista.</small>
        <small>
            Variantes de resumen:
            @foreach ($coverage['variant_rounds'] as $round)
                {{ $round['number'] }}: {{ $round['ready'] }}/{{ $round['total'] }}@if (! $loop->last) · @endif
            @endforeach
        </small>
        <small>Enviado al VPS: {{ $coverage['synced_assets'] }} / {{ $coverage['ready_assets'] }} MP3 listos, en bloques de 5.</small>
    </div></div>
        <div class="actions" data-audio-preview-group>
            @foreach(array_slice(array_keys($lines), 0, 5) as $key)
                <button class="text-link" type="button" data-audio-preview-url="{{ route('admin.audio-studio.preview', [$avatar, $version, 'asset_key' => $key]) }}">Prueba {{ $loop->iteration }}</button>
            @endforeach
            <span class="field-hint" data-audio-preview-status role="status" aria-live="polite"></span>
            <audio data-audio-preview-player controls hidden></audio>
        </div>
        <div class="actions">
            @if(! $version->preview_approved_at)<form method="POST" action="{{ route('admin.audio-studio.approve', [$avatar, $version]) }}">@csrf<button class="button small" type="submit">Aprobar cinco pruebas</button></form>@endif
            @if(in_array($version->status, ['draft', 'uploaded']) && $version->preview_approved_at)<form method="POST" action="{{ route('admin.versions.publish', [$avatar, $version]) }}">@csrf<button class="button small" type="submit">Generar lote local</button></form>@endif
            @if($version->status === 'generating')<form method="POST" action="{{ route('admin.versions.resume', [$avatar, $version]) }}">@csrf<button class="button small" type="submit">Reanudar lote local</button></form>@endif
            @if($version->status === 'ready_to_upload' && $coverage['synced_assets'] < $coverage['ready_assets'])<form method="POST" action="{{ route('admin.audio-studio.upload', [$avatar, $version]) }}">@csrf<button class="button small" type="submit">Reintentar entrega final</button></form>@endif
        </div>
        <details class="tree-editor">
            <summary>Ver y editar {{ count($lines) }} frases del árbol</summary>
            <p>Al guardar, se regenera únicamente esa frase con sus visemas y se sincroniza al VPS en el próximo bloque de hasta cinco audios.</p>
            <div class="tree-editor-list">
                @foreach ($lines as $assetKey => $line)
                    @php($asset = $version->audioAssets->firstWhere('asset_key', $assetKey))
                    <form method="POST" action="{{ route('admin.audio-studio.line.update', [$avatar, $version]) }}" class="tree-editor-row">
                        @csrf
                        @method('PATCH')
                        <code>{{ $assetKey }}</code>
                        <span class="status {{ $asset?->status ?? 'pending' }}">{{ $asset?->status ?? 'pending' }}</span>
                        <input type="hidden" name="asset_key" value="{{ $assetKey }}">
                        <textarea name="text" rows="3" maxlength="900" required>{{ $line }}</textarea>
                        <button class="button small" type="submit">Regenerar esta frase</button>
                    </form>
                @endforeach
            </div>
        </details>
        @php($auditItems = $visemeAudits[$version->id] ?? [])
        @php($auditCounts = collect($auditItems)->countBy('status'))
        @php($flaggedAssets = $version->audioAssets->filter(fn ($asset) => in_array($auditItems[$asset->id]['status'] ?? null, ['warning', 'error'], true)))
        @php($reviewableAssets = $version->audioAssets->filter(fn ($asset) => $asset->status === 'ready' && $asset->path && isset($auditItems[$asset->id])))
        <section class="viseme-audit">
            <div class="viseme-audit-heading">
                <div>
                    <h3>Auditoría de visemas</h3>
                    <p>Solo lectura local. No regenera, modifica ni envía los MP3.</p>
                </div>
                <div class="viseme-audit-counts" aria-label="Resultado de la auditoría">
                    <span class="audit-status ok">{{ $auditCounts->get('ok', 0) }} correctos</span>
                    <span class="audit-status warning">{{ $auditCounts->get('warning', 0) }} advertencias</span>
                    <span class="audit-status error">{{ $auditCounts->get('error', 0) }} errores</span>
                </div>
            </div>
            <p class="field-hint">Se revisan {{ count($auditItems) }} MP3: REST al inicio y final, orden y rango de tiempos, valores 0–6, visemas hablados, pausas largas y repeticiones.</p>

            @if($reviewableAssets->isNotEmpty())
                @php($initialReviewAsset = $reviewableAssets->first())
                @php($initialReviewReport = $auditItems[$initialReviewAsset->id])
                <section class="viseme-manual-review viseme-audit-item" data-viseme-review data-viseme-events="{{ base64_encode(json_encode($initialReviewReport['events'])) }}">
                    <div>
                        <h4>Comparación visual manual</h4>
                        <p>Elige una frase, reproduce el MP3 y compara el texto con el visema activo, su tiempo y la boca del avatar.</p>
                    </div>
                    <label>Frase del árbol
                        <select data-viseme-manual-select>
                            @foreach($reviewableAssets as $asset)
                                @php($report = $auditItems[$asset->id])
                                <option value="{{ $asset->id }}" data-audio-url="{{ '/storage/'.ltrim($asset->path, '/') }}" data-events="{{ base64_encode(json_encode($report['events'])) }}" data-text="{{ $asset->text }}">{{ $asset->asset_key }} · {{ \Illuminate\Support\Str::limit($asset->text, 96) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <p class="viseme-review-text" data-viseme-review-text>{{ $initialReviewAsset->text }}</p>
                    <div class="viseme-review-player">
                        <audio controls preload="metadata" data-viseme-review-player src="{{ '/storage/'.ltrim($initialReviewAsset->path, '/') }}"></audio>
                        <output data-viseme-review-current>REST (0) · 0 ms</output>
                    </div>
                    <div class="viseme-review-controls" aria-label="Controles de revisión de visemas">
                        <span>Velocidad</span>
                        @foreach ([0.25, 0.5, 1] as $rate)
                            <button type="button" data-viseme-rate="{{ $rate }}" @if($rate === 1) class="active" aria-pressed="true" @else aria-pressed="false" @endif>{{ $rate }}×</button>
                        @endforeach
                        <button type="button" data-viseme-previous>← Visema anterior</button>
                        <button type="button" data-viseme-next>Visema siguiente →</button>
                    </div>
                    @if($riveUrl)
                        <section class="viseme-rive-preview" data-viseme-rive-url="{{ $riveUrl }}">
                            <div>
                                <h5>Vista del avatar Rive</h5>
                                <p>Recibe el mismo valor del visema activo en cada cuadro de reproducción.</p>
                            </div>
                            <canvas data-viseme-rive-canvas aria-label="Vista previa del avatar {{ $avatar->name }}"></canvas>
                            <p class="field-hint" data-viseme-rive-status role="status">Cargando el archivo Rive local…</p>
                        </section>
                    @else
                        <p class="viseme-rive-missing">Para ver la boca real, sube el archivo <strong>.riv</strong> en <a href="#animation-rive">Animación del avatar</a>. Debe incluir <code>AvatarStateMachine</code> y <code>ViewModel1.viseme</code>.</p>
                    @endif
                    <details open>
                        <summary>Ver {{ count($initialReviewReport['events']) }} eventos de visemas</summary>
                        <ol class="viseme-event-list" data-viseme-review-events></ol>
                    </details>
                </section>
            @endif

            @if($flaggedAssets->isEmpty())
                <p class="viseme-audit-clear">No se detectaron advertencias técnicas en esta versión.</p>
            @else
                <details class="viseme-audit-list" open>
                    <summary>Revisar {{ $flaggedAssets->count() }} audio(s) marcado(s)</summary>
                    @foreach($flaggedAssets as $asset)
                        @php($report = $auditItems[$asset->id])
                        <article class="viseme-audit-item" data-viseme-review data-viseme-events="{{ base64_encode(json_encode($report['events'])) }}">
                            <div class="viseme-audit-item-heading">
                                <div>
                                    <code>{{ $asset->asset_key }}</code>
                                    <span class="audit-status {{ $report['status'] }}">{{ $report['status'] === 'error' ? 'Error' : 'Revisar' }}</span>
                                </div>
                                @if($report['duration_ms'])<small>Duración auditada: {{ number_format($report['duration_ms'] / 1000, 2) }} s@if($report['measured_duration_ms']) · MP3 comprobado@endif</small>@endif
                            </div>
                            <p>{{ $asset->text }}</p>
                            <ul>
                                @foreach($report['issues'] as $issue)
                                    <li class="{{ $issue['severity'] }}">{{ $issue['message'] }}</li>
                                @endforeach
                            </ul>
                            @if($asset->path)
                                <div class="viseme-review-player">
                                    <audio controls preload="metadata" data-viseme-review-player src="{{ '/storage/'.ltrim($asset->path, '/') }}"></audio>
                                    <output data-viseme-review-current>REST (0) · 0 ms</output>
                                </div>
                                <details>
                                    <summary>Ver {{ count($report['events']) }} eventos</summary>
                                    <ol class="viseme-event-list" data-viseme-review-events></ol>
                                </details>
                            @endif
                        </article>
                    @endforeach
                </details>
            @endif
        </section>
    </article>
@empty <div class="empty">Carga el árbol JSON para habilitar las pruebas de voz.</div>
@endforelse
</div>
@endsection

@push('scripts')
<script src="https://unpkg.com/@rive-app/webgl2@2.42.2"></script>
<script>
document.querySelectorAll('[data-audio-preview-group]').forEach((group) => {
    const player = group.querySelector('[data-audio-preview-player]');
    const status = group.querySelector('[data-audio-preview-status]');
    let objectUrl = null;

    group.querySelectorAll('[data-audio-preview-url]').forEach((button) => {
        button.addEventListener('click', async () => {
            if (button.disabled) {
                return;
            }

            group.querySelectorAll('[data-audio-preview-url]').forEach((item) => item.disabled = true);
            status.textContent = `Generando ${button.textContent.trim()} en esta Mac… puede tardar hasta dos minutos.`;

            try {
                const response = await fetch(button.dataset.audioPreviewUrl, {
                    headers: { Accept: 'audio/mpeg, application/json' },
                });
                if (!response.ok) {
                    const body = await response.json().catch(() => ({}));
                    throw new Error(body.message || 'No se pudo generar la prueba de voz.');
                }

                const audio = await response.blob();
                if (objectUrl) {
                    URL.revokeObjectURL(objectUrl);
                }
                objectUrl = URL.createObjectURL(audio);
                player.src = objectUrl;
                player.hidden = false;
                status.textContent = 'Prueba lista.';

                try {
                    await player.play();
                } catch {
                    status.textContent = 'Prueba lista: pulsa reproducir para escucharla.';
                }
            } catch (error) {
                status.textContent = error.message || 'No se pudo generar la prueba de voz.';
            } finally {
                group.querySelectorAll('[data-audio-preview-url]').forEach((item) => item.disabled = false);
            }
        });
    });
});

const visemeNames = ['REST', 'AHH', 'EEE', 'III', 'OOO', 'UUU', 'FFF'];

const initializeVisemeReview = (review) => {
    const player = review.querySelector('[data-viseme-review-player]');
    const current = review.querySelector('[data-viseme-review-current]');
    const eventList = review.querySelector('[data-viseme-review-events]');
    const rivePreview = review.querySelector('[data-viseme-rive-url]');

    if (!player || !current) {
        return null;
    }

    let events = [];
    let activeEvent = -1;
    let animationFrame = null;
    let riveViseme = null;

    const setRiveViseme = (value) => {
        if (riveViseme) {
            riveViseme.value = value;
        }
    };

    const initializeRivePreview = () => {
        if (!rivePreview) {
            return;
        }

        const canvas = rivePreview.querySelector('[data-viseme-rive-canvas]');
        const status = rivePreview.querySelector('[data-viseme-rive-status]');

        if (!window.rive || !canvas || !status) {
            status.textContent = 'No se pudo cargar el reproductor Rive en este navegador.';

            return;
        }

        const riveInstance = new window.rive.Rive({
            src: rivePreview.dataset.visemeRiveUrl,
            canvas,
            layout: new window.rive.Layout({
                fit: window.rive.Fit.Contain,
                alignment: window.rive.Alignment.Center,
            }),
            autoplay: true,
            stateMachines: 'AvatarStateMachine',
            autoBind: true,
            onLoad: () => {
                riveInstance.resizeDrawingSurfaceToCanvas();
                riveViseme = riveInstance.viewModelInstance?.number('viseme');

                if (!riveViseme) {
                    status.textContent = 'El archivo Rive no expone ViewModel1.viseme como número.';

                    return;
                }

                riveViseme.value = 6;
                if (Number(riveViseme.value) !== 6) {
                    riveViseme = null;
                    status.textContent = 'El número viseme del archivo Rive no acepta valores del 0 al 6.';

                    return;
                }

                setRiveViseme(0);
                status.textContent = 'Rive listo: recibe REST=0, AHH=1, EEE=2, III=3, OOO=4, UUU=5 y FFF=6.';
            },
            onLoadError: () => {
                status.textContent = 'No se pudo abrir este archivo Rive. Verifica AvatarStateMachine y vuelve a subirlo.';
            },
        });
    };

    const renderEvents = () => {
        if (!eventList) {
            return;
        }

        eventList.replaceChildren();
        events.forEach((event, index) => {
            const item = document.createElement('li');
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = `${event.at_ms} ms · ${visemeNames[event.value] || `Desconocido (${event.value})`}`;
            button.dataset.visemeEvent = index;
            button.addEventListener('click', () => seekToEvent(index));
            item.append(button);
            eventList.append(item);
        });
    };

    const renderCurrentViseme = () => {
        const atMs = Math.round(player.currentTime * 1000);
        let nextActiveEvent = 0;

        events.forEach((event, index) => {
            if (event.at_ms <= atMs) {
                nextActiveEvent = index;
            }
        });

        const event = events[nextActiveEvent] || { at_ms: 0, value: 0 };
        current.textContent = `${visemeNames[event.value] || `Desconocido (${event.value})`} (${event.value}) · ${atMs} ms`;
        setRiveViseme(event.value);

        if (nextActiveEvent !== activeEvent) {
            activeEvent = nextActiveEvent;
            review.querySelectorAll('[data-viseme-event]').forEach((item) => item.classList.remove('active'));
            review.querySelector(`[data-viseme-event="${activeEvent}"]`)?.classList.add('active');
        }
    };

    const stopRendering = () => {
        if (animationFrame !== null) {
            window.cancelAnimationFrame(animationFrame);
            animationFrame = null;
        }
    };

    const renderPlaybackFrame = () => {
        renderCurrentViseme();

        if (!player.paused && !player.ended) {
            animationFrame = window.requestAnimationFrame(renderPlaybackFrame);
        } else {
            animationFrame = null;
        }
    };

    const startRendering = () => {
        stopRendering();
        renderPlaybackFrame();
    };

    const seekToEvent = (index) => {
        const event = events[index];
        if (!event) {
            return;
        }

        player.pause();
        player.currentTime = event.at_ms / 1000;
        renderCurrentViseme();
    };

    player.addEventListener('play', startRendering);
    player.addEventListener('pause', () => {
        stopRendering();
        renderCurrentViseme();
    });
    player.addEventListener('seeked', renderCurrentViseme);
    player.addEventListener('loadedmetadata', renderCurrentViseme);
    player.addEventListener('ended', () => {
        stopRendering();
        renderCurrentViseme();
    });

    review.querySelectorAll('[data-viseme-rate]').forEach((button) => {
        button.addEventListener('click', () => {
            player.playbackRate = Number(button.dataset.visemeRate);
            review.querySelectorAll('[data-viseme-rate]').forEach((item) => {
                const active = item === button;
                item.classList.toggle('active', active);
                item.setAttribute('aria-pressed', String(active));
            });
        });
    });

    review.querySelector('[data-viseme-previous]')?.addEventListener('click', () => seekToEvent(Math.max(0, activeEvent - 1)));
    review.querySelector('[data-viseme-next]')?.addEventListener('click', () => seekToEvent(Math.min(events.length - 1, activeEvent + 1)));

    const setEvents = (encodedEvents) => {
        events = JSON.parse(atob(encodedEvents));
        activeEvent = -1;
        renderEvents();
        renderCurrentViseme();
    };

    initializeRivePreview();
    setEvents(review.dataset.visemeEvents);

    return { setEvents };
};

document.querySelectorAll('[data-viseme-review]').forEach((review) => {
    review.visemeReview = initializeVisemeReview(review);
});

document.querySelectorAll('[data-viseme-manual-select]').forEach((select) => {
    select.addEventListener('change', () => {
        const option = select.selectedOptions[0];
        const review = select.closest('[data-viseme-review]');
        const player = review.querySelector('[data-viseme-review-player]');
        const text = review.querySelector('[data-viseme-review-text]');

        player.src = option.dataset.audioUrl;
        text.textContent = option.dataset.text;
        review.visemeReview?.setEvents(option.dataset.events);
        player.load();
    });
});

@if($avatar->conversationVersions->contains('status', 'generating'))
window.setTimeout(() => window.location.reload(), 10000);
@endif
</script>
@endpush
