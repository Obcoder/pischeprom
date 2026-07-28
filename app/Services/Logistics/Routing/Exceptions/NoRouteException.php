<?php

namespace App\Services\Logistics\Routing\Exceptions;

class NoRouteException extends RoutingException
{
    public function __construct(string $message = 'Автомобильный маршрут между выбранными точками не найден.')
    {
        parent::__construct($message, 'no_route', false, 422);
    }
}
