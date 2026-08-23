<?php

namespace App\Jobs\AiSales;

use App\Domain\AiSales\Campaigns\AdvanceClientAcquisitionCampaignRun;
use App\Models\AiAgentRun;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

final class ExecuteClientAcquisitionCampaignRunJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 180;

    public int $uniqueFor = 300;

    public bool $failOnTimeout = true;

    public function __construct(
        public readonly int $runId,
        public readonly int $actorUserId,
    ) {
        $this->onConnection((string) config('ai-sales.queue.connection', 'sync'));
        $this->onQueue((string) config('ai-sales.queue.name', 'ai-sales'));
    }

    public function uniqueId(): string
    {
        return 'ai-sales-campaign-run:'.$this->runId;
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->uniqueId()))->dontRelease()->expireAfter($this->timeout + 30)];
    }

    public function handle(AdvanceClientAcquisitionCampaignRun $service): void
    {
        $run = AiAgentRun::query()->find($this->runId);
        $actor = User::query()->find($this->actorUserId);
        if (! $run || ! $actor || $run->status->terminal()) {
            return;
        }

        $service->handle($run, $actor);
    }
}
