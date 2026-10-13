<?php

namespace App\Services\Suppliers;

use App\Models\Unit;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Read-only procurement analytics. Orders are commitments, purchases are recorded receipts. */
class SupplierAnalytics
{
    public const TIMEZONE = 'Europe/Moscow';

    public function period(array $input): array
    {
        $data = Validator::make($input, [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d'],
        ])->validate();
        $to = CarbonImmutable::parse($data['date_to'] ?? CarbonImmutable::now(self::TIMEZONE)->toDateString(), self::TIMEZONE);
        $from = isset($data['date_from']) ? CarbonImmutable::parse($data['date_from'], self::TIMEZONE) : $to->subDays(89);
        $days = (int) $from->diffInDays($to, false) + 1;
        if ($days < 1 || $days > 3660) {
            throw ValidationException::withMessages(['date_from' => 'Начало периода должно быть не позже окончания; максимальный период — 3660 дней.']);
        }

        return ['date_from' => $from->toDateString(), 'date_to' => $to->toDateString(), 'days' => $days, 'timezone' => self::TIMEZONE];
    }

    /** Inclusive start and exclusive end in the application's database timezone. */
    public function datetimeBounds(array $period): array
    {
        $timezone = config('app.timezone', 'UTC');

        return [
            CarbonImmutable::parse($period['date_from'], self::TIMEZONE)->startOfDay()->setTimezone($timezone)->format('Y-m-d H:i:s'),
            CarbonImmutable::parse($period['date_to'], self::TIMEZONE)->addDay()->startOfDay()->setTimezone($timezone)->format('Y-m-d H:i:s'),
        ];
    }

    public function summaries(array $unitIds, array $period): array
    {
        $unitIds = array_values(array_unique(array_filter(array_map('intval', $unitIds), fn ($id) => $id > 0)));
        if ($unitIds === []) {
            return [];
        }

        return DB::transaction(fn () => $this->summarySnapshot($unitIds, $period));
    }

    public function show(Unit $unit, array $input): array
    {
        $period = $this->period($input);

        return DB::transaction(function () use ($unit, $input, $period): array {
            $summary = $this->summarySnapshot([$unit->id], $period)[$unit->id];
            $warnings = [
                'Заказы поставщику и учтённые закупки показаны отдельно. Закрытие заказа не подтверждает приход или оплату; автоматической связи между этими документами нет.',
                'Закупки отнесены по текущим связям Entity с Unit. Изменение связей меняет эту принадлежность; суммы общих Entity исключены из однозначных итогов поставщика.',
                'Суммы разделены по валютам без пересчёта курса и без предположений о НДС. Количества показаны в исторических единицах; масса старых закупочных упаковок не восстанавливается по текущему товару.',
            ];
            if ($summary['shared_purchases']['count'] > 0) {
                $warnings[] = 'Есть закупки Entity, связанных с несколькими Unit: они показаны отдельно и не включены в суммы, частоту и помесячную динамику этого поставщика.';
            }
            if ($summary['orders']['unpriced_items_count'] || $summary['purchases']['unpriced_items_count']) {
                $warnings[] = 'Часть позиций не имеет цены: денежные итоги неполные.';
            }
            if (array_key_exists('UNKNOWN', $summary['orders']['totals_by_currency'])
                || array_key_exists('UNKNOWN', $summary['purchases']['totals_by_currency'])
                || array_key_exists('UNKNOWN', $summary['shared_purchases']['totals_by_currency'])) {
                $warnings[] = 'Для части позиций валюта не указана: их сумма показана отдельно.';
            }

            return ['period' => $period, 'summary' => $summary, 'monthly' => $this->monthly($unit->id, $period),
                'goods' => $this->goods($unit->id, $period, $input), 'purchases' => $this->purchases($unit->id, $period, $input), 'warnings' => $warnings];
        });
    }

    private function summarySnapshot(array $unitIds, array $period): array
    {
        $result = [];
        foreach ($unitIds as $id) {
            $result[$id] = ['orders' => $this->emptyMetrics(),
                'purchases' => [...$this->emptyMetrics(), 'attribution' => 'unique_current_entity_link'],
                'shared_purchases' => ['count' => 0, 'totals_by_currency' => [], 'entity_ids' => []],
                'monitoring' => ['open_orders_count' => 0, 'overdue_delivery_count' => 0, 'unknown_delivery_count' => 0, 'uncertain_send_count' => 0]];
        }
        $orders = $this->ordersQuery($unitIds, $period);
        $purchases = $this->purchasesQuery($unitIds, $period);
        foreach (['orders' => [$orders, 'so.unit_id', 'so.id', 'so.sent_at'],
            'purchases' => [$purchases, 'owners.unit_id', 'p.id', 'p.date']] as $source => [$query, $unitColumn, $idColumn, $dateColumn]) {
            $stats = (clone $query)->selectRaw("{$unitColumn} AS unit_id, COUNT(DISTINCT {$idColumn}) AS documents_count, MIN({$dateColumn}) AS first_at, MAX({$dateColumn}) AS last_at")
                ->groupBy($unitColumn)->get();
            foreach ($stats as $stat) {
                $result[$stat->unit_id][$source] = [...$result[$stat->unit_id][$source], ...$this->frequency($stat, $period, $source)];
            }
            $allTime = $source === 'orders' ? $this->ordersQuery($unitIds) : $this->purchasesQuery($unitIds);
            foreach ($allTime->selectRaw("{$unitColumn} AS unit_id, MAX({$dateColumn}) AS last_at")->groupBy($unitColumn)->get() as $stat) {
                $last = $this->date($stat->last_at, $source);
                $result[$stat->unit_id][$source]['last_at_all_time'] = $source === 'orders' ? $last->toIso8601String() : $last->toDateString();
                $result[$stat->unit_id][$source]['days_since_last'] = max(0, (int) $last->startOfDay()->diffInDays(CarbonImmutable::now(self::TIMEZONE)->startOfDay(), false));
            }
            $lines = $source === 'orders' ? $this->orderLines($unitIds, $period) : $this->purchaseLines($unitIds, $period);
            $seenGoods = [];
            foreach ($lines->cursor() as $line) {
                $id = (int) $line->unit_id;
                $money = $this->lineAmount($line, $source);
                if ($money === null) {
                    $result[$id][$source]['unpriced_items_count']++;
                } else {
                    $this->addMoney($result[$id][$source]['totals_by_currency'], $line->currency_code, $money);
                }
                $seenGoods[$id][$line->good_id === null ? 'name:'.$line->name : 'id:'.$line->good_id] = true;
            }
            foreach ($seenGoods as $id => $goods) {
                $result[$id][$source]['distinct_goods_count'] = count($goods);
            }
        }
        $shared = $this->purchasesQuery($unitIds, $period, true);
        foreach ((clone $shared)->selectRaw('links.unit_id, COUNT(DISTINCT p.id) AS documents_count')->groupBy('links.unit_id')->get() as $stat) {
            $result[$stat->unit_id]['shared_purchases']['count'] = (int) $stat->documents_count;
        }
        foreach ((clone $shared)->select(['links.unit_id', 'p.entity_id'])->distinct()->get() as $row) {
            $result[$row->unit_id]['shared_purchases']['entity_ids'][] = (int) $row->entity_id;
        }
        foreach ($this->purchaseLines($unitIds, $period, true)->cursor() as $line) {
            if (($money = $this->lineAmount($line, 'purchases')) !== null) {
                $this->addMoney($result[$line->unit_id]['shared_purchases']['totals_by_currency'], $line->currency_code, $money);
            }
        }
        $today = CarbonImmutable::now(self::TIMEZONE)->toDateString();
        $monitoring = DB::table('supplier_orders')->whereIn('unit_id', $unitIds)->select('unit_id')
            ->selectRaw("SUM(CASE WHEN status IN ('sent','confirmed') THEN 1 ELSE 0 END) AS open_orders_count")
            ->selectRaw("SUM(CASE WHEN status IN ('sent','confirmed') AND delivery_date < ? THEN 1 ELSE 0 END) AS overdue_delivery_count", [$today])
            ->selectRaw("SUM(CASE WHEN status IN ('sent','confirmed') AND delivery_date IS NULL THEN 1 ELSE 0 END) AS unknown_delivery_count")
            ->selectRaw("SUM(CASE WHEN status IN ('sending','send_unknown') THEN 1 ELSE 0 END) AS uncertain_send_count")->groupBy('unit_id')->get();
        foreach ($monitoring as $row) {
            foreach (array_keys($result[$row->unit_id]['monitoring']) as $key) {
                $result[$row->unit_id]['monitoring'][$key] = (int) $row->{$key};
            }
        }

        return $result;
    }

    private function ordersQuery(array $unitIds, ?array $period = null): Builder
    {
        $query = DB::table('supplier_orders as so')->whereIn('so.unit_id', $unitIds)
            ->whereIn('so.status', ['sent', 'confirmed', 'closed'])->whereNotNull('so.sent_at');
        if ($period) {
            [$from, $to] = $this->datetimeBounds($period);
            $query->where('so.sent_at', '>=', $from)->where('so.sent_at', '<', $to);
        }

        return $query;
    }

    private function ownership(): Builder
    {
        return DB::table('entity_unit')->selectRaw('entity_id, MIN(unit_id) AS unit_id, COUNT(DISTINCT unit_id) AS links_count')->groupBy('entity_id');
    }

    private function purchasesQuery(array $unitIds, ?array $period = null, bool $shared = false): Builder
    {
        $query = DB::table('purchases as p')->joinSub($this->ownership(), 'owners', 'owners.entity_id', '=', 'p.entity_id');
        if ($shared) {
            $links = DB::table('entity_unit')->select(['entity_id', 'unit_id'])->whereIn('unit_id', $unitIds)->distinct();
            $query->joinSub($links, 'links', 'links.entity_id', '=', 'p.entity_id')->where('owners.links_count', '>', 1);
        } else {
            $query->whereIn('owners.unit_id', $unitIds)->where('owners.links_count', 1);
        }
        $query->whereNotNull('p.date')->where('p.date', '>=', '0001-01-01');
        if ($period) {
            $query->where('p.date', '>=', $period['date_from'])->where('p.date', '<=', $period['date_to']);
        }

        return $query;
    }

    private function orderLines(array $unitIds, array $period): Builder
    {
        return $this->ordersQuery($unitIds, $period)->join('supplier_order_items as i', 'i.supplier_order_id', '=', 'so.id')
            ->select(['i.id as line_id', 'so.unit_id', 'so.id as document_id', 'so.sent_at as document_date', 'i.good_id', 'i.name',
                'i.measure_id', 'i.unit_label', 'i.kilograms_per_unit', 'i.quantity', 'i.unit_price as price', 'i.currency_code']);
    }

    private function purchaseLines(array $unitIds, array $period, bool $shared = false): Builder
    {
        return $this->purchasesQuery($unitIds, $period, $shared)->join('good_purchase as i', 'i.purchase_id', '=', 'p.id')
            ->leftJoin('goods as g', 'g.id', '=', 'i.good_id')->leftJoin('measures as m', 'm.id', '=', 'i.measure_id')
            ->leftJoin('currencies as c', 'c.id', '=', 'i.currency_id')
            ->select(['i.id as line_id', ($shared ? 'links' : 'owners').'.unit_id', 'p.id as document_id', 'p.date as document_date',
                'i.good_id', 'g.name', 'i.measure_id', 'm.name as unit_label', 'i.quantity', 'i.price', 'i.total as recorded_total', 'c.code as currency_code'])
            ->selectRaw('NULL AS kilograms_per_unit');
    }

    private function emptyMetrics(): array
    {
        return ['count' => 0, 'totals_by_currency' => [], 'unpriced_items_count' => 0, 'first_at' => null, 'last_at' => null,
            'average_interval_days' => null, 'per_30_days' => 0, 'distinct_goods_count' => 0, 'last_at_all_time' => null, 'days_since_last' => null];
    }

    private function frequency(object $stat, array $period, string $source): array
    {
        $count = (int) $stat->documents_count;
        $first = $this->date($stat->first_at, $source);
        $last = $this->date($stat->last_at, $source);

        return ['count' => $count, 'first_at' => $source === 'orders' ? $first->toIso8601String() : $first->toDateString(),
            'last_at' => $source === 'orders' ? $last->toIso8601String() : $last->toDateString(),
            'average_interval_days' => $count < 2 ? null : round($first->diffInSeconds($last) / 86400 / ($count - 1), 2),
            'per_30_days' => round($count * 30 / $period['days'], 2)];
    }

    private function date(string $value, string $source): CarbonImmutable
    {
        return CarbonImmutable::parse($value, $source === 'orders' ? config('app.timezone', 'UTC') : self::TIMEZONE)->setTimezone(self::TIMEZONE);
    }

    private function decimal(mixed $value, int $scale): BigDecimal
    {
        return BigDecimal::of(is_float($value) ? number_format($value, $scale, '.', '') : (string) $value)->toScale($scale, RoundingMode::HalfUp);
    }

    private function lineAmount(object $line, string $source): ?BigDecimal
    {
        if ($line->price === null || ! is_numeric($line->price) || ! is_numeric($line->quantity)) {
            return null;
        }
        if ($source === 'purchases') {
            return isset($line->recorded_total) && is_numeric($line->recorded_total) ? $this->decimal($line->recorded_total, 4) : null;
        }

        return $this->decimal($line->quantity, 6)->multipliedBy($this->decimal($line->price, 4))->toScale(4, RoundingMode::HalfUp);
    }

    private function addMoney(array &$totals, ?string $currency, BigDecimal $amount): void
    {
        $key = trim($currency ?? '') ?: 'UNKNOWN';
        $totals[$key] = (string) BigDecimal::of($totals[$key] ?? '0')->plus($amount)->toScale(4, RoundingMode::HalfUp);
        ksort($totals);
    }

    private function monthly(int $unitId, array $period): array
    {
        $months = [];
        $month = CarbonImmutable::parse($period['date_from'], self::TIMEZONE)->startOfMonth();
        $end = CarbonImmutable::parse($period['date_to'], self::TIMEZONE)->startOfMonth();
        while ($month <= $end) {
            $key = $month->format('Y-m');
            $months[$key] = ['month' => $key, 'orders_count' => 0, 'purchases_count' => 0,
                'order_totals_by_currency' => [], 'purchase_totals_by_currency' => []];
            $month = $month->addMonth();
        }
        foreach (['orders', 'purchases'] as $source) {
            $documents = $source === 'orders' ? $this->ordersQuery([$unitId], $period)->select('so.sent_at as document_date')
                : $this->purchasesQuery([$unitId], $period)->select('p.date as document_date');
            foreach ($documents->cursor() as $document) {
                $key = $this->date($document->document_date, $source)->format('Y-m');
                $months[$key][$source.'_count']++;
            }
            $lines = $source === 'orders' ? $this->orderLines([$unitId], $period) : $this->purchaseLines([$unitId], $period);
            foreach ($lines->cursor() as $line) {
                if (($amount = $this->lineAmount($line, $source)) !== null) {
                    $key = $this->date($line->document_date, $source)->format('Y-m');
                    $this->addMoney($months[$key][$source === 'orders' ? 'order_totals_by_currency' : 'purchase_totals_by_currency'], $line->currency_code, $amount);
                }
            }
        }

        return array_values($months);
    }

    private function goods(int $unitId, array $period, array $input): array
    {
        $source = $input['goods_source'] ?? 'all';
        $groups = [];
        if ($source !== 'purchases') {
            $groups[] = $this->goodsGroups($this->orderLines([$unitId], $period), 'orders');
        }
        if ($source !== 'orders') {
            $groups[] = $this->goodsGroups($this->purchaseLines([$unitId], $period), 'purchases');
        }
        $query = array_shift($groups);
        foreach ($groups as $other) {
            $query->unionAll($other);
        }
        $search = trim($input['goods_search'] ?? '');
        $page = DB::query()->fromSub($query, 'grouped_goods')->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->orderBy('name')->orderBy('source')->orderBy('good_id')
            ->orderBy('measure_id')->orderBy('unit_label')->orderBy('kilograms_per_unit')->orderBy('group_name')
            ->paginate($input['per_page'] ?? 25, ['*'], 'goods_page', $input['goods_page'] ?? 1);
        $rows = [];
        foreach ($page->items() as $group) {
            $key = $this->groupKey($group, $group->source);
            $rows[$key] = ['key' => $key, 'good_id' => $group->good_id === null ? null : (int) $group->good_id, 'name' => $group->name,
                'source' => $group->source, 'unit_label' => $group->unit_label ?: 'Единица не указана',
                'measure_id' => $group->measure_id === null ? null : (int) $group->measure_id,
                'kilograms_per_unit' => $group->kilograms_per_unit === null ? null : (string) $this->decimal($group->kilograms_per_unit, 6),
                'quantity' => '0.000000', 'documents_count' => (int) $group->documents_count,
                'totals_by_currency' => [], 'unpriced_items_count' => 0,
                'last_at' => $group->source === 'orders' ? $this->date($group->last_at, 'orders')->toIso8601String() : $group->last_at];
        }
        // Only selected page identities are read again for exact decimal totals.
        // Historical groups sharing a good remain separate by their original unit/weight.
        foreach (['orders', 'purchases'] as $type) {
            $selected = array_values(array_filter($page->items(), fn ($row) => $row->source === $type));
            if ($selected === []) {
                continue;
            }
            $lines = $type === 'orders' ? $this->orderLines([$unitId], $period) : $this->purchaseLines([$unitId], $period);
            $ids = array_values(array_filter(array_column($selected, 'good_id'), fn ($id) => $id !== null));
            $names = array_column(array_filter($selected, fn ($row) => $row->good_id === null), 'name');
            $lines->where(fn ($query) => $query->whereIn('i.good_id', $ids)
                ->when($names !== [], fn ($query) => $query->orWhere(fn ($query) => $query->whereNull('i.good_id')->whereIn($type === 'orders' ? 'i.name' : 'g.name', $names))));
            foreach ($lines->cursor() as $line) {
                $key = $this->groupKey($line, $type);
                if (! isset($rows[$key])) {
                    continue;
                }
                $rows[$key]['quantity'] = (string) BigDecimal::of($rows[$key]['quantity'])->plus($this->decimal($line->quantity, 6));
                if (($amount = $this->lineAmount($line, $type)) === null) {
                    $rows[$key]['unpriced_items_count']++;
                } else {
                    $this->addMoney($rows[$key]['totals_by_currency'], $line->currency_code, $amount);
                }
            }
        }

        return ['data' => array_values($rows), 'meta' => $this->meta($page)];
    }

    private function goodsGroups(Builder $lines, string $source): Builder
    {
        $query = DB::query()->fromSub($lines, 'lines');
        // MySQL's usual case/accent-insensitive collation must not merge snapshot
        // identities that the exact-decimal pass distinguishes byte-for-byte.
        $label = DB::getDriverName() === 'mysql' ? 'CAST(unit_label AS BINARY)' : 'unit_label COLLATE BINARY';
        $name = DB::getDriverName() === 'mysql' ? 'CAST(name AS BINARY)' : 'name COLLATE BINARY';
        $identity = 'CASE WHEN good_id IS NULL THEN '.$name.' ELSE NULL END';

        return $query->selectRaw('? AS source, good_id, measure_id, '.$label.' AS unit_label, kilograms_per_unit, MAX(name) AS name,
            '.$identity.' AS group_name, COUNT(DISTINCT document_id) AS documents_count, MAX(document_date) AS last_at', [$source])
            ->groupBy('good_id', 'measure_id', 'kilograms_per_unit')->groupByRaw($label)->groupByRaw($identity);
    }

    private function groupKey(object $line, string $source): string
    {
        return hash('sha256', json_encode([$source, $line->good_id === null ? null : (int) $line->good_id,
            $line->good_id === null ? $line->name : null, $line->measure_id === null ? null : (int) $line->measure_id,
            $line->unit_label, $line->kilograms_per_unit === null ? null : (string) $this->decimal($line->kilograms_per_unit, 6)], JSON_THROW_ON_ERROR));
    }

    private function purchases(int $unitId, array $period, array $input): array
    {
        $links = DB::table('entity_unit')->where('unit_id', $unitId)->select('entity_id')->distinct();
        $query = DB::table('purchases as p')->whereIn('p.entity_id', $links)->join('entities as e', 'e.id', '=', 'p.entity_id')
            ->joinSub($this->ownership(), 'owners', 'owners.entity_id', '=', 'p.entity_id')
            ->where('p.date', '>=', $period['date_from'])->where('p.date', '<=', $period['date_to'])
            ->select(['p.id', 'p.date', 'p.entity_id', 'e.name as entity_name', 'owners.links_count']);
        $page = $query->orderByDesc('p.date')->orderByDesc('p.id')->paginate($input['per_page'] ?? 25, ['*'], 'purchases_page', $input['purchases_page'] ?? 1);
        $ids = array_column($page->items(), 'id');
        $entityIds = array_column($page->items(), 'entity_id');
        $linked = DB::table('entity_unit')->whereIn('entity_id', $entityIds)->select(['entity_id', 'unit_id'])->distinct()->orderBy('unit_id')->get()->groupBy('entity_id');
        $totals = [];
        $counts = [];
        foreach (DB::table('good_purchase as i')->whereIn('i.purchase_id', $ids)->leftJoin('currencies as c', 'c.id', '=', 'i.currency_id')
            ->get(['i.purchase_id', 'i.quantity', 'i.price', 'i.total as recorded_total', 'c.code as currency_code']) as $line) {
            $counts[$line->purchase_id] = ($counts[$line->purchase_id] ?? 0) + 1;
            $totals[$line->purchase_id] ??= [];
            if (($amount = $this->lineAmount($line, 'purchases')) !== null) {
                $this->addMoney($totals[$line->purchase_id], $line->currency_code, $amount);
            }
        }
        $data = array_map(fn ($row) => ['id' => (int) $row->id, 'date' => $row->date,
            'entity' => ['id' => (int) $row->entity_id, 'name' => $row->entity_name], 'shared' => (int) $row->links_count > 1,
            'linked_unit_ids' => $linked->get($row->entity_id, collect())->pluck('unit_id')->map(fn ($id) => (int) $id)->all(),
            'totals_by_currency' => $totals[$row->id] ?? [], 'items_count' => $counts[$row->id] ?? 0], $page->items());

        return ['data' => $data, 'meta' => $this->meta($page)];
    }

    private function meta($page): array
    {
        return ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), 'per_page' => $page->perPage()];
    }
}
