<?php

namespace App\Domain\AiSales\Scoring;

use App\Domain\AiSales\Support\AiCanonicalJson;

final readonly class ScoringDefinition
{
    public string $hash;

    public function __construct(
        public string $code,
        public string $version,
        public array $factors,
        public array $caps,
        public array $normalization,
        public array $bands,
        public array $confidenceRules,
        public array $stalePolicy,
        public array $allowedLanes,
        public array $allowedRoles,
        public array $explanationTemplates,
    ) {
        $this->hash = AiCanonicalJson::hash($this->safeArray(includeHash: false));
    }

    public function safeArray(bool $includeHash = true): array
    {
        $value = [
            'code' => $this->code,
            'version' => $this->version,
            'factors' => $this->factors,
            'caps' => $this->caps,
            'normalization' => $this->normalization,
            'bands' => $this->bands,
            'confidence_rules' => $this->confidenceRules,
            'stale_policy' => $this->stalePolicy,
            'allowed_lanes' => $this->allowedLanes,
            'allowed_roles' => $this->allowedRoles,
            'explanation_templates' => $this->explanationTemplates,
        ];

        if ($includeHash) {
            $value['hash'] = $this->hash;
        }

        return $value;
    }
}
