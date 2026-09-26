<?php

return [
    'audio_role' => env('AVATAR_AUDIO_ROLE', 'studio'),
    'sync_url' => rtrim((string) env('AVATAR_SYNC_URL', ''), '/'),
    'sync_token' => env('AVATAR_SYNC_TOKEN'),
    'sync_timeout_seconds' => (int) env('AVATAR_SYNC_TIMEOUT_SECONDS', 120),
    // URL y token solo del servidor; el navegador nunca accede a Salad.
    'voicebox_enabled' => env('AVATAR_VOICEBOX_ENABLED', false),
    'voicebox_url' => rtrim((string) env('AVATAR_VOICEBOX_URL', ''), '/'),
    'voicebox_token' => env('AVATAR_VOICEBOX_TOKEN'),
    // Salad puede tardar algo más de un segundo en atravesar la red; este
    // límite solo comprueba salud, no envía texto ni audio.
    'voicebox_health_timeout_seconds' => (int) env('AVATAR_VOICEBOX_HEALTH_TIMEOUT_SECONDS', 5),
    'voicebox_timeout_seconds' => (int) env('AVATAR_VOICEBOX_TIMEOUT_SECONDS', 30),
    'voicebox_synthetic_speech_path' => env('AVATAR_VOICEBOX_SYNTHETIC_SPEECH_PATH', '/v1/anita/speech'),
    'voicebox_clone_speech_path' => env('AVATAR_VOICEBOX_CLONE_SPEECH_PATH', '/v1/cloned/speech'),
    'voicebox_synthetic_reference_path' => env('AVATAR_VOICEBOX_SYNTHETIC_REFERENCE_PATH', storage_path('app/private/voice-references/anita.wav')),

    'router_enabled' => env('AVATAR_ROUTER_ENABLED', false),
    'router_url' => rtrim((string) env('AVATAR_ROUTER_URL', ''), '/'),
    'router_token' => env('AVATAR_ROUTER_TOKEN'),
    'router_timeout_seconds' => (int) env('AVATAR_ROUTER_TIMEOUT_SECONDS', 5),
    'public_domain_suffix' => env('AVATAR_PUBLIC_DOMAIN_SUFFIX', 'ia.tinq.pe'),
    'local_default_slug' => env('AVATAR_LOCAL_DEFAULT_SLUG', 'ica-demo'),
];
