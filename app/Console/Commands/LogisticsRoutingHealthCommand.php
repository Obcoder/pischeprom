<?php

namespace App\Console\Commands;

use App\Services\Logistics\Routing\Contracts\RoutingProviderInterface;
use Illuminate\Console\Command;

class LogisticsRoutingHealthCommand extends Command
{
    protected $signature = 'logistics:routing-health {--json : Print machine-readable JSON}';

    protected $description = 'Check the private automobile routing provider without exposing its internal URL';

    public function handle(RoutingProviderInterface $provider): int
    {
        $health = $provider->health();

        if ($this->option('json')) {
            $this->line(json_encode($health->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $line = sprintf(
                '%s: %s, engine=%s, osm=%s, latency=%s ms',
                $health->provider,
                $health->healthy ? 'healthy' : 'unavailable',
                $health->routingEngineVersion ?: 'unknown',
                $health->osmDataVersion ?: 'unknown',
                $health->latencyMs ?? 'unknown',
            );
            $health->healthy ? $this->info($line) : $this->error($line);

            if ($health->message) {
                $this->line($health->message);
            }
        }

        return $health->healthy ? self::SUCCESS : self::FAILURE;
    }
}
