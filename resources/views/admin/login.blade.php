<!doctype html>
<html lang="es">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Acceso · Avatar IA</title><link rel="stylesheet" href="{{ asset('css/platform.css') }}"></head>
<body class="platform-body centered">
<form class="card login-card" method="POST" action="{{ route('admin.login.store') }}">
    @csrf
    <span class="eyebrow">ADMINISTRACIÓN</span><h1>Avatar IA</h1><p>Ingresa con una cuenta administradora.</p>
    <label>Correo<input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="email"></label>
    <label>Contraseña<input type="password" name="password" required autocomplete="current-password"></label>
    <label class="check"><input type="checkbox" name="remember" value="1"> Mantener sesión</label>
    @error('email')<p class="field-error">{{ $message }}</p>@enderror
    <button class="button" type="submit">Ingresar</button>
</form>
</body>
</html>
