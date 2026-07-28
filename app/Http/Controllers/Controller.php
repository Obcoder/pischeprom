<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Gate;

abstract class Controller
{
    protected function authorizeLogistics(string $ability, mixed $arguments = []): void
    {
        if (! config('logistics.authorization_enabled')) {
            return;
        }

        Gate::authorize($ability, $arguments);
    }
}
