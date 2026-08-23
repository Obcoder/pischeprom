<?php

namespace App\Domain\AiSales\Scoring;

use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Enums\ScoreFactorStatus;
use App\Domain\AiSales\Enums\UnitRoleCode;
use DomainException;

abstract class DeterministicScoringSupport
{
    protected function assertSignals(ScoringInput $input, string $level, array $expected): void
    {
        if ($input->level !== $level) {
            throw new DomainException('Scoring input is bound to the wrong level.');
        }

        $actual = array_keys($input->signals);
        sort($actual);
        sort($expected);
        if ($actual !== $expected) {
            throw new DomainException('Unknown or missing scoring input signal.');
        }
    }

    protected function assertSubject(ScoringInput $input, array $expected): void
    {
        $actual = array_keys($input->subject);
        sort($actual);
        sort($expected);
        if ($actual !== $expected) {
            throw new DomainException('Unknown or missing scoring subject binding.');
        }
        foreach ($input->subject as $value) {
            if (! is_int($value) || $value < 1) {
                throw new DomainException('Scoring subject bindings must be positive integer IDs.');
            }
        }
    }

    protected function assertBooleanSignals(array $signals, array $keys): void
    {
        foreach ($keys as $key) {
            if (! is_bool($signals[$key] ?? null)) {
                throw new DomainException('Invalid boolean scoring signal.');
            }
        }
    }

    protected function assertIntegerSignal(array $signals, string $key, int $minimum, int $maximum): void
    {
        if (! is_int($signals[$key] ?? null) || $signals[$key] < $minimum || $signals[$key] > $maximum) {
            throw new DomainException('Invalid bounded integer scoring signal.');
        }
    }

    protected function evidenceFor(ScoringInput $input, string $factorCode): ?array
    {
        $candidates = array_values(array_filter(
            $input->evidence,
            static fn (array $evidence): bool => ($evidence['factor_code'] ?? null) === $factorCode,
        ));
        if ($candidates === []) {
            return null;
        }
        usort($candidates, static function (array $left, array $right): int {
            $comparison = ((int) ($right['verified'] ?? false)) <=> ((int) ($left['verified'] ?? false));
            if ($comparison !== 0) {
                return $comparison;
            }
            $comparison = ((int) ($right['confidence'] ?? 0)) <=> ((int) ($left['confidence'] ?? 0));

            return $comparison !== 0 ? $comparison : strcmp((string) ($left['reference'] ?? ''), (string) ($right['reference'] ?? ''));
        });

        return $candidates[0];
    }

    protected function assertLaneRole(ScoringInput $input, ScoringDefinition $definition): void
    {
        $lane = BusinessLane::tryFrom((string) ($input->signals['lane'] ?? ''));
        $role = UnitRoleCode::tryFrom((string) ($input->signals['role_code'] ?? ''));
        if (! $lane || ! $role || ! in_array($lane->value, $definition->allowedLanes, true)
            || ! in_array($role->value, $definition->allowedRoles, true) || ! $role->allowsLane($lane)) {
            throw new DomainException('Scoring lane or role is not allowed by the code-owned definition.');
        }
    }

    protected function assertEvidence(ScoringInput $input, ScoringDefinition $definition): void
    {
        if (! array_is_list($input->evidence) || count($input->evidence) > 50) {
            throw new DomainException('Scoring evidence must be a bounded list.');
        }

        $allowedKeys = ['factor_code', 'type', 'reference', 'hash', 'confidence', 'verified', 'at'];
        foreach ($input->evidence as $evidence) {
            if (! is_array($evidence) || array_diff(array_keys($evidence), $allowedKeys) !== []) {
                throw new DomainException('Unknown scoring evidence field.');
            }
            $factorCode = $evidence['factor_code'] ?? null;
            $type = $evidence['type'] ?? null;
            $reference = $evidence['reference'] ?? null;
            $hash = $evidence['hash'] ?? null;
            $confidence = $evidence['confidence'] ?? null;
            $verified = $evidence['verified'] ?? null;
            $at = $evidence['at'] ?? null;
            if (! is_string($factorCode) || ! array_key_exists($factorCode, $definition->factors)
                || ! is_string($type) || $type === '' || mb_strlen($type) > 32
                || ! is_string($reference) || $reference === '' || mb_strlen($reference) > 512 || strlen($reference) > 2048
                || ! is_string($hash) || preg_match('/^[a-f0-9]{64}$/', $hash) !== 1
                || ! is_int($confidence) || $confidence < 0 || $confidence > 100
                || ! is_bool($verified)
                || ($at !== null && (! is_string($at) || mb_strlen($at) > 64))) {
                throw new DomainException('Invalid or unknown scoring evidence.');
            }
        }
    }

    protected function assertRequiredEvidence(ScoringInput $input, array $signalToFactor): void
    {
        foreach ($signalToFactor as $signal => $factorCode) {
            if (($input->signals[$signal] ?? false) === true && $this->evidenceFor($input, $factorCode) === null) {
                throw new DomainException('A positive scoring signal is missing required evidence.');
            }
        }
    }

    protected function factor(
        ScoringInput $input,
        string $code,
        string $polarity,
        int $weight,
        int $contribution,
        ScoreFactorStatus $status,
        string $state,
        string $rationale,
        int $defaultConfidence = 0,
    ): ScoreFactorResult {
        $evidence = $this->evidenceFor($input, $code);
        $confidence = (int) ($evidence['confidence'] ?? $defaultConfidence);
        if ($evidence !== null && ! ($evidence['verified'] ?? false)) {
            $confidence = min($confidence, 39);
        }

        return new ScoreFactorResult(
            $code,
            $polarity,
            $state,
            $weight,
            $contribution,
            max(0, min(100, $confidence)),
            $status,
            $rationale,
            isset($evidence['type']) ? (string) $evidence['type'] : null,
            isset($evidence['reference']) ? (string) $evidence['reference'] : null,
            isset($evidence['hash']) ? (string) $evidence['hash'] : null,
            isset($evidence['at']) ? (string) $evidence['at'] : null,
        );
    }

    protected function confidence(array $factors, int $penalty = 0): int
    {
        $values = array_values(array_map(
            static fn (ScoreFactorResult $factor): int => $factor->confidence,
            array_filter($factors, static fn (ScoreFactorResult $factor): bool => $factor->status === ScoreFactorStatus::Applied && $factor->contribution > 0),
        ));

        return max(0, min(100, ($values === [] ? 0 : (int) round(array_sum($values) / count($values))) - $penalty));
    }

    protected function clamped(int $score): int
    {
        return max(0, min(100, $score));
    }
}
