<?php

namespace App\Domain\AiSales\Enums;

enum FakeAiProviderScenario: string
{
    case Normal = 'normal';
    case StructuredOutput = 'structured_output';
    case FunctionCall = 'function_call';
    case Timeout = 'timeout';
    case RateLimited = 'rate_limited';
    case ServerError = 'server_error';
    case SchemaMismatch = 'schema_mismatch';
    case DlpBlock = 'dlp_block';
    case ContourBlock = 'contour_block';
    case ProviderUnavailable = 'provider_unavailable';
}
