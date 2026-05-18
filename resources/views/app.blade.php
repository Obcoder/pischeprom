<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="yandex-verification" content="9eaa399be3fa6a51">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    @inertiaHead

    @php
        /*
        |--------------------------------------------------------------------------
        | JSON-LD for crawlers
        |--------------------------------------------------------------------------
        | Важно:
        | Vue/Inertia Head без рабочего SSR не попадает в исходный HTML.
        | Поэтому выводим structured data прямо из Inertia props в Blade.
        */

        $jsonLd = data_get($page ?? [], 'props.seo.jsonLd');

        if (is_string($jsonLd)) {
            $decodedJsonLd = json_decode($jsonLd, true);
            $jsonLd = json_last_error() === JSON_ERROR_NONE ? $decodedJsonLd : null;
        }

        $jsonLdItems = [];

        if (is_array($jsonLd) && ! empty($jsonLd)) {
            $jsonLdItems = array_is_list($jsonLd)
                && isset($jsonLd[0])
                && is_array($jsonLd[0])
                    ? $jsonLd
                    : [$jsonLd];
        }

        $jsonFlags = JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_HEX_TAG
            | JSON_HEX_AMP
            | JSON_HEX_APOS
            | JSON_HEX_QUOT;
    @endphp

    @foreach ($jsonLdItems as $jsonLdItem)
        @if (is_array($jsonLdItem) && ! empty($jsonLdItem))
            <script type="application/ld+json">{!! json_encode($jsonLdItem, $jsonFlags) !!}</script>
        @endif
    @endforeach

    <!-- Scripts -->
    @routes
    @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
</head>
<body class="font-sans antialiased">
@inertia
</body>
</html>
