<?php

use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Search\SearchProviderException;
use App\Domain\Avito\Exceptions\AvitoException;
use App\Domain\Banking\Exceptions\BankingException;
use App\Services\Logistics\Routing\Exceptions\RoutingException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (Application $app): void {
            Route::middleware([])->group($app->basePath('routes/provider-webhooks.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'mailings/unsubscribe/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->dontReport(BankingException::class);
        $exceptions->dontReport(AvitoException::class);
        $exceptions->dontReport(RoutingException::class);
        $exceptions->dontReport(PolicyViolation::class);
        $exceptions->dontReport(SearchProviderException::class);

        $exceptions->render(function (PolicyViolation $exception, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            $status = $exception->errorCode === 'idempotency_key_conflict' ? 409 : 422;

            return response()->json([
                'message' => $exception->getMessage(),
                'code' => $exception->errorCode,
                'field' => $exception->field,
            ], $status);
        });

        $exceptions->render(function (SearchProviderException $exception, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            $status = match ($exception->category) {
                'validation', 'url_policy', 'fetch_policy', 'robots', 'page_parse', 'policy', 'protocol', 'idempotency' => 422,
                'authentication' => 502,
                'rate_limit' => 429,
                'network', 'provider_unavailable', 'provider_rejected', 'response' => 502,
                default => 500,
            };

            return response()->json([
                'message' => $exception->getMessage(),
                'category' => $exception->category,
                'code' => $exception->safeCode,
            ], $status);
        });

        $exceptions->render(function (AvitoException $exception, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => $exception->getMessage(),
                'category' => $exception->category,
                'retryable' => $exception->retryable,
            ], $exception->httpStatus);
        });

        $exceptions->render(function (RoutingException $exception, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => $exception->getMessage(),
                'code' => $exception->domainCode,
                'retryable' => $exception->retryable,
            ], $exception->httpStatus);
        });

        $exceptions->render(function (BankingException $exception, Request $request) {
            if (! $request->expectsJson()) {
                return null;
            }

            $status = match ($exception->category) {
                'authentication' => 401,
                'authorization', 'authorization_scope', 'scope' => 403,
                'reconciliation_conflict' => 409,
                'validation', 'configuration', 'read_only_violation' => 422,
                'rate_limit' => 429,
                'certificate', 'network_timeout', 'bank_unavailable', 'malformed_response' => 502,
                default => 500,
            };

            $response = response()->json([
                'message' => $exception->getMessage(),
                'category' => $exception->category,
            ], $status);

            if ($exception->retryAfterSeconds !== null) {
                $response->header('Retry-After', (string) $exception->retryAfterSeconds);
            }

            return $response;
        });
    })->create();
