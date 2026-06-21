<?php

namespace App\Jobs;

use App\Models\MailingWebhookCall;
use App\Services\CommercialOffers\UnisenderWebhookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessUnisenderWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public int $webhookCallId) {}

    public function handle(UnisenderWebhookService $service): void
    {
        $call = MailingWebhookCall::query()->findOrFail($this->webhookCallId);

        try {
            $service->handleRawBody($call->raw_payload, $call);
        } catch (Throwable $exception) {
            $call->update([
                'processed_at' => now(),
                'error_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
