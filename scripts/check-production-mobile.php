<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

// Read schema metadata, then make anonymous requests to the same HTTPS
// endpoint as the phone. Empty login data fails before any account lookup or
// token creation. No sales, stock movements or authenticated requests are made.
if ($argc !== 2) {
    fwrite(STDERR, "Expected TARGET_DIR.\n");
    exit(2);
}

$stage = 'application bootstrap';

try {
    $targetDir = rtrim($argv[1], '/');
    require $targetDir.'/vendor/autoload.php';
    $app = require $targetDir.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();

    $stage = 'fulfillment schema';
    if (! Schema::hasColumns('orders', [
        'fulfillment_warehouse_id', 'prepared_by_user_id', 'prepared_at',
        'prepared_fingerprint', 'preparation_invalidated_at', 'shipped_sale_id',
        'shipped_by_user_id', 'shipped_at',
    ]) || ! Schema::hasColumn('order_items', 'measure_id')) {
        throw new RuntimeException('Mobile order fulfillment migration is missing.');
    }

    $stage = 'HTTPS application URL';
    $baseUrl = rtrim((string) config('app.url'), '/');
    $url = parse_url($baseUrl);
    if (! is_array($url) || ($url['scheme'] ?? null) !== 'https'
        || empty($url['host']) || isset($url['user']) || isset($url['pass'])
        || isset($url['query']) || isset($url['fragment'])) {
        throw new RuntimeException('APP_URL must be the canonical HTTPS application URL.');
    }

    $stage = 'POST /api/mobile/v1/auth/login';
    $login = Http::acceptJson()->connectTimeout(5)->timeout(15)
        ->withoutRedirecting()->withBody('{}', 'application/json')
        ->post($baseUrl.'/api/mobile/v1/auth/login');
    if ($login->status() !== 422
        || ! str_contains(strtolower($login->header('Content-Type')), 'application/json')
        || ! is_array($login->json('errors.email'))
        || ! is_array($login->json('errors.password'))
        || ! is_array($login->json('errors.device_name'))) {
        throw new RuntimeException('The login validation endpoint is unavailable.');
    }

    $stage = 'GET /api/mobile/v1/orders without authentication';
    $orders = Http::acceptJson()->connectTimeout(5)->timeout(15)
        ->withoutRedirecting()->get($baseUrl.'/api/mobile/v1/orders');
    if ($orders->status() !== 401
        || ! str_contains(strtolower($orders->header('Content-Type')), 'application/json')
        || ! is_string($orders->json('message'))) {
        throw new RuntimeException('The orders endpoint must reject anonymous requests.');
    }

    foreach (['config', 'orders'] as $endpoint) {
        $stage = 'GET /api/mobile/v1/delivery-map/'.$endpoint.' without authentication';
        $map = Http::acceptJson()->connectTimeout(5)->timeout(15)
            ->withoutRedirecting()->get($baseUrl.'/api/mobile/v1/delivery-map/'.$endpoint);
        if ($map->status() !== 401
            || ! str_contains(strtolower($map->header('Content-Type')), 'application/json')
            || ! is_string($map->json('message'))) {
            throw new RuntimeException('Delivery map endpoints must reject anonymous requests.');
        }
    }

    fwrite(STDOUT, "Mobile API checks passed: fulfillment schema, login HTTP 422, anonymous orders and delivery map HTTP 401.\n");
    fwrite(STDOUT, 'Mobile stock shortage override: '.(config('mobile.allow_negative_stock') ? 'enabled' : 'disabled').".\n");
    fwrite(STDOUT, 'Yandex delivery map key: '.(trim((string) config('gis.providers.yandex.api_key')) !== '' ? 'configured' : 'not configured').".\n");
} catch (Throwable) {
    // Never print response bodies, configuration values or exception messages.
    fwrite(STDERR, "Mobile API check failed during {$stage}; inspect the application locally on the VPS.\n");
    exit(1);
}
