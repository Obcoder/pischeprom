<?php

namespace App\Services\Logistics\Routing\Exceptions;

class MalformedRoutingResponseException extends RoutingException
{
    public function __construct(string $message = 'Routing-сервис вернул некорректный ответ.')
    {
        parent::__construct($message, 'malformed_response', false, 502);
    }
}
