<?php

namespace App\Jobs\AiSales;

use App\Domain\AiSales\Enums\AiRunStatus;
use App\Domain\AiSales\Runs\ExecuteAiAgentRunStep;
use App\Domain\AiSales\Runs\PrepareAiAgentRun;
use App\Models\AiAgentRun;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class ExecuteAiAgentRunJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 90;

    public int $uniqueFor = 300;

    public function __construct(public readonly int $runId)
    {
        $this->onConnection((string) config('ai-sales.queue.connection', 'sync'));
        $this->onQueue((string) config('ai-sales.queue.name', 'ai-sales'));
    }

    public function uniqueId(): string
    {
        return 'ai-sales-run:'.$this->runId;
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->uniqueId()))->expireAfter($this->timeout + 30)];
    }

    public function handle(PrepareAiAgentRun $prepare, ExecuteAiAgentRunStep $execute): void
    {
        $run = AiAgentRun::query()->find($this->runId);

        if (! $run || $run->status->terminal()) {
            return;
        }

        if ($run->status === AiRunStatus::Queued) {
            $run = $prepare->handle($run);
        }

        if ($run->status === AiRunStatus::Ready) {
            $execute->handle($run);
        }
    }
}
