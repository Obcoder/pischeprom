<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PriceType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PriceTypeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = PriceType::query()
            ->with('currency')
            ->orderBy('sort_order')
            ->orderBy('name');

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('is_public')) {
            $query->where('is_public', filter_var($request->input('is_public'), FILTER_VALIDATE_BOOLEAN));
        }

        return response()->json($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
                                            'name' => ['required', 'string', 'max:255'],
                                            'code' => ['nullable', 'string', 'max:255', 'unique:price_types,code'],
                                            'description' => ['nullable', 'string'],
                                            'currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
                                            'markup_percent' => ['nullable', 'numeric'],
                                            'target_margin_percent' => ['nullable', 'numeric', 'min:0', 'max:99.99'],
                                            'rounding_step' => ['nullable', 'numeric', 'min:0'],
                                            'is_active' => ['nullable', 'boolean'],
                                            'is_public' => ['nullable', 'boolean'],
                                            'sort_order' => ['nullable', 'integer', 'min:0'],
                                        ]);

        $validated['code'] = $validated['code'] ?? Str::slug($validated['name']);
        $validated['is_active'] = $validated['is_active'] ?? true;
        $validated['is_public'] = $validated['is_public'] ?? false;
        $validated['sort_order'] = $validated['sort_order'] ?? 100;

        $priceType = PriceType::create($validated);

        return response()->json($priceType->fresh('currency'), 201);
    }

    public function show(PriceType $priceType): JsonResponse
    {
        return response()->json($priceType->load('currency'));
    }

    public function update(Request $request, PriceType $priceType): JsonResponse
    {
        $validated = $request->validate([
                                            'name' => ['sometimes', 'required', 'string', 'max:255'],
                                            'code' => [
                                                'nullable',
                                                'string',
                                                'max:255',
                                                Rule::unique('price_types', 'code')->ignore($priceType->id),
                                            ],
                                            'description' => ['nullable', 'string'],
                                            'currency_id' => ['nullable', 'integer', 'exists:currencies,id'],
                                            'markup_percent' => ['nullable', 'numeric'],
                                            'target_margin_percent' => ['nullable', 'numeric', 'min:0', 'max:99.99'],
                                            'rounding_step' => ['nullable', 'numeric', 'min:0'],
                                            'is_active' => ['nullable', 'boolean'],
                                            'is_public' => ['nullable', 'boolean'],
                                            'sort_order' => ['nullable', 'integer', 'min:0'],
                                        ]);

        if (array_key_exists('name', $validated) && empty($validated['code'])) {
            $validated['code'] = Str::slug($validated['name']);
        }

        $priceType->update($validated);

        return response()->json($priceType->fresh('currency'));
    }

    public function destroy(PriceType $priceType): JsonResponse
    {
        $priceType->delete();

        return response()->json(null, 204);
    }
}
