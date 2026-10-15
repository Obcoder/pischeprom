<?php

namespace App\Services\SupplierOrders;

use App\Models\Currency;
use App\Models\Good;
use App\Models\Quotation;
use App\Models\Unit;
use App\Models\Warehouse;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierOrderContext
{
    public function forUnit(Unit $unit, array $filters): array
    {
        // On the application's MySQL REPEATABLE READ connection, the demand and
        // warehouse queries share one snapshot even if a shipment commits between them.
        return DB::transaction(fn () => $this->snapshot($unit, $filters));
    }

    private function snapshot(Unit $unit, array $filters): array
    {
        $warehouseId = array_key_exists('warehouse_id', $filters) ? (int) $filters['warehouse_id']
            : Warehouse::where('code', Warehouse::GOODS_CODE)->where('is_active', true)->value('id');
        if ($warehouseId === null) {
            throw ValidationException::withMessages(['warehouse_id' => 'Активный системный склад товаров не найден. Выберите склад.']);
        }
        $entityIds = DB::table('entity_unit')->where('unit_id', $unit->id)->pluck('entity_id');
        $tab = $filters['tab'] ?? 'demand';
        $goods = Good::query()->withoutEagerLoads()->with('measure:id,name');
        if ($tab === 'demand') {
            $goods->whereIn('goods.id', $this->demandQuery($filters, $warehouseId)
                ->when(! ($filters['include_deferred'] ?? false), fn ($query) => $query->where('os.code', '<>', 'deferred'))
                ->select('oi.good_id'));
        } elseif ($tab === 'assortment') {
            $goods->whereIn('goods.id', $this->assortmentIds($unit, $entityIds));
        }
        $search = trim($filters['search'] ?? '');
        $goods->when($search !== '', fn ($query) => $query->where('goods.name', 'like', '%'.$search.'%'));
        $page = $goods->orderBy('goods.name')->orderBy('goods.id')->paginate($filters['per_page'] ?? 30,
            ['goods.id', 'goods.name', 'goods.measure_id', 'goods.unit_weight_kg', 'goods.ava_thumb', 'goods.ava_image'], 'page', $filters['page'] ?? 1);
        $ids = $page->getCollection()->pluck('id')->all();
        $sources = $this->assortmentSources($unit, $entityIds, $ids);
        $demand = $this->demandQuery($filters, $warehouseId)->whereIn('oi.good_id', $ids)
            ->leftJoin('entities as e', 'e.id', '=', 'o.entity_id')->leftJoin('measures as m', 'm.id', '=', 'oi.measure_id')
            ->orderBy('o.delivery_date')->orderBy('oi.id')
            ->get(['oi.id', 'oi.good_id', 'oi.quantity', 'oi.measure_id', 'oi.denominator', 'oi.snapshot', 'm.name as unit_label',
                'o.id as order_id', 'o.number', 'o.delivery_date', 'o.prepared_at', 'o.preparation_invalidated_at',
                'e.name as entity_name', 'os.code as status_code', 'os.name as status_name'])->groupBy('good_id');
        $stock = DB::table('good_stock_movements as gsm')->whereIn('gsm.good_id', $ids)
            ->when($warehouseId !== 0, fn ($query) => $query->where('gsm.warehouse_id', $warehouseId))
            ->leftJoin('measures as m', 'm.id', '=', 'gsm.measure_id')
            ->selectRaw('gsm.good_id, gsm.warehouse_id, gsm.measure_id, m.name as unit_label, SUM(gsm.quantity_delta) as quantity')
            ->groupBy('gsm.good_id', 'gsm.warehouse_id', 'gsm.measure_id', 'm.name')->get()->groupBy('good_id');
        $pending = DB::table('supplier_order_items as si')->join('supplier_orders as so', 'so.id', '=', 'si.supplier_order_id')
            ->join('units as u', 'u.id', '=', 'so.unit_id')->whereIn('si.good_id', $ids)
            ->whereIn('so.status', ['draft', 'sending', 'sent', 'confirmed', 'send_unknown'])
            ->when($warehouseId !== 0, fn ($query) => $query->where(fn ($query) => $query->where('so.warehouse_id', $warehouseId)->orWhereNull('so.warehouse_id')))
            ->orderBy('so.id')->orderBy('si.position')
            ->get(['si.id', 'si.good_id', 'si.quantity', 'si.measure_id', 'si.unit_label', 'si.kilograms_per_unit',
                'so.id as order_id', 'so.number', 'so.status', 'so.warehouse_id', 'so.delivery_date', 'u.name as unit_name'])->groupBy('good_id');
        $quotes = Quotation::withoutEagerLoads()->with(['currency:id,code', 'measure:id,name'])
            ->where('unit_id', $unit->id)->whereIn('good_id', $ids)
            ->orderByRaw('COALESCE(quoted_at, created_at) DESC')->orderByDesc('id')
            ->get()->unique('good_id')->keyBy('good_id');

        return [
            'data' => $page->getCollection()->map(fn (Good $good) => $this->row($good, $sources->get($good->id, collect()),
                $demand->get($good->id, collect()), $stock->get($good->id, collect()), $pending->get($good->id, collect()),
                $quotes->get($good->id), (bool) ($filters['include_deferred'] ?? false), $filters['delivery_to'] ?? null))->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), 'per_page' => $page->perPage()],
            'warehouses' => Warehouse::orderBy('name')->get(['id', 'name']), 'warehouse_id' => $warehouseId,
            'currencies' => Currency::orderBy('code')->get(['id', 'code']),
            'unit' => ['id' => $unit->id, 'name' => $unit->name], 'recipients' => $this->recipients($unit, $entityIds),
            'defaults' => app(SupplierOrderDefaults::class)->forUnit($unit->id),
            'scope_note' => 'Спрос: неотгруженные незакрытые заказы всех покупателей'.(($filters['include_deferred'] ?? false) ? ', включая отложенные.' : ', без отложенных.')
                .' Собранные заказы включены; отдельного резервирования нет. Остаток — проведённые движения выбранного склада; отрицательные остатки увеличивают дефицит.'
                .' Из дефицита вычтены отправленные и подтверждённые заказы всем поставщикам, включая заказы без назначенного склада. Неопределённые отправки показаны отдельно.'
                .(! empty($filters['delivery_to']) ? ' Спрос ограничен доставкой до '.$filters['delivery_to'].' и заказами без даты; поставки позже этой даты или без срока не уменьшают рекомендацию.' : ''),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    private function demandQuery(array $filters, int $warehouseId): Builder
    {
        return DB::table('order_items as oi')->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->join('order_statuses as os', 'os.id', '=', 'o.order_status_id')
            ->whereNull('o.closed_at')->whereNull('o.shipped_at')->whereNull('o.shipped_sale_id')->where('os.is_closed', false)
            ->when($warehouseId !== 0, fn ($query) => $query->where(fn ($query) => $query->where('o.fulfillment_warehouse_id', $warehouseId)->orWhereNull('o.fulfillment_warehouse_id')))
            ->when($filters['delivery_to'] ?? null, fn ($query, $date) => $query->where(fn ($query) => $query->whereDate('o.delivery_date', '<=', $date)->orWhereNull('o.delivery_date')));
    }

    private function assortmentIds(Unit $unit, Collection $entityIds): Builder
    {
        return DB::table('quotations')->where('unit_id', $unit->id)->select('good_id')
            ->union(DB::table('good_purchase as gp')->join('purchases as p', 'p.id', '=', 'gp.purchase_id')->whereIn('p.entity_id', $entityIds)->select('gp.good_id'))
            ->union(DB::table('supplier_good_prices')->whereIn('entity_id', $entityIds)->select('good_id'))
            ->union(DB::table('good_product as gp')->join('manufacturers as m', 'm.product_id', '=', 'gp.product_id')->where('m.unit_id', $unit->id)->select('gp.good_id'));
    }

    private function assortmentSources(Unit $unit, Collection $entityIds, array $ids): Collection
    {
        $sets = [
            ['quotation', 'Предложение поставщика', DB::table('quotations')->where('unit_id', $unit->id)->whereIn('good_id', $ids)->pluck('good_id')],
            ['purchase', 'Закупали у связанного Entity', DB::table('good_purchase as gp')->join('purchases as p', 'p.id', '=', 'gp.purchase_id')->whereIn('p.entity_id', $entityIds)->whereIn('gp.good_id', $ids)->pluck('gp.good_id')],
            ['supplier_price', 'Импортированный прайс поставщика', DB::table('supplier_good_prices')->whereIn('entity_id', $entityIds)->whereIn('good_id', $ids)->pluck('good_id')],
            ['manufacture', 'Производит продукт · конкретный товар требует уточнения', DB::table('good_product as gp')->join('manufacturers as m', 'm.product_id', '=', 'gp.product_id')->where('m.unit_id', $unit->id)->whereIn('gp.good_id', $ids)->pluck('gp.good_id')],
        ];
        $sources = collect();
        foreach ($sets as [$kind, $label, $goodIds]) {
            foreach ($goodIds->unique() as $id) {
                $sources->put($id, $sources->get($id, collect())->push(['kind' => $kind, 'label' => $label]));
            }
        }

        return $sources;
    }

    private function row(Good $good, Collection $sources, Collection $demand, Collection $stock, Collection $pending, ?Quotation $quote, bool $includeDeferred, ?string $deliveryTo): array
    {
        $measurement = $good->measurement();
        $issues = [];
        $demandQuantity = $deferredQuantity = $stockQuantity = $sentQuantity = $confirmedQuantity = $uncertainQuantity = 0.0;
        $customerOrders = [];
        foreach ($demand as $item) {
            $snapshot = json_decode($item->snapshot ?: '{}', true);
            $unit = $snapshot['measurement'] ?? ['measure_id' => $item->measure_id, 'unit_label' => $item->unit_label, 'kilograms_per_unit' => $item->denominator];
            $quantity = $this->quantity($measurement, $unit, $item->quantity, 'customer_order', $item->id, $issues,
                isset($unit['measure_id']) && (int) $unit['measure_id'] !== (int) $item->measure_id);
            if ($item->status_code === 'deferred') {
                $deferredQuantity = $this->sum($deferredQuantity, $quantity);
            }
            if ($item->status_code !== 'deferred' || $includeDeferred) {
                $demandQuantity = $this->sum($demandQuantity, $quantity);
            }
            $customerOrders[] = ['id' => $item->order_id, 'item_id' => $item->id, 'number' => $item->number,
                'entity_name' => $item->entity_name, 'quantity' => (float) $item->quantity, 'normalized_quantity' => $quantity,
                'unit_label' => $unit['unit_label'] ?? $item->unit_label, 'delivery_date' => $item->delivery_date,
                'status_name' => $item->status_name, 'prepared' => $item->prepared_at !== null && $item->preparation_invalidated_at === null];
        }
        foreach ($stock as $bucket) {
            if (abs((float) $bucket->quantity) < 0.000000001) {
                continue;
            }
            $stockQuantity = $this->sum($stockQuantity, $this->quantity($measurement,
                ['measure_id' => $bucket->measure_id, 'unit_label' => $bucket->unit_label], $bucket->quantity, 'stock', $bucket->warehouse_id, $issues));
        }
        $supplierOrders = [];
        $timelyQuantity = $lateQuantity = $undatedQuantity = 0.0;
        foreach ($pending as $item) {
            $quantity = $this->quantity($measurement, (array) $item, $item->quantity, 'supplier_order', $item->id, $issues);
            if ($item->status === 'sent') {
                $sentQuantity = $this->sum($sentQuantity, $quantity);
            } elseif ($item->status === 'confirmed') {
                $confirmedQuantity = $this->sum($confirmedQuantity, $quantity);
            } elseif (in_array($item->status, ['sending', 'send_unknown'], true)) {
                $uncertainQuantity = $this->sum($uncertainQuantity, $quantity);
            }
            $placed = in_array($item->status, ['sent', 'confirmed'], true);
            $late = $deliveryTo !== null && $item->delivery_date !== null && $item->delivery_date > $deliveryTo;
            $undated = $deliveryTo !== null && $item->delivery_date === null;
            if ($placed && $late) {
                $lateQuantity = $this->sum($lateQuantity, $quantity);
            } elseif ($placed && $undated) {
                $undatedQuantity = $this->sum($undatedQuantity, $quantity);
            } elseif ($placed) {
                $timelyQuantity = $this->sum($timelyQuantity, $quantity);
            }
            $supplierOrders[] = ['id' => $item->order_id, 'number' => $item->number, 'status' => $item->status,
                'quantity' => (float) $item->quantity, 'normalized_quantity' => $quantity, 'unit_label' => $item->unit_label,
                'unit_name' => $item->unit_name, 'warehouse_id' => $item->warehouse_id, 'delivery_date' => $item->delivery_date,
                'deducted_from_recommendation' => $placed && ! $late && ! $undated,
                'timing' => $late ? 'after_cutoff' : ($undated ? 'unknown' : 'within_scope')];
        }
        if (! $measurement['measure_id']) {
            $issues[] = ['source' => 'good', 'record_id' => $good->id, 'reason' => 'У товара не задана единица учёта.'];
        }
        $ordered = $this->sum($sentQuantity, $confirmedQuantity);
        $shortage = $demandQuantity === null || $stockQuantity === null ? null : round(max(0, $demandQuantity - $stockQuantity), 6);
        $toOrder = $issues || $shortage === null || $timelyQuantity === null ? null : round(max(0, $shortage - $timelyQuantity), 6);

        return [
            'good_id' => $good->id, 'name' => $good->name, 'measurement' => $measurement,
            'avatar_url' => app(SupplierOrderProductImages::class)->url($good),
            'assortment_sources' => $sources->values(), 'offered_by_supplier' => $sources->contains(fn ($source) => $source['kind'] !== 'manufacture'),
            ...$this->quote($quote, $measurement),
            'demand_quantity' => $demandQuantity, 'deferred_quantity' => $deferredQuantity, 'stock_quantity' => $stockQuantity,
            'shortage_quantity' => $shortage, 'to_order_quantity' => $toOrder, 'ordered_quantity' => $ordered,
            'sent_quantity' => $sentQuantity, 'confirmed_quantity' => $confirmedQuantity, 'uncertain_quantity' => $uncertainQuantity,
            'incoming_before_cutoff_quantity' => $timelyQuantity, 'late_incoming_quantity' => $lateQuantity,
            'undated_incoming_quantity' => $undatedQuantity,
            'incoming_warnings' => [
                ...($lateQuantity === null || $lateQuantity > 0 ? ['Поставки после выбранной даты показаны отдельно и не уменьшают рекомендацию.'] : []),
                ...($undatedQuantity === null || $undatedQuantity > 0 ? ['Срок части поставок неизвестен: наличие к выбранной дате не подтверждено. Эти количества не уменьшают рекомендацию.'] : []),
                ...($uncertainQuantity === null || $uncertainQuantity > 0 ? ['Есть отправки с неопределённым результатом. Проверьте их до повторного заказа.'] : []),
            ],
            'unit_issues' => $issues, 'customer_orders' => $customerOrders, 'supplier_orders' => $supplierOrders,
        ];
    }

    private function quantity(array $current, array $snapshot, mixed $quantity, string $source, int $id, array &$issues, bool $conflict = false): ?float
    {
        $weight = $snapshot['kilograms_per_unit'] ?? null;
        if ($conflict || ! $current['measure_id'] || ! ($snapshot['measure_id'] ?? null)
            || (int) $current['measure_id'] !== (int) $snapshot['measure_id']
            || ($weight !== null && ($current['kilograms_per_unit'] === null || abs((float) $weight - $current['kilograms_per_unit']) > 0.000000001))
            || ! is_numeric($quantity) || ! is_finite((float) $quantity) || ($source !== 'stock' && (float) $quantity <= 0)) {
            $issues[] = ['source' => $source, 'record_id' => $id, 'quantity' => is_numeric($quantity) ? (float) $quantity : null,
                'unit_label' => $snapshot['unit_label'] ?? null, 'reason' => 'Единица или масса не совпадает с текущей единицей товара; автоматический пересчёт не выполнен.'];

            return null;
        }

        return round((float) $quantity, 6);
    }

    private function sum(?float $total, ?float $quantity): ?float
    {
        return $total === null || $quantity === null ? null : round($total + $quantity, 6);
    }

    private function quote(?Quotation $quote, array $measurement): array
    {
        return $quote ? app(SupplierQuotationPricing::class)->resolve($quote, $measurement)
            : ['unit_price' => null, 'currency_id' => null, 'currency_code' => null, 'quotation_id' => null,
                'quotation' => null, 'quotation_warning' => null, 'quotation_snapshot' => null];
    }

    private function recipients(Unit $unit, Collection $entityIds): Collection
    {
        return DB::table('emails as e')->whereNull('e.deleted_at')->where('e.is_active', true)
            ->where(fn ($query) => $query->whereExists(fn ($query) => $query->selectRaw('1')->from('email_unit as eu')->whereColumn('eu.email_id', 'e.id')->where('eu.unit_id', $unit->id))
                ->orWhereExists(fn ($query) => $query->selectRaw('1')->from('email_entity as ee')->whereColumn('ee.email_id', 'e.id')->whereIn('ee.entity_id', $entityIds)))
            ->orderBy('e.address')->get(['e.address', 'e.name'])->filter(fn ($email) => filter_var($email->address, FILTER_VALIDATE_EMAIL))->values();
    }
}
