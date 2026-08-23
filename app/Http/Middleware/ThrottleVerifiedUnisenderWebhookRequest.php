<?php

namespace App\Http\Middleware;

use App\Services\CommercialOffers\AuthenticatedUnisenderWebhookRequest;
use App\Services\CommercialOffers\MailProviderSafeErrorCode;
use App\Services\CommercialOffers\VerifiedUnisenderWebhookRateLimiter;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class ThrottleVerifiedUnisenderWebhookRequest
{
    public function __construct(private readonly VerifiedUnisenderWebhookRateLimiter $limiter) {}

    public function handle(Request $request, Closure $next): Response
    {
        $authenticated = $request->attributes->get(AuthenticatedUnisenderWebhookRequest::REQUEST_ATTRIBUTE);
        if (! $authenticated instanceof AuthenticatedUnisenderWebhookRequest) {
            return $this->reject(MailProviderSafeErrorCode::InvalidSignature, 403);
        }

        try {
            if (! $this->limiter->allow($authenticated)) {
                return $this->reject(
                    MailProviderSafeErrorCode::RateLimited,
                    429,
                    ['Retry-After' => (string) $this->limiter->retryAfterSeconds()],
                );
            }
        } catch (Throwable) {
            Log::error('Verified Unisender webhook limiter unavailable', [
                'provider' => 'unisender_go',
                'safe_error_code' => MailProviderSafeErrorCode::ProcessingFailedSafe->value,
            ]);

            return $this->reject(MailProviderSafeErrorCode::ProcessingFailedSafe, 503);
        }

        return $next($request);
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function reject(MailProviderSafeErrorCode $code, int $status, array $headers = []): JsonResponse
    {
        return response()->json([
            'status' => 'rejected',
            'code' => $code->value,
        ], $status, $headers);
    }
}
