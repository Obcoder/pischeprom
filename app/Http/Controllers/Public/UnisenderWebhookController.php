<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessUnisenderWebhookJob;
use App\Models\MailingWebhookCall;
use App\Services\CommercialOffers\MailProviderSafeErrorCode;
use App\Services\CommercialOffers\UnisenderWebhookPersistenceService;
use App\Services\CommercialOffers\VerifiedUnisenderWebhookRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class UnisenderWebhookController extends Controller
{
    public function verify(): JsonResponse
    {
        return response()->json(['status' => 'ok', 'provider' => 'unisender_go']);
    }

    public function handle(Request $request, UnisenderWebhookPersistenceService $persistence): JsonResponse
    {
        $verified = $request->attributes->get(VerifiedUnisenderWebhookRequest::REQUEST_ATTRIBUTE);
        if (! $verified instanceof VerifiedUnisenderWebhookRequest) {
            return response()->json([
                'status' => 'rejected',
                'code' => MailProviderSafeErrorCode::InvalidSignature->value,
            ], 403);
        }

        $persisted = $persistence->persist($verified);

        if ($persisted->eventIdsToQueue !== []) {
            try {
                ProcessUnisenderWebhookJob::dispatch($persisted->eventIdsToQueue)
                    ->onConnection($this->queueConnection())
                    ->onQueue((string) config('services.unisender_go.webhook_queue', 'mailing-webhooks'));
            } catch (Throwable) {
                MailingWebhookCall::query()->whereKey($persisted->webhookCallId)->update([
                    'status' => 'queue_failed',
                    'safe_error_code' => MailProviderSafeErrorCode::ProcessingFailedSafe->value,
                    'safe_summary' => 'queue_dispatch_failed_safe',
                ]);
                Log::error('Unisender webhook queue dispatch failed', [
                    'provider' => 'unisender_go',
                    'webhook_call_id' => $persisted->webhookCallId,
                    'safe_error_code' => MailProviderSafeErrorCode::ProcessingFailedSafe->value,
                ]);

                return response()->json([
                    'status' => 'temporarily_unavailable',
                    'code' => MailProviderSafeErrorCode::ProcessingFailedSafe->value,
                ], 503);
            }
        }

        return response()->json([
            'status' => 'ok',
            'duplicate' => $persisted->duplicateRequest,
            'accepted_events' => $persisted->acceptedEventCount,
        ]);
    }

    private function queueConnection(): string
    {
        $connection = (string) config('services.unisender_go.webhook_queue_connection', 'database');

        return $connection === '' || $connection === 'sync' ? 'database' : $connection;
    }
}
