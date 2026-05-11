<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Good;
use App\Models\GoodPriceCalculation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoodPriceCalculationController extends Controller
{
    public function index(Good $good)
    {
        return response()->json(
            $good->priceCalculations()
                ->with([
                           'currency',
                           'priceType',
                           'quotation.unit',
                           'purchase.entity',
                       ])
                ->latest()
                ->get()
        );
    }

    public function store(Request $request, Good $good): JsonResponse
    {
        $validated = $request->validate([
                                            'purchase_id' => ['nullable', 'integer', 'exists:purchases,id'],
                                            'quotation_id' => ['nullable', 'integer', 'exists:quotations,id'],
                                            'price_type_id' => ['nullable', 'integer', 'exists:price_types,id'],
                                            'formula_id' => ['nullable', 'integer', 'exists:good_price_formulas,id'],
                                            'currency_id' => ['nullable', 'integer', 'exists:currencies,id'],

                                            'name' => ['nullable', 'string', 'max:255'],
                                            'comment' => ['nullable', 'string'],

                                            'input' => ['nullable', 'array'],
                                            'result' => ['nullable', 'array'],

                                            'purchase_net_per_kg' => ['nullable', 'numeric'],
                                            'sale_net_per_kg' => ['nullable', 'numeric'],
                                            'sale_gross_per_kg' => ['nullable', 'numeric'],
                                            'sale_net_per_box' => ['nullable', 'numeric'],
                                            'sale_gross_per_box' => ['nullable', 'numeric'],
                                            'profit_per_kg' => ['nullable', 'numeric'],
                                            'margin_percent' => ['nullable', 'numeric'],
                                            'markup_percent' => ['nullable', 'numeric'],
                                        ]);

        $calculation = $good->priceCalculations()->create([
                                                              ...$validated,
                                                              'created_by' => $request->user()?->id,
                                                          ]);

        return response()->json(
            $calculation->fresh([
                                    'currency',
                                    'priceType',
                                    'quotation.unit',
                                    'purchase.entity',
                                ]),
            201
        );
    }

    public function update(Request $request, Good $good, GoodPriceCalculation $calculation): JsonResponse
    {
        abort_unless($calculation->good_id === $good->id, 404);

        $validated = $request->validate([
                                            'name' => ['nullable', 'string', 'max:255'],
                                            'comment' => ['nullable', 'string'],
                                        ]);

        $calculation->update($validated);

        return response()->json($calculation->fresh());
    }

    public function destroy(Good $good, GoodPriceCalculation $calculation): JsonResponse
    {
        abort_unless($calculation->good_id === $good->id, 404);

        $calculation->delete();

        return response()->json(null, 204);
    }
}
