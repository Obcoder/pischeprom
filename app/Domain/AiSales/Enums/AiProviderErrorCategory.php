<?php

namespace App\Domain\AiSales\Enums;

enum AiProviderErrorCategory: string
{
    case Timeout = 'timeout';
    case RateLimited = 'rate_limited';
    case ServerError = 'server_error';
    case SchemaMismatch = 'schema_mismatch';
    case DlpBlocked = 'dlp_blocked';
    case ContourBlocked = 'contour_blocked';
    case ProviderUnavailable = 'provider_unavailable';
    case CapabilityMissing = 'capability_missing';
    case PolicyBlocked = 'policy_blocked';
}
