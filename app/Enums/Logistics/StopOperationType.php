<?php

namespace App\Enums\Logistics;

enum StopOperationType: string
{
    case Loading = 'loading';
    case Unloading = 'unloading';
    case LoadingUnloading = 'loading_unloading';
    case Technical = 'technical';
}
