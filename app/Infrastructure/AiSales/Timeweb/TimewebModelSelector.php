<?php

namespace App\Infrastructure\AiSales\Timeweb;

use App\Domain\AiSales\Enums\AiModelProfile;
use App\Domain\AiSales\Enums\AiProviderRoute;
use App\Domain\AiSales\Exceptions\PolicyViolation;

class TimewebModelSelector
{
    public function modelForProfile(AiProviderRoute $route, AiModelProfile $profile): string
    {
        if ($route === AiProviderRoute::LocalRu) {
            $model = $this->allowedModels($route)[0] ?? null;
        } else {
            $logical = match ($profile) {
                AiModelProfile::HighVolumeExtraction, AiModelProfile::Validation => 'luna',
                AiModelProfile::StandardResearch, AiModelProfile::OutreachDrafting, AiModelProfile::ReplyTriage => 'terra',
                AiModelProfile::ComplexResearch => 'sol',
            };
            $model = config("ai-sales.providers.timeweb.routes.external_sanitized.models.{$logical}");
        }

        if (! is_string($model) || ! $this->validModelId($model) || ! in_array($model, $this->allowedModels($route), true)) {
            throw new PolicyViolation('timeweb_model_mapping_missing', 'The logical profile has no exact server-side Timeweb model mapping.');
        }

        return $model;
    }

    public function assertAllowed(AiProviderRoute $route, string $modelId): void
    {
        if (! $this->validModelId($modelId) || ! in_array($modelId, $this->allowedModels($route), true)) {
            throw new PolicyViolation('timeweb_model_not_allowlisted', 'The exact Timeweb model is not in the server-side route allowlist.');
        }
    }

    public function allowedModels(AiProviderRoute $route): array
    {
        $configured = $route === AiProviderRoute::LocalRu
            ? (array) config('ai-sales.providers.timeweb.routes.local_ru.model_ids', [])
            : array_values((array) config('ai-sales.providers.timeweb.routes.external_sanitized.models', []));

        $models = [];

        foreach ($configured as $model) {
            if (is_string($model) && $this->validModelId($model)) {
                $models[$model] = $model;
            }
        }

        return array_values($models);
    }

    private function validModelId(string $modelId): bool
    {
        if ($modelId === '' || strlen($modelId) > 191
            || preg_match('/^[A-Za-z0-9][A-Za-z0-9._:\/-]*$/', $modelId) !== 1
            || preg_match('/(?:password|api[_-]?key|authorization|cookie|session|private[_-]?key|access[_-]?token)/i', $modelId) === 1
            || preg_match('/^(?:sk-|eyJ[A-Za-z0-9_-]{10,}\.)/', $modelId) === 1) {
            return false;
        }

        foreach (AiProviderRoute::cases() as $route) {
            $key = config("ai-sales.providers.timeweb.routes.{$route->value}.api_key");

            if (is_string($key) && $key !== '' && hash_equals($key, $modelId)) {
                return false;
            }
        }

        return true;
    }
}
