<?php

namespace App\Domain\Banking\DTO;

use Illuminate\Support\Collection;

final readonly class MatchResult
{
    /**
     * @param  Collection<int, MatchCandidate>  $candidates
     */
    public function __construct(
        public Collection $candidates,
        public ?MatchCandidate $automaticCandidate,
    ) {}
}
