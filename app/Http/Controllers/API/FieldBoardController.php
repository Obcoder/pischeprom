<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Field;
use App\Models\FieldMatch;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FieldBoardController extends Controller
{
    public function show(Field $field): JsonResponse
    {
        $producers = $this->producerQuery($field)
            ->with([
                'manufactures' => fn ($query) => $query
                    ->select('products.id', 'products.rus', 'products.eng', 'products.category_id')
                    ->whereHas('category', fn ($categoryQuery) => $categoryQuery->where('field_id', $field->id)),
                'manufactures.category:id,name,field_id',
                'fields:id,title',
                'uris:id,address',
                'cities:id,name',
            ])
            ->get();

        $consumers = $this->consumerQuery($field)
            ->with([
                'consumptions' => fn ($query) => $query
                    ->whereHas('product.category', fn ($categoryQuery) => $categoryQuery->where('field_id', $field->id)),
                'consumptions.product:id,rus,eng,category_id',
                'consumptions.product.category:id,name,field_id',
                'consumptions.measure:id,name',
                'fields:id,title',
                'uris:id,address',
                'cities:id,name',
            ])
            ->get();

        $producerIds = $producers->pluck('id');
        $consumerIds = $consumers->pluck('id');

        $matches = FieldMatch::query()
            ->where('field_id', $field->id)
            ->whereIn('producer_unit_id', $producerIds)
            ->whereIn('consumer_unit_id', $consumerIds)
            ->with([
                'producer:id,name,is_supplier,is_customer',
                'consumer:id,name,is_supplier,is_customer',
            ])
            ->latest()
            ->get();

        return response()->json([
            'field' => [
                'id' => $field->id,
                'title' => $field->title,
                'name' => $field->name,
            ],
            'producers' => $producers->map(fn (Unit $unit) => $this->producerPayload($unit))->values(),
            'consumers' => $consumers->map(fn (Unit $unit) => $this->consumerPayload($unit))->values(),
            'matches' => $matches->map(fn (FieldMatch $match) => $this->matchPayload($match, $producers, $consumers))->values(),
        ]);
    }

    public function store(Request $request, Field $field): JsonResponse
    {
        $data = $request->validate([
            'producer_unit_id' => ['required', 'integer', 'exists:units,id'],
            'consumer_unit_id' => ['required', 'integer', 'exists:units,id', 'different:producer_unit_id'],
            'status' => ['nullable', Rule::in(FieldMatch::STATUSES)],
            'note' => ['nullable', 'string', 'max:5000'],
        ]);

        if (!$this->unitHasFieldManufacture($field, (int) $data['producer_unit_id'])) {
            return response()->json([
                'message' => 'Producer unit has no manufactures in this field.',
            ], 422);
        }

        if (!$this->unitHasFieldConsumption($field, (int) $data['consumer_unit_id'])) {
            return response()->json([
                'message' => 'Consumer unit has no consumptions in this field.',
            ], 422);
        }

        $match = FieldMatch::query()->firstOrCreate(
            [
                'field_id' => $field->id,
                'producer_unit_id' => (int) $data['producer_unit_id'],
                'consumer_unit_id' => (int) $data['consumer_unit_id'],
            ],
            [
                'status' => $data['status'] ?? FieldMatch::STATUS_DRAFT,
                'note' => $data['note'] ?? null,
            ]
        );

        $match->load(['producer:id,name,is_supplier,is_customer', 'consumer:id,name,is_supplier,is_customer']);

        return response()->json([
            'data' => $this->matchPayload($match),
        ], $match->wasRecentlyCreated ? 201 : 200);
    }

    public function update(Request $request, Field $field, FieldMatch $match): JsonResponse
    {
        $this->abortIfMatchOutsideField($field, $match);

        $data = $request->validate([
            'status' => ['nullable', Rule::in(FieldMatch::STATUSES)],
            'note' => ['nullable', 'string', 'max:5000'],
        ]);

        $match->update($data);
        $match->load(['producer:id,name,is_supplier,is_customer', 'consumer:id,name,is_supplier,is_customer']);

        return response()->json([
            'data' => $this->matchPayload($match),
        ]);
    }

    public function destroy(Field $field, FieldMatch $match): JsonResponse
    {
        $this->abortIfMatchOutsideField($field, $match);

        $match->delete();

        return response()->json(null, 204);
    }

    protected function producerQuery(Field $field)
    {
        return Unit::query()
            ->select('units.*')
            ->whereHas('manufactures.category', fn ($query) => $query->where('field_id', $field->id))
            ->withCount([
                'manufactures as field_products_count' => fn ($query) =>
                    $query->whereHas('category', fn ($categoryQuery) => $categoryQuery->where('field_id', $field->id)),
                'fieldProducerMatches as field_matches_count' => fn ($query) =>
                    $query->where('field_id', $field->id),
            ])
            ->orderByDesc('field_matches_count')
            ->orderByDesc('field_products_count')
            ->orderBy('name');
    }

    protected function consumerQuery(Field $field)
    {
        return Unit::query()
            ->select('units.*')
            ->whereHas('consumptions.product.category', fn ($query) => $query->where('field_id', $field->id))
            ->withCount([
                'consumptions as field_products_count' => fn ($query) =>
                    $query->whereHas('product.category', fn ($categoryQuery) => $categoryQuery->where('field_id', $field->id)),
                'fieldConsumerMatches as field_matches_count' => fn ($query) =>
                    $query->where('field_id', $field->id),
            ])
            ->orderByDesc('field_matches_count')
            ->orderByDesc('field_products_count')
            ->orderBy('name');
    }

    protected function producerPayload(Unit $unit): array
    {
        $products = $unit->manufactures
            ->map(fn ($product) => [
                'id' => $product->id,
                'title' => $product->rus ?: $product->eng ?: "Product #{$product->id}",
                'category' => $product->category?->name,
            ])
            ->values();

        return $this->unitPayload($unit) + [
            'products' => $products,
            'products_count' => $products->count(),
            'matches_count' => (int) ($unit->field_matches_count ?? 0),
        ];
    }

    protected function consumerPayload(Unit $unit): array
    {
        $products = $unit->consumptions
            ->map(fn ($consumption) => [
                'id' => $consumption->product_id,
                'title' => $consumption->product?->rus ?: $consumption->product?->eng ?: "Product #{$consumption->product_id}",
                'quantity' => $consumption->quantity,
                'measure' => $consumption->measure?->name,
                'category' => $consumption->product?->category?->name,
            ])
            ->values();

        return $this->unitPayload($unit) + [
            'products' => $products,
            'products_count' => $products->count(),
            'matches_count' => (int) ($unit->field_matches_count ?? 0),
        ];
    }

    protected function unitPayload(Unit $unit): array
    {
        $site = $unit->uris
            ->first(fn ($uri) => !empty($uri->address))
            ?->address;

        return [
            'id' => $unit->id,
            'name' => $unit->name,
            'is_customer' => (bool) $unit->is_customer,
            'is_supplier' => (bool) $unit->is_supplier,
            'site' => $site,
            'cities' => $unit->cities
                ->map(fn ($city) => ['id' => $city->id, 'name' => $city->name])
                ->values(),
        ];
    }

    protected function matchPayload(FieldMatch $match, ?Collection $producers = null, ?Collection $consumers = null): array
    {
        $producer = $producers?->firstWhere('id', $match->producer_unit_id);
        $consumer = $consumers?->firstWhere('id', $match->consumer_unit_id);

        return [
            'id' => $match->id,
            'field_id' => $match->field_id,
            'producer_unit_id' => $match->producer_unit_id,
            'consumer_unit_id' => $match->consumer_unit_id,
            'status' => $match->status,
            'note' => $match->note,
            'producer' => $match->producer ? [
                'id' => $match->producer->id,
                'name' => $match->producer->name,
            ] : null,
            'consumer' => $match->consumer ? [
                'id' => $match->consumer->id,
                'name' => $match->consumer->name,
            ] : null,
            'shared_products' => $this->sharedProducts($producer, $consumer),
            'created_at' => $match->created_at,
            'updated_at' => $match->updated_at,
        ];
    }

    protected function sharedProducts(?Unit $producer, ?Unit $consumer): array
    {
        if (!$producer || !$consumer) {
            return [];
        }

        $producerProducts = $producer->manufactures
            ->keyBy('id');

        return $consumer->consumptions
            ->filter(fn ($consumption) => $producerProducts->has($consumption->product_id))
            ->map(fn ($consumption) => [
                'id' => $consumption->product_id,
                'title' => $producerProducts->get($consumption->product_id)?->rus
                    ?: $producerProducts->get($consumption->product_id)?->eng
                    ?: "Product #{$consumption->product_id}",
                'quantity' => $consumption->quantity,
                'measure' => $consumption->measure?->name,
            ])
            ->values()
            ->all();
    }

    protected function unitHasFieldManufacture(Field $field, int $unitId): bool
    {
        return Unit::query()
            ->whereKey($unitId)
            ->whereHas('manufactures.category', fn ($query) => $query->where('field_id', $field->id))
            ->exists();
    }

    protected function unitHasFieldConsumption(Field $field, int $unitId): bool
    {
        return Unit::query()
            ->whereKey($unitId)
            ->whereHas('consumptions.product.category', fn ($query) => $query->where('field_id', $field->id))
            ->exists();
    }

    protected function abortIfMatchOutsideField(Field $field, FieldMatch $match): void
    {
        abort_if((int) $match->field_id !== (int) $field->id, 404);
    }
}
