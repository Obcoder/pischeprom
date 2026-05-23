@php
    $appName = config('app.name', 'ПИЩЕПРОМ-СЕРВЕР');

    $configuredMetricaCounterId = config('services.yandex_metrica.counter_id');

    $metricaCounterId = is_numeric($configuredMetricaCounterId) && (int) $configuredMetricaCounterId > 0
        ? (int) $configuredMetricaCounterId
        : null;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <meta
        name="csrf-token"
        content="{{ csrf_token() }}"
    >

    {{-- Inertia использует нестандартный атрибут "inertia".
         Выводим тег через PHP, чтобы IDE не подсвечивала HTML-ошибку. --}}
    <?php echo '<title inertia>' . e($appName) . '</title>'; ?>

    <?php if ($metricaCounterId !== null): ?>
    <script>
        (function () {
            const counterId = {{ $metricaCounterId }};
            const metricaScriptUrl = 'https://mc.yandex.ru/metrika/tag.js';
            const metricaFunctionName = 'ym';

            window.dataLayer = window.dataLayer || [];

            window[metricaFunctionName] = window[metricaFunctionName] || function () {
                window[metricaFunctionName].a = window[metricaFunctionName].a || [];
                window[metricaFunctionName].a.push(arguments);
            };

            window[metricaFunctionName].l = Date.now();

            let scriptAlreadyAdded = false;

            for (let index = 0; index < document.scripts.length; index += 1) {
                if (document.scripts[index].src === metricaScriptUrl) {
                    scriptAlreadyAdded = true;
                    break;
                }
            }

            if (!scriptAlreadyAdded) {
                const metricaScript = document.createElement('script');
                const firstScript = document.getElementsByTagName('script')[0];

                metricaScript.async = true;
                metricaScript.src = metricaScriptUrl;

                firstScript.parentNode.insertBefore(metricaScript, firstScript);
            }

            window[metricaFunctionName](counterId, 'init', {
                clickmap: true,
                trackLinks: true,
                accurateTrackBounce: true,
                webvisor: true,
                ecommerce: 'dataLayer'
            });
        })();
    </script>
    <?php endif; ?>

    @routes

    @vite([
        'resources/js/app.js',
        "resources/js/Pages/{$page['component']}.vue"
    ])

    @inertiaHead
</head>

<body>
<?php if ($metricaCounterId !== null): ?>
<noscript>
    <div>
        <img
            src="https://mc.yandex.ru/watch/{{ $metricaCounterId }}"
            style="position:absolute; left:-9999px;"
            alt=""
        >
    </div>
</noscript>
<?php endif; ?>

@inertia
</body>
</html>
