<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Quotation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuotationController extends Controller
{
    public function index()
    {
        return Quotation::query()
            ->with(['good', 'unit', 'currency', 'measure'])
            ->orderByDesc('created_at')
            ->get();
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
                                            'good_id' => ['required', 'integer', 'exists:goods,id'],
                                            'unit_id' => ['required', 'integer', 'exists:units,id'],
                                            'price' => ['required', 'numeric', 'min:0'],
                                            'currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
                                            'measure_id' => ['nullable', 'integer', 'exists:measures,id'],
                                            'denominator' => ['nullable', 'numeric', 'min:0.0001'],
                                        ]);

        $quotation = Quotation::create([
                                           ...$validated,
                                           'denominator' => $validated['denominator'] ?? 1,
                                       ]);

        return response()->json(
            $quotation->fresh(['good', 'unit', 'currency', 'measure']),
            201
        );
    }

    public function show(Quotation $quotation)
    {
        return $quotation->load(['good', 'unit', 'currency', 'measure']);
    }

    public function update(Request $request, Quotation $quotation): JsonResponse
    {
        $validated = $request->validate([
                                            'good_id' => ['sometimes', 'required', 'integer', 'exists:goods,id'],
                                            'unit_id' => ['sometimes', 'required', 'integer', 'exists:units,id'],
                                            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
                                            'currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
                                            'measure_id' => ['nullable', 'integer', 'exists:measures,id'],
                                            'denominator' => ['nullable', 'numeric', 'min:0.0001'],
                                        ]);

        $quotation->update($validated);

        return response()->json(
            $quotation->fresh(['good', 'unit', 'currency', 'measure'])
        );
    }

    public function destroy(Quotation $quotation): JsonResponse
    {
        $quotation->delete();

        return response()->json(null, 204);
    }
}
