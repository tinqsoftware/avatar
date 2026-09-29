<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Avatar IA · Administración</title>
    <link rel="stylesheet" href="{{ asset('css/platform.css') }}">
</head>
<body class="platform-body">
<header class="platform-header">
    <a href="{{ route('admin.avatars.index') }}" class="platform-brand">✦ AVATAR IA</a>
    @auth
        <div class="actions">
            @if (config('avatar.audio_role') === 'studio')
                <a class="link-button" href="{{ route('admin.audio-studio.index') }}">Estudio de audio</a>
            @endif
            <form method="POST" action="{{ route('admin.logout') }}">@csrf<button class="link-button" type="submit">Cerrar sesión</button></form>
        </div>
    @endauth
</header>
<main class="platform-main">
    @if (session('success'))<div class="flash success">{{ session('success') }}</div>@endif
    @if ($errors->any())<div class="flash error"><strong>Revisa lo siguiente:</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @yield('content')
</main>
@stack('scripts')
</body>
</html>
