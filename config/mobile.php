<?php

return [
    // An employee signs in again after the work shift. Hard limit: seven days.
    'token_ttl_minutes' => (int) env('MOBILE_TOKEN_TTL_MINUTES', 480),
];
