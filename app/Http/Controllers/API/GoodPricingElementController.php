<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Good;
use App\Models\GoodPricingElement;
use App\Services\Goods\GoodMeasurement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GoodPricingElementController extends Controller
{
    public function index(Good $good): JsonResponse
    {
        return response()->json(GoodPricingElement::where('good_id', $good->id)
            ->with('currency')->orderBy('id')->get()->map(fn ($element) => $this->row($element)));
    }

    public function store(Request $request, Good $good, GoodMeasurement $measurement): JsonResponse
    {
        $input = $this->validated($request);

        return DB::transaction(function () use ($request, $good, $input, $measurement): JsonResponse {
            $good = Good::lockForUpdate()->findOrFail($good->id);
            $element = new GoodPricingElement($this->attributes($good, $input, $measurement));
            $element->good_id = $good->id;
            $element->created_by = $request->user()->id;
            $element->updated_by = $request->user()->id;
            $element->save();

            return response()->json($this->row($element->fresh('currency')), 201);
        });
    }

    public function update(Request $request, Good $good, int $element, GoodMeasurement $measurement): JsonResponse
    {
        $input = $this->validated($request, true);

        return DB::transaction(function () use ($request, $good, $element, $input, $measurement): JsonResponse {
            $good = Good::lockForUpdate()->findOrFail($good->id);
            $element = GoodPricingElement::where('good_id', $good->id)->lockForUpdate()->findOrFail($element);
            $this->assertRevision($element, $input['revision']);
            $element->fill($this->attributes($good, $input, $measurement));
            $element->revision++;
            $element->updated_by = $request->user()->id;
            $element->save();

            return response()->json($this->row($element->fresh('currency')));
        });
    }

    public function destroy(Request $request, Good $good, int $element): JsonResponse
    {
        $input = $request->validate(['revision' => ['required', 'integer', 'min:1']]);

        return DB::transaction(function () use ($good, $element, $input): JsonResponse {
            Good::lockForUpdate()->findOrFail($good->id);
            $element = GoodPricingElement::where('good_id', $good->id)->lockForUpdate()->findOrFail($element);
            $this->assertRevision($element, $input['revision']);
            $element->delete();

            return response()->json(null, 204);
        });
    }

    private function assertRevision(GoodPricingElement $element, int $revision): void
    {
        abort_unless($element->revision === $revision, 409, 'Элемент цены уже изменён другим сотрудником. Обновите библиотеку элементов.');
    }

    private function attributes(Good $good, array $input, GoodMeasurement $measurement): array
    {
        $measurement->assertSnapshot($good, $input['measurement']);
        $current = $measurement->assertConfigured($good, 'measurement');
        if ($input['kind'] === 'percent' && $input['includes_vat']) {
            throw ValidationException::withMessages(['includes_vat' => 'Процентный элемент рассчитывается от суммы без НДС. Выделение НДС доступно для фиксированной суммы.']);
        }

        return [
            'name' => trim($input['name']), 'kind' => $input['kind'], 'value' => $input['value'],
            'percentage_base' => $input['percentage_base'], 'includes_vat' => $input['includes_vat'],
            'vat_rate' => $input['kind'] === 'percent' ? 0 : $input['vat_rate'],
            'currency_id' => $input['currency_id'], 'measurement' => $current,
        ];
    }

    private function validated(Request $request, bool $updating = false): array
    {
        return $request->validate([
            'revision' => [$updating ? 'required' : 'sometimes', 'integer', $updating ? 'min:1' : 'in:0'],
            'name' => ['required', 'string', 'max:120', 'regex:/\S/u'],
            'kind' => ['required', Rule::in(['fixed', 'percent'])],
            'value' => ['required', 'numeric', 'between:0,100000000000', 'decimal:0,4'],
            'percentage_base' => ['required', Rule::in(['purchase_net', 'running_cost'])],
            'includes_vat' => ['required', 'boolean'], 'vat_rate' => ['required', 'numeric', 'between:0,100', 'decimal:0,4'],
            'currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'measurement' => ['required', 'array:measure_id,unit_label,kilograms_per_unit'],
            'measurement.measure_id' => ['required', 'integer', 'exists:measures,id'],
            'measurement.unit_label' => ['nullable', 'string', 'max:255'],
            'measurement.kilograms_per_unit' => ['nullable', 'numeric', 'gt:0'],
        ]);
    }

    private function row(GoodPricingElement $element): array
    {
        return [
            'id' => $element->id, 'good_id' => $element->good_id, 'name' => $element->name,
            'kind' => $element->kind, 'value' => $element->value, 'percentage_base' => $element->percentage_base,
            'includes_vat' => $element->includes_vat, 'vat_rate' => $element->vat_rate,
            'currency_id' => $element->currency_id, 'currency_code' => $element->currency?->code,
            'measurement' => $element->measurement, 'revision' => $element->revision,
            'created_by' => $element->created_by, 'updated_by' => $element->updated_by,
            'created_at' => $element->created_at?->toISOString(), 'updated_at' => $element->updated_at?->toISOString(),
        ];
    }
}
