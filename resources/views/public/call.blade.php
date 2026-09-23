<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Llamada con {{ $avatar->name }}</title>
    <link rel="stylesheet" href="{{ asset('css/avatar-call.css') }}">
</head>
<body class="call-body">
    <main
        id="callShell"
        class="call-shell"
        data-status-url="{{ route('avatar.status') }}"
        data-greeting-url="{{ route('avatar.greeting') }}"
        data-message-url="{{ route('avatar.message') }}"
        data-poll-url="{{ url('/asistente/respuestas/:ticket') }}"
        data-rive-url="{{ asset($avatar->rive_path ?: 'assets/avatar/anita.riv') }}"
    >
        <header class="call-header">
            <a class="brand light" href="{{ route('avatar.landing') }}">
                <span class="brand-mark">✦</span> {{ strtoupper($avatar->name) }}
            </a>
            <span id="callStatus" class="connection" data-state="loading"><i></i> Preparando llamada</span>
        </header>

        <section class="call-stage">
            <div id="chat" class="conversation" aria-live="polite">
                <article class="bubble bubble--avatar">
                    <strong>{{ $avatar->name }}</strong>
                    <p>Estoy preparando nuestra llamada…</p>
                </article>
            </div>

            <div class="avatar-area">
                <div class="avatar-halo"></div>
                <canvas id="avatarCanvas" aria-label="Avatar animado de {{ $avatar->name }}"></canvas>
                <div id="avatarFallback" class="fallback-avatar" aria-hidden="true">
                    <div class="hair"></div>
                    <div class="face"><i class="eye left"></i><i class="eye right"></i><i class="mouth"></i></div>
                    <div class="body"><i></i></div>
                </div>
                <button id="startConversation" class="start-conversation" type="button" hidden>
                    Iniciar conversación
                </button>
                <div class="avatar-name">
                    <strong id="avatarName">{{ $avatar->name }}</strong>
                    <span>Disponible</span>
                </div>
            </div>

            <p id="callMessage" class="transcript" aria-live="polite">Preparando llamada…</p>
        </section>

        <footer class="call-controls">
            <button id="hangupButton" class="control hangup" type="button" aria-label="Colgar llamada">⌕</button>
            <div class="recording-wrap">
                <button id="microphoneButton" class="control microphone" type="button" aria-label="Grabar hasta 15 segundos" disabled><span>⌁</span></button>
                <b id="recordingTimer"></b>
            </div>
            <button id="speakerButton" class="control mute" type="button" aria-label="Silenciar o reactivar audio" aria-pressed="false">◖</button>
        </footer>
        <p class="call-help">El micrófono se detiene automáticamente a los 15 segundos. Vuelve a pulsarlo para detenerlo antes.</p>
    </main>

    <script src="https://unpkg.com/@rive-app/webgl2@2.42.2"></script>
    <script src="{{ asset('js/avatar-call.js') }}"></script>
</body>
</html>
