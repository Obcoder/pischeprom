<?php

namespace App\Domain\AiSales\Contracts;

use App\Domain\AiSales\Probes\GitRepositoryState;

interface GitRepositoryStateInspectorInterface
{
    public function inspect(string $baseCommit): GitRepositoryState;
}
