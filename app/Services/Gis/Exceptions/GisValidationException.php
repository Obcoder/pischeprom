<?php

namespace App\Services\Gis\Exceptions;

class GisValidationException extends GisException
{
    public function __construct(string $message)
    {
        parent::__construct($message, 422);
    }
}
