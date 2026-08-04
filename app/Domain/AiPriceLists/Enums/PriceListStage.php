<?php

namespace App\Domain\AiPriceLists\Enums;

enum PriceListStage: string
{
    case Ingest = 'ingest';
    case Validate = 'validate';
    case Classify = 'classify';
    case Extract = 'extract';
    case Ocr = 'ocr';
    case Normalize = 'normalize';
    case Match = 'match';
    case Finalize = 'finalize';
    case Apply = 'apply';
}
