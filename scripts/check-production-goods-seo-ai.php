<?php

declare(strict_types=1);

use App\Models\Good;
use App\Services\Seo\GoodSeoAiException;
use App\Services\Seo\GoodSeoAiService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Collection;

// The default check is free of provider requests and catalog reads or writes.
// --live explicitly makes one billable H1 request using synthetic data only.
if ($argc < 2 || $argc > 3 || ($argc === 3 && $argv[2] !== '--live')) {
    fwrite(STDERR, "Expected TARGET_DIR [--live].\n");
    exit(2);
}

try {
    $targetDir = rtrim($argv[1], '/');

    if (! is_readable($targetDir.'/vendor/autoload.php') || ! is_readable($targetDir.'/bootstrap/app.php')) {
        throw new RuntimeException('The application is unavailable.');
    }

    require $targetDir.'/vendor/autoload.php';
    $app = require $targetDir.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();

    $service = $app->make(GoodSeoAiService::class);
    $model = config('goods-seo-ai.timeweb.model');
    $tokenParameter = config('goods-seo-ai.timeweb.token_parameter');

    if (($service->availability()['available'] ?? false) !== true
        || $model !== 'yandex/yandexgpt-pro-5.1' || $tokenParameter !== 'max_tokens') {
        throw new RuntimeException('Goods SEO AI is not configured for production.');
    }

    $route = $app['router']->getRoutes()->getByName('api.goods.seo.generate-ai');

    if ($route === null || $route->methods() !== ['POST']
        || array_diff(['auth:sanctum', 'verified', 'throttle:10,1,goods-seo-ai'], $route->gatherMiddleware()) !== []) {
        throw new RuntimeException('The protected AI generation route is unavailable.');
    }

    if ($argc === 3) {
        $good = new Good([
            'name' => 'Яблочный пектин',
            'description' => 'Пектин для использования в пищевом производстве.',
        ]);
        // Preload every relation consulted by the service to prevent catalog queries.
        $good->setRelation('seo', null);
        $good->setRelation('products', new Collection);
        $good->setRelation('country', null);

        $value = $service->generate($good, 'h1');
        $length = mb_strlen($value);

        if (trim($value) === '' || $length > 255 || strip_tags($value) !== $value
            || preg_match('/[\r\n\x00-\x1F\x7F]/u', $value) !== 0) {
            throw new RuntimeException('The live H1 response is invalid.');
        }

        fwrite(STDOUT, "Goods SEO AI live H1 check passed: chars={$length}.\n");
    }

    fwrite(STDOUT, "Goods SEO AI check passed: enabled=true model={$model} token_parameter={$tokenParameter}.\n");
} catch (GoodSeoAiException $exception) {
    fwrite(STDERR, "Goods SEO AI check failed: {$exception->errorCode}.\n");
    exit(1);
} catch (Throwable) {
    // Provider errors may contain credentials or payloads; never print exceptions.
    fwrite(STDERR, "Goods SEO AI check failed; inspect application configuration and provider availability on the server.\n");
    exit(1);
}
