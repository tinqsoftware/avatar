@php($measurementId = config('avatar.google_analytics_measurement_id'))

@if (filled($measurementId))
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ urlencode($measurementId) }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', @json($measurementId));
    </script>
@endif
