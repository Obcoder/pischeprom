<?php

namespace App\Domain\AiSales\Enums;

enum AiProviderEndpointProfile: string
{
    case Responses = 'responses';
    case ChatCompletions = 'chat_completions';
    case Unsupported = 'unsupported';
}
