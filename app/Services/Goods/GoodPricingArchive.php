<?php

namespace App\Services\Goods;

use App\Models\Currency;
use App\Models\Entity;
use App\Models\Good;
use App\Models\GoodPriceHistory;
use App\Models\GoodPricingCalculation;
use App\Models\PriceType;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class GoodPricingArchive
{
    public function record(Good $good, array $input, array $result, ?GoodPriceHistory $history = null): GoodPricingCalculation
    {
        $scope = $input['scope'] ?? 'common';
        if (! in_array($scope, ['common', 'entity'], true)) {
            throw ValidationException::withMessages(['scope' => 'Выберите назначение расчёта.']);
        }
        if ($history && ((int) $history->good_id !== (int) $good->id || $history->scope !== $scope)) {
            throw new \LogicException('The calculation and its price history must have the same product and scope.');
        }
        if ($history && $existing = GoodPricingCalculation::where('price_history_id', $history->id)->first()) {
            return $existing;
        }
        $targetId = $input['target_id'] ?? null;
        $target = $scope === 'entity'
            ? Entity::without(['buildings', 'classification', 'country'])->find($targetId)
            : ($targetId ? PriceType::find($targetId) : null);
        if ($scope === 'entity' && ! $target) {
            throw ValidationException::withMessages(['target_id' => 'Для закрытого расчёта выберите Entity.']);
        }
        $currency = Currency::findOrFail($result['currency_id']);
        $measurement = $result['measurement'] ?? $input['measurement'] ?? $good->measurement();
        $snapshot = [...$input, 'scope' => $scope, 'target_id' => $target?->id,
            'target_name' => $target?->name, 'measurement' => $measurement];
        $attributes = [
            'good_id' => $good->id,
            'scope' => $scope,
            'entity_id' => $scope === 'entity' ? $target->id : null,
            'target_name' => $target?->name,
            'name' => mb_substr(trim($input['calculation_name'] ?? $input['name'] ?? '') ?: 'Расчёт'.($target ? ' · '.$target->name : ''), 0, 255),
            'comment' => $input['comment'] ?? null,
            'currency_id' => $currency->id,
            'currency_code' => $currency->code,
            'measurement' => $measurement,
            'input' => $snapshot,
            'result' => $result,
            'price_net' => $result['price_net'],
            'price_gross' => $result['price_gross'],
            'created_by' => auth()->id(),
            'created_by_name' => auth()->user()?->name,
            'created_at' => now(),
        ];

        return $history
            ? GoodPricingCalculation::firstOrCreate(['price_history_id' => $history->id], $attributes)
            : GoodPricingCalculation::create($attributes);
    }

    public function listing(Good $good): Collection
    {
        return GoodPricingCalculation::where('good_id', $good->id)->orderByDesc('created_at')->orderByDesc('id')->get()
            ->map(fn (GoodPricingCalculation $calculation) => $this->toDto($calculation));
    }

    public function toDto(GoodPricingCalculation $calculation): array
    {
        return $calculation->workspaceData();
    }
}
