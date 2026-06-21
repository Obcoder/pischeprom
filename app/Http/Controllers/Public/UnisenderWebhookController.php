<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessUnisenderWebhookJob;
use App\Models\MailingWebhookCall;
use App\Services\CommercialOffers\UnisenderGoClient;
use App\Services\CommercialOffers\UnisenderWebhookService;
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

    public function handle(Request $request, UnisenderGoClient $client, UnisenderWebhookService $service): JsonResponse
    {
        $rawBody = $request->getContent();
        $authValid = $client->verifyWebhookRawBody($rawBody);

        $call = MailingWebhookCall::query()->create([
            'provider' => 'unisender_go',
            'auth_valid' => $authValid,
            'raw_payload' => $rawBody,
            'created_at' => now(),
        ]);

        if (! $authValid) {
            Log::warning('Unisender Go webhook rejected', ['provider' => 'unisender_go', 'webhook_call_id' => $call->id]);

            return response()->json(['status' => 'forbidden'], 403);
        }

        try {
            if (config('queue.default') !== 'sync') {
                ProcessUnisenderWebhookJob::dispatch($call->id);
            } else {
                $service->handleRawBody($rawBody, $call);
            }
        } catch (Throwable $exception) {
            $call->update(['processed_at' => now(), 'error_message' => $exception->getMessage()]);
            Log::error('Unisender Go webhook processing failed', [
                'provider' => 'unisender_go',
                'webhook_call_id' => $call->id,
                'error' => $exception->getMessage(),
            ]);
        }

        return response()->json(['status' => 'ok']);
    }
}
