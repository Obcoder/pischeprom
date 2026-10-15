<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Quotation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class QuotationController extends Controller
{
    public function index()
    {
        return Quotation::query()
            ->with(['good', 'unit', 'currency', 'measure'])
            ->orderByRaw('COALESCE(quoted_at, created_at) DESC')
            ->orderByDesc('id')
            ->get();
    }

    public function store(Request $request): JsonResponse
    {
        $quotation = Quotation::create($this->validated($request));

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
        $quotation->update($this->validated($request, $quotation));

        return response()->json(
            $quotation->fresh(['good', 'unit', 'currency', 'measure'])
        );
    }

    public function destroy(Quotation $quotation): JsonResponse
    {
        $quotation->delete();

        return response()->json(null, 204);
    }

    private function validated(Request $request, ?Quotation $quotation = null): array
    {
        // PATCH validates the resulting offer. An old offer can be corrected in
        // place, but cannot continue being saved without currency or price units.
        $existing = $quotation ? $quotation->only($quotation->getFillable()) : [
            'denominator' => 1,
            'quoted_at' => today()->toDateString(),
        ];
        foreach (['quoted_at', 'valid_until'] as $field) {
            if (($existing[$field] ?? null) instanceof \DateTimeInterface) {
                $existing[$field] = $existing[$field]->format('Y-m-d');
            }
        }
        $input = [...$existing, ...$request->only((new Quotation)->getFillable())];
        $validator = Validator::make($input, [
            'good_id' => ['required', 'integer', 'exists:goods,id'],
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'price' => ['required', 'numeric', 'min:0', 'lt:1000000000000', 'decimal:0,4'],
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'measure_id' => ['required', 'integer', 'exists:measures,id'],
            'denominator' => ['required', 'numeric', 'min:0.0001', 'max:99999999999', 'decimal:0,4'],
            'quoted_at' => ['nullable', 'date_format:Y-m-d'],
            'valid_until' => ['nullable', 'date_format:Y-m-d'],
            'price_includes_vat' => ['nullable', 'boolean'],
            'vat_rate' => ['nullable', 'numeric', 'min:0', 'max:100', 'decimal:0,4'],
            'minimum_quantity' => ['nullable', 'numeric', 'min:0.0001', 'max:99999999999', 'decimal:0,4'],
            'lead_time_days' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ], [], [
            'good_id' => 'Товар', 'unit_id' => 'Поставщик', 'price' => 'Цена', 'currency_id' => 'Валюта',
            'measure_id' => 'Единица цены', 'denominator' => 'Количество единиц в цене',
            'quoted_at' => 'Дата предложения', 'valid_until' => 'Действует до', 'vat_rate' => 'Ставка НДС',
            'price_includes_vat' => 'Учёт НДС', 'minimum_quantity' => 'Минимальный заказ',
            'lead_time_days' => 'Срок поставки', 'notes' => 'Условия предложения',
        ]);
        $validator->after(function ($validator) use ($input) {
            if (! $validator->errors()->has('quoted_at') && ! $validator->errors()->has('valid_until')
                && ! empty($input['quoted_at']) && ! empty($input['valid_until'])
                && $input['valid_until'] < $input['quoted_at']) {
                $validator->errors()->add('valid_until', 'Срок действия не может быть раньше даты предложения.');
            }
            if (($input['price_includes_vat'] ?? null) !== null && ($input['vat_rate'] ?? null) === null) {
                $validator->errors()->add('vat_rate', 'Укажите ставку НДС, в том числе 0% для предложения без налога.');
            }
            if (($input['vat_rate'] ?? null) !== null && ($input['price_includes_vat'] ?? null) === null) {
                $validator->errors()->add('price_includes_vat', 'Укажите, включён ли НДС в цену предложения.');
            }
        });

        $validated = $validator->validate();

        // Unchanged historical values retain their stored precision and dates.
        return $quotation ? array_intersect_key($validated, $request->all()) : $validated;
    }
}
