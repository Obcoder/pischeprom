<?php

namespace App\Services\Logistics\Routing\DTO;

final readonly class RoutingHealth
{
    public function __construct(
        public bool $healthy,
        public string $provider,
        public ?string $routingEngineVersion,
        public ?string $osmDataVersion,
        public ?int $latencyMs,
        public ?string $message = null,
    ) {}

    public function toArray(): array
    {
        return [
            'healthy' => $this->healthy,
            'provider' => $this->provider,
            'routing_engine_version' => $this->routingEngineVersion,
            'osm_data_version' => $this->osmDataVersion,
            'latency_ms' => $this->latencyMs,
            'message' => $this->message,
        ];
    }
}
