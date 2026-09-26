@php
    $callCssUrl = asset('css/avatar-call.css').'?v='.filemtime(public_path('css/avatar-call.css'));
    $callScriptUrl = asset('js/avatar-call.js').'?v='.filemtime(public_path('js/avatar-call.js'));
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Llamada con {{ $avatar->name }}</title>
    <link rel="stylesheet" href="{{ $callCssUrl }}">
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
        data-avatar-name="{{ $avatar->name }}"
    >
        <section class="call-stage">
            @if($backgroundUrl)
                <div class="avatar-background" aria-hidden="true">
                    <img src="{{ $backgroundUrl }}" alt="">
                </div>
            @endif

            <div class="chat-panel">
                <div id="chat" class="conversation" aria-live="polite" aria-relevant="additions text"></div>
            </div>

            <div class="avatar-area">
                <div class="avatar-halo"></div>
                <canvas id="avatarCanvas" aria-label="Avatar animado de {{ $avatar->name }}"></canvas>
                <button id="startConversation" class="start-conversation" type="button" hidden>
                    Iniciar conversación
                </button>
            </div>
        </section>

        <footer class="call-controls">
            <button id="hangupButton" class="control hangup" type="button" aria-label="Colgar llamada">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <g transform="rotate(135 12 12)">
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.18 4.22 2 2 0 0 1 4.17 2h3a2 2 0 0 1 2 1.72c.12.9.33 1.78.62 2.63a2 2 0 0 1-.45 2.11L8.07 9.73a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.85.29 1.73.5 2.63.62A2 2 0 0 1 22 16.92Z" />
                    </g>
                </svg>
            </button>
            <div class="recording-wrap">
                <button id="microphoneButton" class="control microphone" type="button" aria-label="Grabar hasta 15 segundos" disabled>
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M12 14.5a3.5 3.5 0 0 0 3.5-3.5V6a3.5 3.5 0 1 0-7 0v5a3.5 3.5 0 0 0 3.5 3.5Z" />
                        <path d="M18 10.5a6 6 0 0 1-12 0M12 16.5V21M8.5 21h7" />
                    </svg>
                </button>
                <b id="recordingTimer"></b>
                <p id="callHint" class="call-hint" role="status" aria-live="polite"></p>
            </div>
        </footer>
    </main>

    <script src="https://unpkg.com/@rive-app/webgl2@2.42.2"></script>
    <script src="{{ $callScriptUrl }}"></script>
</body>
</html>
