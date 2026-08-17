<?php

namespace App\Jobs\AiSales;

use App\Domain\AiSales\Services\ExecuteProspectingSearchQuery;
use App\Models\ProspectingSearchQuery;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class ExecuteProspectingSearchQueryJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 60;

    public bool $failOnTimeout = true;

    public function __construct(
        public readonly int $queryId,
        public readonly int $actorUserId,
    ) {
        $this->onConnection(config('ai-sales.queue.connection'));
        $this->onQueue(config('ai-sales.queue.name'));
    }

    public function uniqueId(): string
    {
        return 'prospecting-search-query:'.$this->queryId;
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->uniqueId()))->dontRelease()->expireAfter($this->timeout + 10)];
    }

    public function handle(ExecuteProspectingSearchQuery $service): void
    {
        $service->handle(
            ProspectingSearchQuery::query()->findOrFail($this->queryId),
            User::query()->findOrFail($this->actorUserId),
        );
    }
}
