<?php

namespace Tests\Support\AiSales;

use App\Domain\AiSales\Contracts\GitRepositoryStateInspectorInterface;
use App\Domain\AiSales\Probes\GitRepositoryState;

final class FakeGitRepositoryStateInspector implements GitRepositoryStateInspectorInterface
{
    public int $inspectCalls = 0;

    public function __construct(private readonly GitRepositoryState $state) {}

    public function inspect(string $baseCommit): GitRepositoryState
    {
        $this->inspectCalls++;

        return $this->state;
    }
}
