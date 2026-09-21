<?php

return [
    // An employee signs in again after the work shift. Hard limit: seven days.
    'token_ttl_minutes' => (int) env('MOBILE_TOKEN_TTL_MINUTES', 480),

    // Temporary employee-order exception. Set false to restore strict mobile stock checks.
    // It does not change stock enforcement in the desktop sales or warehouse interfaces.
    'allow_negative_stock' => (bool) env('MOBILE_ALLOW_NEGATIVE_STOCK', true),
];
