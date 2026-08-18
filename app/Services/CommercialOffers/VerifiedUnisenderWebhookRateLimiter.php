<?php

namespace App\Services\CommercialOffers;

use Illuminate\Cache\RateLimiter;

final class VerifiedUnisenderWebhookRateLimiter
{
    public const CACHE_KEY = 'unisender-webhook:verified-provider-scope:v1';

    private const DECAY_SECONDS = 60;

    public function __construct(private readonly RateLimiter $limiter) {}

    public function allow(AuthenticatedUnisenderWebhookRequest $request): bool
    {
        if (! preg_match('/\A[a-f0-9]{64}\z/', $request->requestHash)) {
            return false;
        }

        if ($this->limiter->tooManyAttempts(self::CACHE_KEY, $this->maxAttempts())) {
            return false;
        }

        $this->limiter->hit(self::CACHE_KEY, self::DECAY_SECONDS);

        return true;
    }

    public function maxAttempts(): int
    {
        return min(1200, max(
            120,
            (int) config('services.unisender_go.webhook_max_parallel', 10) * 12,
        ));
    }

    public function retryAfterSeconds(): int
    {
        return max(1, $this->limiter->availableIn(self::CACHE_KEY));
    }
}
