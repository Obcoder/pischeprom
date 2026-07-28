<?php

namespace App\Services\Logistics\Routing\Exceptions;

use Throwable;

class RoutingQueueUnavailableException extends RoutingException
{
    public function __construct(
        string $message = 'Очередь расчёта маршрутов временно недоступна. Повторите попытку позже.',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 'routing_queue_unavailable', true, 503, $previous);
    }
}
