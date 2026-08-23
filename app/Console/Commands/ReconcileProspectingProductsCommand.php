<?php

namespace App\Console\Commands;

use App\Domain\AiSales\Services\ProspectingProductReconciliationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ReconcileProspectingProductsCommand extends Command
{
    protected $signature = 'ai-sales:reconcile-prospecting-products
        {--apply : Apply deterministic Product-first reconciliation; default is dry-run}
        {--yes : Confirm the explicit apply operation}
        {--chunk=100 : Rows per chunk, 1..500}';

    protected $description = 'Dry-run or reconcile committed Good-first prospecting rows to Product-first relations';

    public function handle(ProspectingProductReconciliationService $reconciliation): int
    {
        $chunk = filter_var($this->option('chunk'), FILTER_VALIDATE_INT);
        if ($chunk === false || $chunk < 1 || $chunk > 500) {
            $this->error('--chunk must be an integer from 1 to 500.');

            return self::INVALID;
        }
        $apply = (bool) $this->option('apply');
        if ($apply && ! (bool) $this->option('yes')) {
            $this->error('Blocked: --apply requires explicit --yes confirmation.');

            return self::FAILURE;
        }
        if ($apply && ! app()->environment(['local', 'testing', 'staging'])) {
            $this->error('Blocked: apply is allowed only in local, testing, or staging.');

            return self::FAILURE;
        }
        $connection = DB::connection();
        $database = (string) $connection->getDatabaseName();
        $this->line('APP_ENV='.app()->environment());
        $this->line('DB_DRIVER='.$connection->getDriverName());
        $this->line('DB_DATABASE='.($database === ':memory:' ? ':memory:' : basename($database)));

        try {
            $report = $reconciliation->reconcile($apply, $chunk);
        } catch (Throwable $exception) {
            $this->error('Blocked safely: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->table(array_keys($report), [array_values($report)]);
        $this->comment('Output contains counters only; no Product, Good, Unit, Entity, prompt, key, or provider values.');

        return self::SUCCESS;
    }
}
