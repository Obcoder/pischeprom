<?php

namespace App\Console\Commands;

use App\Domain\AiSales\Scoring\ProspectingScoreRecalculationService;
use App\Models\UnitBusinessContext;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

class RecalculateProspectingScoresCommand extends Command
{
    protected $signature = 'ai-sales:recalculate-prospecting-scores
        {--user-id= : Authorized reviewer User ID}
        {--context-id= : Optional exact UnitBusinessContext ID}
        {--status=active : Context status selector}
        {--chunk=100 : Contexts per chunk, 1..500}
        {--apply : Persist append-only snapshots; default is dry-run}
        {--yes : Confirm explicit apply}';

    protected $description = 'Dry-run or deterministically recalculate context-bound prospecting scores without HTTP';

    public function handle(ProspectingScoreRecalculationService $recalculation): int
    {
        Http::preventStrayRequests();
        if (app()->environment('production')) {
            $this->error('Blocked: Stage 10 recalculation never runs in production.');

            return self::FAILURE;
        }
        if (! app()->environment(['local', 'testing', 'staging'])) {
            $this->error('Blocked: Stage 10 recalculation is restricted to local/testing/staging.');

            return self::FAILURE;
        }
        $chunk = filter_var($this->option('chunk'), FILTER_VALIDATE_INT);
        $userId = filter_var($this->option('user-id'), FILTER_VALIDATE_INT);
        if ($chunk === false || $chunk < 1 || $chunk > 500 || $userId === false || $userId < 1) {
            $this->error('--user-id is required and --chunk must be 1..500.');

            return self::INVALID;
        }
        $apply = (bool) $this->option('apply');
        if ($apply && ! (bool) $this->option('yes')) {
            $this->error('Blocked: --apply requires --yes and local/testing/staging.');

            return self::FAILURE;
        }
        $actor = User::query()->find($userId);
        if (! $actor) {
            $this->error('Authorized reviewer was not found.');

            return self::FAILURE;
        }

        $connection = DB::connection();
        $this->line('APP_ENV='.app()->environment());
        $this->line('DB_DRIVER='.$connection->getDriverName());
        $this->line('DB_DATABASE='.basename((string) $connection->getDatabaseName()));
        $counters = ['contexts' => 0, 'products' => 0, 'goods' => 0, 'priorities' => 0, 'blocked' => 0];
        $query = UnitBusinessContext::query()->select(['id', 'unit_id', 'lane', 'role_code', 'status'])
            ->where('status', (string) $this->option('status'));
        if ($this->option('context-id') !== null) {
            $query->where('id', (int) $this->option('context-id'));
        }
        $query->orderBy('id')->chunkById($chunk, function ($contexts) use ($recalculation, $actor, $apply, &$counters): void {
            foreach ($contexts as $context) {
                try {
                    foreach ($context->productMatches()->select(['id', 'unit_id', 'unit_business_context_id'])->orderBy('id')->limit(100)->get() as $match) {
                        $recalculation->product($actor, $match, $apply);
                        $counters['products']++;
                    }
                    foreach ($context->goodMatches()->select(['id', 'unit_id', 'unit_business_context_id'])->orderBy('id')->limit(100)->get() as $match) {
                        $recalculation->good($actor, $match, $apply);
                        $counters['goods']++;
                    }
                    $recalculation->priority($actor, $context, $apply);
                    $counters['priorities']++;
                    $counters['contexts']++;
                } catch (Throwable) {
                    $counters['blocked']++;
                }
            }
        }, 'id');
        $this->table(array_keys($counters), [array_values($counters)]);
        $this->comment(($apply ? 'Applied' : 'Dry-run').'; HTTP=0; output contains counters only.');

        return $counters['blocked'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
