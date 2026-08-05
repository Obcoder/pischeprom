<?php

use App\Domain\Avito\Exceptions\AvitoException;
use App\Domain\Banking\Exceptions\BankingException;
use App\Services\Logistics\Routing\Exceptions\RoutingException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'webhooks/unisender-go',
            'mailings/unsubscribe/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->dontReport(BankingException::class);
        $exceptions->dontReport(AvitoException::class);
        $exceptions->dontReport(RoutingException::class);

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
