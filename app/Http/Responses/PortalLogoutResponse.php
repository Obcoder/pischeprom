<?php

namespace App\Http\Responses;

use Inertia\Inertia;
use Laravel\Fortify\Http\Responses\LogoutResponse;

class PortalLogoutResponse extends LogoutResponse
{
    public function toResponse($request)
    {
        // Runs after session invalidation so the redirect can clear encrypted
        // cabinet history before another person uses this browser session.
        Inertia::clearHistory();

        return parent::toResponse($request);
    }
}
