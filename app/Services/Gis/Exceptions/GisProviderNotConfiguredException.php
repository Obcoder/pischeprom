<?php

namespace App\Services\Gis\Exceptions;

class GisProviderNotConfiguredException extends GisException
{
    public function __construct(string $message)
    {
        parent::__construct($message, 503);
    }
}
