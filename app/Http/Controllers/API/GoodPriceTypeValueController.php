<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Good;
use App\Models\GoodPriceCalculation;
use App\Models\GoodPriceTypeValue;
use App\Models\PriceType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoodPriceTypeValueController extends Controller
{
    public function index(Good $good): JsonResponse
    {
        $values = $good->priceTypeValues()
            ->with([
                       'priceType.currency',
                       'currency',
                       'calculation.currency',
                       'calculation.priceType',
                   ])
            ->get()
            ->sortBy([
                         fn ($a, $b) => ($a->priceType?->sort_order ?? 100) <=> ($b->priceType?->sort_order ?? 100),
                         fn ($a, $b) => strcmp((string) ($a->priceType?->name ?? ''), (string) ($b->priceType?->name ?? '')),
                     ])
            ->values();

        return response()->json($values);
    }

    public function store(Request $request, Good $good): JsonResponse
    {
        $validated = $request->validate([
                                            'price_type_id' => ['required', 'integer', 'exists:price_types,id'],
                                            'calculation_id' => ['nullable', 'integer', 'exists:good_price_calculations,id'],
                                            'currency_id' => ['nullable', 'integer', 'exists:currencies,id'],

                                            'price_net' => ['nullable', 'numeric', 'min:0'],
                                            'price_gross' => ['nullable', 'numeric', 'min:0'],
                                            'vat_rate' => ['nullable', 'numeric', 'min:0'],

                                            'is_manual' => ['nullable', 'boolean'],
                                            'manual_comment' => ['nullable', 'string'],
                                            'is_published' => ['nullable', 'boolean'],

                                            'valid_from' => ['nullable', 'date'],
                                            'valid_to' => ['nullable', 'date'],
                                        ]);

        $priceType = PriceType::findOrFail($validated['price_type_id']);

        $calculation = null;

        if (!empty($validated['calculation_id'])) {
            $calculation = GoodPriceCalculation::query()
                ->where('good_id', $good->id)
                ->findOrFail($validated['calculation_id']);
        }

        $vatRate = $validated['vat_rate']
            ?? $good->vatRate?->rate
            ?? 20;

        $priceNet = $validated['price_net']
            ?? $calculation?->sale_net_per_kg;

        $priceGross = $validated['price_gross']
            ?? $calculation?->sale_gross_per_kg;

        if ($priceGross === null && $priceNet !== null) {
            $priceGross = $this->grossFromNet((float) $priceNet, (float) $vatRate);
        }

        if ($priceNet === null && $priceGross !== null) {
            $priceNet = $this->netFromGross((float) $priceGross, (float) $vatRate);
        }

        if ($priceGross !== null && $priceType->rounding_step) {
            $priceGross = $this->roundUpToStep((float) $priceGross, (float) $priceType->rounding_step);
            $priceNet = $this->netFromGross((float) $priceGross, (float) $vatRate);
        }

        $value = GoodPriceTypeValue::updateOrCreate(
            [
                'good_id' => $good->id,
                'price_type_id' => $priceType->id,
            ],
            [
                'calculation_id' => $calculation?->id,
                'currency_id' => $validated['currency_id']
                    ?? $calculation?->currency_id
                        ?? $priceType->currency_id,

                'price_net' => $priceNet,
                'price_gross' => $priceGross,
                'vat_rate' => $vatRate,

                'is_manual' => $validated['is_manual'] ?? false,
                'manual_comment' => $validated['manual_comment'] ?? null,
                'is_published' => $validated['is_published'] ?? $priceType->is_public,

                'valid_from' => $validated['valid_from'] ?? null,
                'valid_to' => $validated['valid_to'] ?? null,
            ]
        );

        return response()->json(
            $value->fresh([
                              'priceType.currency',
                              'currency',
                              'calculation.currency',
                              'calculation.priceType',
                          ]),
            201
        );
    }

    public function update(Request $request, Good $good, GoodPriceTypeValue $value): JsonResponse
    {
        abort_unless($value->good_id === $good->id, 404);

        $validated = $request->validate([
                                            'currency_id' => ['nullable', 'integer', 'exists:currencies,id'],

                                            'price_net' => ['nullable', 'numeric', 'min:0'],
                                            'price_gross' => ['nullable', 'numeric', 'min:0'],
                                            'vat_rate' => ['nullable', 'numeric', 'min:0'],

                                            'is_manual' => ['nullable', 'boolean'],
                                            'manual_comment' => ['nullable', 'string'],
                                            'is_published' => ['nullable', 'boolean'],

                                            'valid_from' => ['nullable', 'date'],
                                            'valid_to' => ['nullable', 'date'],
                                        ]);

        $vatRate = $validated['vat_rate'] ?? $value->vat_rate ?? $good->vatRate?->rate ?? 20;

        $priceNet = array_key_exists('price_net', $validated)
            ? $validated['price_net']
            : $value->price_net;

        $priceGross = array_key_exists('price_gross', $validated)
            ? $validated['price_gross']
            : $value->price_gross;

        if ($priceGross === null && $priceNet !== null) {
            $priceGross = $this->grossFromNet((float) $priceNet, (float) $vatRate);
        }

        if ($priceNet === null && $priceGross !== null) {
            $priceNet = $this->netFromGross((float) $priceGross, (float) $vatRate);
        }

        $value->update([
                           ...$validated,
                           'price_net' => $priceNet,
                           'price_gross' => $priceGross,
                           'vat_rate' => $vatRate,
                       ]);

        return response()->json(
            $value->fresh([
                              'priceType.currency',
                              'currency',
                              'calculation.currency',
                              'calculation.priceType',
                          ])
        );
    }

    public function destroy(Good $good, GoodPriceTypeValue $value): JsonResponse
    {
        abort_unless($value->good_id === $good->id, 404);

        $value->delete();

        return response()->json(null, 204);
    }

    private function grossFromNet(float $net, float $vatRate): float
    {
        return round($net * (1 + ($vatRate / 100)), 4);
    }

    private function netFromGross(float $gross, float $vatRate): float
    {
        if ($vatRate <= 0) {
            return round($gross, 4);
        }

        return round($gross / (1 + ($vatRate / 100)), 4);
    }

    private function roundUpToStep(float $value, float $step): float
    {
        if ($step <= 0) {
            return round($value, 4);
        }

        return ceil($value / $step) * $step;
    }
}
