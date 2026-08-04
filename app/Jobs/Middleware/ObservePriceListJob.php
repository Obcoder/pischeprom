<?php

namespace App\Jobs\Middleware;

use App\Models\PriceListImport;
use Closure;
use Illuminate\Support\Facades\Log;
use Throwable;

class ObservePriceListJob
{
    public function handle(object $command, Closure $next): void
    {
        $importId = isset($command->importId) ? (int) $command->importId : null;
        $import = $importId ? PriceListImport::query()->find($importId) : null;
        $context = array_filter([
            'import_uuid' => $import?->uuid,
            'import_id' => $importId,
            'stage' => $import?->current_stage,
            'job' => $command::class,
            'job_uuid' => is_object($command->job ?? null) && method_exists($command->job, 'uuid') ? $command->job->uuid() : null,
            'attempt' => method_exists($command, 'attempts') ? $command->attempts() : null,
            'max_event_id' => isset($command->eventId) ? (int) $command->eventId : null,
        ], static fn ($value): bool => $value !== null && $value !== '');
        $started = hrtime(true);
        Log::info('price_list_job_started', $context);

        try {
            $next($command);
            Log::info('price_list_job_completed', [
                ...$context,
                'duration_ms' => (int) round((hrtime(true) - $started) / 1_000_000),
            ]);
        } catch (Throwable $exception) {
            Log::warning('price_list_job_failed', [
                ...$context,
                'duration_ms' => (int) round((hrtime(true) - $started) / 1_000_000),
                'exception_class' => $exception::class,
            ]);
            throw $exception;
        }
    }
}
