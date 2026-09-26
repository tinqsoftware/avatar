@extends('admin.layout')

@section('content')
<div class="page-title"><div><span class="eyebrow">{{ $avatar->name }}</span><h1>Subir árbol conversacional</h1><p>Incluye temas, variantes, tres niveles de detalle y conectores.</p></div><a class="text-link" href="{{ route('admin.avatars.show', $avatar) }}">← Volver</a></div>
<form class="card form-grid" method="POST" action="{{ route('admin.versions.store', $avatar) }}">@csrf
    <label>Nombre de esta versión<input name="label" value="{{ old('label', 'Versión '.now()->format('d-m-Y H:i')) }}" required></label>
    <label>JSON del árbol<textarea name="tree_json" rows="22" required spellcheck="false">{{ old('tree_json') }}</textarea><small>La validación exige greeting, fallback, connectors y temas con id, title, description, examples, keywords, summary, detail y next.</small></label>
    <button class="button" type="submit">Guardar borrador</button>
</form>
@endsection
