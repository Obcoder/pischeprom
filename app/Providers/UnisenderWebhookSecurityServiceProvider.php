<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class UnisenderWebhookSecurityServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        RateLimiter::for('unisender-webhook', function (Request $request): Limit {
            $perMinute = min(1200, max(
                120,
                (int) config('services.unisender_go.webhook_max_parallel', 10) * 12,
            ));
            $ipFingerprint = hash_hmac(
                'sha256',
                (string) $request->ip(),
                (string) (config('app.key') ?: 'unisender-webhook-rate-limit'),
            );

            return Limit::perMinute($perMinute)
                ->by('unisender-webhook:'.$ipFingerprint)
                ->response(fn (Request $request, array $headers) => response()->json([
                    'status' => 'rejected',
                    'code' => 'rate_limited',
                ], 429, $headers));
        });
    }
}
