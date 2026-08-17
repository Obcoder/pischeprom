<?php

namespace App\Http\Middleware;

use App\Services\CommercialOffers\MailProviderSafeErrorCode;
use App\Services\CommercialOffers\UnisenderWebhookIngress;
use App\Services\CommercialOffers\UnisenderWebhookRequestException;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class VerifyUnisenderWebhookRequest
{
    public function __construct(private readonly UnisenderWebhookIngress $ingress) {}

    public function handle(Request $request, Closure $next): Response
    {
        $contentType = mb_strtolower(trim(explode(';', (string) $request->header('Content-Type'))[0]));
        $contentEncoding = mb_strtolower(trim((string) $request->header('Content-Encoding', 'identity')));

        if ($contentType !== 'application/json' || ! in_array($contentEncoding, ['', 'identity'], true)) {
            return $this->reject(MailProviderSafeErrorCode::InvalidContentType, 415);
        }

        $declaredLength = $request->header('Content-Length');
        if (is_string($declaredLength) && ctype_digit($declaredLength)
            && (int) $declaredLength > UnisenderWebhookIngress::MAX_ENCODED_BODY_BYTES) {
            return $this->reject(MailProviderSafeErrorCode::PayloadTooLarge, 413);
        }

        $rawBody = $request->getContent();
        if (strlen($rawBody) > UnisenderWebhookIngress::MAX_ENCODED_BODY_BYTES) {
            return $this->reject(MailProviderSafeErrorCode::PayloadTooLarge, 413);
        }

        try {
            $verified = $this->ingress->verifyAndNormalize($rawBody);
        } catch (UnisenderWebhookRequestException $exception) {
            return $this->reject($exception->safeCode, $exception->httpStatus);
        }

        $request->attributes->set($verified::REQUEST_ATTRIBUTE, $verified);

        return $next($request);
    }

    private function reject(MailProviderSafeErrorCode $code, int $status): JsonResponse
    {
        return response()->json([
            'status' => 'rejected',
            'code' => $code->value,
        ], $status);
    }
}
