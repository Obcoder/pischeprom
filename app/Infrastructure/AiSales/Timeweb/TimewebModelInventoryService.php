<?php

namespace App\Infrastructure\AiSales\Timeweb;

use App\Domain\AiSales\Enums\AiProviderEndpointProfile;
use App\Domain\AiSales\Enums\AiProviderRoute;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Models\AiProviderModel;
use Illuminate\Support\Facades\DB;

class TimewebModelInventoryService
{
    public function __construct(private readonly TimewebAiGatewayTransport $transport) {}

    public function sync(
        AiProviderRoute $route,
        bool $apply,
        string $operatorReference,
        TimewebProbeBudgetGuard $budget,
    ): TimewebModelInventorySyncResult {
        $operatorReference = $this->operatorReference($operatorReference);
        $budget->reserve(0, 0, '0.0000');
        $wire = $this->transport->listModels($route, $budget->remainingTimeoutSeconds());
        $items = $this->normalize($wire->data);
        $modelIds = array_map(static fn (TimewebModelInventoryItem $item): string => $item->modelId, $items);
        sort($modelIds);
        $existing = AiProviderModel::query()
            ->where('provider_code', 'timeweb')
            ->where('provider_route', $route->value)
            ->get()
            ->keyBy('model_id');
        $created = collect($modelIds)->reject(fn (string $id): bool => $existing->has($id))->count();
        $updated = count($modelIds) - $created;
        $markedInactive = $existing->where('active_in_inventory', true)->keys()->diff($modelIds)->count();
        $resultHash = hash('sha256', json_encode([
            'route' => $route->value,
            'models' => $modelIds,
            'request_id' => $wire->requestId,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        if ($apply) {
            DB::transaction(function () use ($route, $operatorReference, $wire, $items, $modelIds): void {
                $now = now();
                AiProviderModel::query()
                    ->where('provider_code', 'timeweb')
                    ->where('provider_route', $route->value)
                    ->whereNotIn('model_id', $modelIds === [] ? ['__none__'] : $modelIds)
                    ->update([
                        'active_in_inventory' => false,
                        'updated_by_reference' => $operatorReference,
                        'updated_at' => $now,
                    ]);

                foreach ($items as $item) {
                    $record = AiProviderModel::query()->firstOrNew([
                        'provider_code' => 'timeweb',
                        'provider_route' => $route->value,
                        'model_id' => $item->modelId,
                    ]);
                    $isNew = ! $record->exists;
                    $record->fill([
                        'display_label' => $item->displayLabel,
                        'active_in_inventory' => true,
                        'first_seen_at' => $isNew ? $now : $record->first_seen_at,
                        'last_seen_at' => $now,
                        'safe_metadata' => $item->safeMetadata,
                        'source_reference' => 'timeweb:/v1/models#'.($wire->requestId ?? substr($item->metadataHash, 0, 16)),
                        'metadata_hash' => $item->metadataHash,
                        'created_by_reference' => $isNew ? $operatorReference : $record->created_by_reference,
                        'updated_by_reference' => $operatorReference,
                    ]);

                    if ($isNew) {
                        $record->endpoint_profile = AiProviderEndpointProfile::Unsupported;
                    }

                    $record->save();
                }
            }, 3);
        }

        return new TimewebModelInventorySyncResult(
            $route->value,
            $apply,
            count($items),
            $created,
            $updated,
            $markedInactive,
            $modelIds,
            $wire->requestId,
            $resultHash,
            $budget->summary(),
        );
    }

    /** @return list<TimewebModelInventoryItem> */
    private function normalize(array $payload): array
    {
        $rows = $payload['data'] ?? null;

        if (! is_array($rows) || count($rows) > 1000) {
            throw new TimewebTransportException(
                \App\Domain\AiSales\Enums\AiProviderErrorCategory::InvalidResponse,
                'timeweb_models_shape_invalid',
            );
        }

        $items = [];

        foreach ($rows as $row) {
            if (! is_array($row) || ! is_string($row['id'] ?? null)) {
                throw new TimewebTransportException(
                    \App\Domain\AiSales\Enums\AiProviderErrorCategory::InvalidResponse,
                    'timeweb_model_item_invalid',
                );
            }

            $modelId = $row['id'];

            if (! $this->safeModelId($modelId)) {
                throw new TimewebTransportException(
                    \App\Domain\AiSales\Enums\AiProviderErrorCategory::InvalidResponse,
                    'timeweb_model_id_invalid',
                );
            }

            $metadata = [
                'object' => $this->safeMetadataToken($row['object'] ?? null, 64),
                'owned_by' => $this->safeMetadataToken($row['owned_by'] ?? null, 96),
                'created' => is_int($row['created'] ?? null) ? $row['created'] : null,
            ];
            $metadata = array_filter($metadata, static fn (mixed $value): bool => $value !== null);
            ksort($metadata);
            $hash = hash('sha256', json_encode([
                'model_id' => $modelId,
                'metadata' => $metadata,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            $items[$modelId] = new TimewebModelInventoryItem($modelId, $modelId, $metadata, $hash);
        }

        ksort($items);

        return array_values($items);
    }

    private function operatorReference(string $value): string
    {
        $value = trim($value);

        if ($value === '' || mb_strlen($value) > 128
            || preg_match('/^[A-Za-z0-9._:@-]+$/', $value) !== 1
            || preg_match('/password|token|api[_-]?key|authorization|cookie|session|private/i', $value) === 1
            || $this->containsConfiguredKey($value)) {
            throw new PolicyViolation('timeweb_operator_reference_invalid', 'A bounded non-secret operator reference is required.');
        }

        return $value;
    }

    private function safeMetadataToken(mixed $value, int $maxLength): ?string
    {
        if (! is_string($value) || $value === '' || mb_strlen($value) > $maxLength
            || preg_match('/^[A-Za-z0-9._:\/@ -]+$/D', $value) !== 1
            || preg_match('/password|token|api[_-]?key|authorization|cookie|session/i', $value) === 1) {
            return null;
        }

        return $value;
    }

    private function safeModelId(string $modelId): bool
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

    private function containsConfiguredKey(string $value): bool
    {
        foreach (AiProviderRoute::cases() as $route) {
            $key = config("ai-sales.providers.timeweb.routes.{$route->value}.api_key");

            if (is_string($key) && $key !== '' && hash_equals($key, $value)) {
                return true;
            }
        }

        return false;
    }
}
