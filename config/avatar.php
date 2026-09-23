<?php

return [
    // URL y token solo del servidor; el navegador nunca accede a Salad.
    'voicebox_enabled' => env('AVATAR_VOICEBOX_ENABLED', false),
    'voicebox_url' => rtrim((string) env('AVATAR_VOICEBOX_URL', ''), '/'),
    'voicebox_token' => env('AVATAR_VOICEBOX_TOKEN'),
    // Salad puede tardar algo más de un segundo en atravesar la red; este
    // límite solo comprueba salud, no envía texto ni audio.
    'voicebox_health_timeout_seconds' => (int) env('AVATAR_VOICEBOX_HEALTH_TIMEOUT_SECONDS', 5),
    'voicebox_timeout_seconds' => (int) env('AVATAR_VOICEBOX_TIMEOUT_SECONDS', 30),

    'router_enabled' => env('AVATAR_ROUTER_ENABLED', false),
    'router_url' => rtrim((string) env('AVATAR_ROUTER_URL', ''), '/'),
    'router_token' => env('AVATAR_ROUTER_TOKEN'),
    'router_timeout_seconds' => (int) env('AVATAR_ROUTER_TIMEOUT_SECONDS', 5),
    'public_domain_suffix' => env('AVATAR_PUBLIC_DOMAIN_SUFFIX', 'ia.tinq.pe'),
    'local_default_slug' => env('AVATAR_LOCAL_DEFAULT_SLUG', 'ica-demo'),
];
