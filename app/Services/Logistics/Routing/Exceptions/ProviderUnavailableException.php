<?php

namespace App\Services\Logistics\Routing\Exceptions;

use Throwable;

class ProviderUnavailableException extends RoutingException
{
    public function __construct(
        string $message = 'Внутренний routing-сервис временно недоступен.',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 'provider_unavailable', true, 503, $previous);
    }
}
