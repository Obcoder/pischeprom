<?php

namespace App\Domain\AiSales\Enums;

enum AiProviderErrorCategory: string
{
    case BadRequest = 'bad_request';
    case Authentication = 'authentication';
    case InsufficientBalance = 'insufficient_balance';
    case NotFound = 'not_found';
    case Conflict = 'conflict';
    case Unprocessable = 'unprocessable';
    case Timeout = 'timeout';
    case Network = 'network';
    case Tls = 'tls';
    case Dns = 'dns';
    case RateLimited = 'rate_limited';
    case ServerError = 'server_error';
    case InvalidResponse = 'invalid_response';
    case OversizedResponse = 'oversized_response';
    case UnsupportedEndpoint = 'unsupported_endpoint';
    case SchemaMismatch = 'schema_mismatch';
    case DlpBlocked = 'dlp_blocked';
    case ContourBlocked = 'contour_blocked';
    case ProviderUnavailable = 'provider_unavailable';
    case CapabilityMissing = 'capability_missing';
    case PolicyBlocked = 'policy_blocked';
}
