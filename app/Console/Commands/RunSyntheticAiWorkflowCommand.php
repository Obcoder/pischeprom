<?php

namespace App\Console\Commands;

use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Workflows\AiWorkflowExecutionContext;
use App\Domain\AiSales\Workflows\AiWorkflowExecutor;
use App\Models\AiAgentRun;
use Illuminate\Console\Command;

class RunSyntheticAiWorkflowCommand extends Command
{
    protected $signature = 'ai-sales:run-synthetic-workflow
        {run : Existing prepared AI run public UUID}
        {--idempotency=stage07-synthetic-cli : Bounded caller idempotency reference}';

    protected $description = 'Run the repository-owned Stage 07 workflow through fake providers only';

    public function handle(AiWorkflowExecutor $executor): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Blocked: synthetic workflow CLI is restricted to local/testing environments.');

            return self::FAILURE;
        }

        $run = AiAgentRun::query()
            ->where('public_id', (string) $this->argument('run'))
            ->first();

        if (! $run) {
            $this->error('Blocked: prepared synthetic run was not found.');

            return self::FAILURE;
        }

        $step = $run->steps()->where('sequence', 1)->first();

        if (! $step) {
            $this->error('Blocked: prepared synthetic run step was not found.');

            return self::FAILURE;
        }

        try {
            $result = $executor->execute(new AiWorkflowExecutionContext(
                $run->id,
                $step->id,
                $run->initiator_user_id,
                $run->lock_version,
                (string) $this->option('idempotency'),
            ));
        } catch (PolicyViolation $violation) {
            $this->error('Blocked safely: '.$violation->errorCode);

            return self::FAILURE;
        }

        $this->info(sprintf(
            '%s %s:%s; tools=%d rows=%d bytes=%d duration_ms=%d replayed=%s',
            $result->status,
            $result->workflowCode,
            $result->workflowVersion,
            $result->toolCallCount,
            $result->rowCount,
            $result->byteCount,
            $result->durationMs,
            $result->replayed ? 'yes' : 'no',
        ));

        return self::SUCCESS;
    }
}
