<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Entity;
use App\Models\EntityConsumption;
use App\Models\Measure;
use App\Models\Product;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EntityConsumptionController extends Controller
{
    private const DUPLICATE_MESSAGE = 'Потребность в этом продукте уже зарегистрирована. Измените существующую запись.';

    public function index(string $entity): JsonResponse
    {
        $entity = $this->findEntity($entity);

        return response()->json([
            'data' => $entity->consumptions()->with($this->relations())->latest('id')->get(),
        ]);
    }

    public function meta(string $entity): JsonResponse
    {
        $this->findEntity($entity);

        return response()->json([
            'products' => Product::query()->withoutEagerLoads()->orderBy('rus')->orderBy('id')->get(['id', 'rus', 'eng']),
            'measures' => Measure::query()->orderBy('name')->orderBy('id')->get(['id', 'name']),
            'statuses' => collect(EntityConsumption::STATUSES)
                ->map(fn (string $label, string $value): array => compact('value', 'label'))->values(),
        ]);
    }

    public function store(Request $request, string $entity): JsonResponse
    {
        $entity = $this->findEntity($entity);
        $data = $this->validatedData($request, $entity);

        try {
            $consumption = $entity->consumptions()->create($data);
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['product_id' => self::DUPLICATE_MESSAGE]);
        }

        return response()->json([
            'data' => $consumption->refresh()->load($this->relations()),
        ], Response::HTTP_CREATED);
    }

    public function show(string $entity, string $consumption): JsonResponse
    {
        $consumption = $this->findEntity($entity)->consumptions()->findOrFail($consumption);

        return response()->json(['data' => $consumption->load($this->relations())]);
    }

    public function update(Request $request, string $entity, string $consumption): JsonResponse
    {
        $entity = $this->findEntity($entity);
        $consumption = $entity->consumptions()->findOrFail($consumption);
        $data = $this->validatedData($request, $entity, $consumption);

        try {
            $consumption->update($data);
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['product_id' => self::DUPLICATE_MESSAGE]);
        }

        return response()->json(['data' => $consumption->refresh()->load($this->relations())]);
    }

    public function destroy(string $entity, string $consumption): Response
    {
        $this->findEntity($entity)->consumptions()->findOrFail($consumption)->delete();

        return response()->noContent();
    }

    public function forProduct(string $product): JsonResponse
    {
        $product = Product::query()->withoutEagerLoads()->findOrFail($product);

        return response()->json([
            'data' => $product->entityConsumptions()->with([
                ...$this->relations(),
                'entity' => fn ($query) => $query->withoutEagerLoads()->select(['id', 'name', 'INN']),
            ])->latest('id')->get(),
        ]);
    }

    private function findEntity(string $id): Entity
    {
        return Entity::query()->withoutEagerLoads()->findOrFail($id);
    }

    private function relations(): array
    {
        return [
            'product' => fn ($query) => $query->withoutEagerLoads()->select(['id', 'rus', 'eng']),
            'measure:id,name',
        ];
    }

    private function validatedData(Request $request, Entity $entity, ?EntityConsumption $consumption = null): array
    {
        $uniqueProduct = Rule::unique('entity_consumptions', 'product_id')->where('entity_id', $entity->id);
        if ($consumption) {
            $uniqueProduct->ignore($consumption->id);
        }

        $data = $request->validate([
            'entity_id' => ['prohibited'],
            'product_id' => [$consumption ? 'sometimes' : 'required', 'required', 'integer', 'exists:products,id', $uniqueProduct],
            'quantity' => ['sometimes', 'nullable', 'numeric', 'gt:0', 'max:999999999999.999', 'decimal:0,3'],
            'measure_id' => ['sometimes', 'nullable', 'integer', 'exists:measures,id'],
            'status' => ['sometimes', 'required', Rule::in(array_keys(EntityConsumption::STATUSES))],
            'comment' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ], [
            'product_id.unique' => self::DUPLICATE_MESSAGE,
            'quantity.gt' => 'Количество должно быть больше нуля.',
            'quantity.decimal' => 'Укажите не более трёх знаков после запятой.',
        ]);

        // PATCH may keep an existing quantity while clearing its measure.
        $quantity = array_key_exists('quantity', $data) ? $data['quantity'] : $consumption?->quantity;
        $measureId = array_key_exists('measure_id', $data) ? $data['measure_id'] : $consumption?->measure_id;
        if ($quantity !== null && $measureId === null) {
            throw ValidationException::withMessages([
                'measure_id' => 'Выберите единицу измерения для указанного количества.',
            ]);
        }

        return $data;
    }
}
