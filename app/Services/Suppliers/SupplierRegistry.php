<?php

namespace App\Services\Suppliers;

use App\Models\SupplierProfile;
use App\Models\Unit;
use Generator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierRegistry
{
    public const EXPORT_LIMIT = 10000;

    public function __construct(private SupplierAnalytics $analytics, private SupplierProfileWriter $profiles) {}

    public function index(array $filters, array $period): array
    {
        $query = $this->filtered($filters, $period);
        // The counters describe the entire filtered set, independently of pagination.
        $summary = (clone $query)->selectRaw('COUNT(*) as suppliers_count,
            COALESCE(SUM(CASE WHEN is_watched = 1 AND status <> ? THEN 1 ELSE 0 END), 0) as watched_count,
            COALESCE(SUM(review_due), 0) as review_due_count,
            COALESCE(SUM(CASE WHEN overdue_deliveries > 0 THEN 1 ELSE 0 END), 0) as overdue_delivery_suppliers_count,
            COALESCE(SUM(CASE WHEN uncertain_sends > 0 THEN 1 ELSE 0 END), 0) as uncertain_send_suppliers_count', ['archived'])->first();
        $page = $this->sorted($query, $filters)->paginate($filters['per_page'] ?? 25,
            ['unit_id', 'name', 'is_supplier'], 'page', $filters['page'] ?? 1);

        return [
            'data' => $this->rows($page->getCollection(), $period),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(),
                'total' => $page->total(), 'per_page' => $page->perPage()],
            'summary' => array_map(fn ($value) => (int) $value, (array) $summary),
            'period' => $period,
        ];
    }

    public function show(int $unitId): array
    {
        $unit = Unit::withoutEagerLoads()->findOrFail($unitId, ['id', 'name', 'is_supplier']);
        $profile = SupplierProfile::with('owner:id,name')->where('unit_id', $unitId)->first();
        $entities = $this->entities([$unitId])[$unitId] ?? [];
        $entityIds = array_column($entities, 'id');

        return [
            'unit' => ['id' => $unit->id, 'name' => $unit->name, 'is_supplier' => (bool) $unit->is_supplier],
            'profile' => $this->profiles->data($profile, $unitId),
            'sources' => $this->sources(collect([(object) ['unit_id' => $unitId, 'is_supplier' => $unit->is_supplier]]), $profile ? [$unitId] : [])[$unitId] ?? [],
            'entities' => $entities,
            'contacts' => [
                'emails' => $this->contactQuery('emails', 'email', $unitId, $entityIds)
                    ->whereNull('emails.deleted_at')->where('emails.is_active', true)
                    ->orderBy('emails.address')->get(['emails.address', 'emails.name'])->all(),
                'telephones' => $this->contactQuery('telephones', 'telephone', $unitId, $entityIds)
                    ->orderBy('telephones.number')->get(['telephones.number'])->all(),
                'uris' => $this->contactQuery('uris', 'uri', $unitId, $entityIds)
                    ->orderBy('uris.address')->get(['uris.address as uri'])->all(),
            ],
            'pipelines' => DB::table('supplier_pipeline_cards as card')
                ->join('supplier_pipelines as pipeline', 'pipeline.id', '=', 'card.supplier_pipeline_id')
                ->join('supplier_pipeline_stages as stage', 'stage.id', '=', 'card.supplier_pipeline_stage_id')
                ->where('card.unit_id', $unitId)->orderBy('pipeline.name')->orderBy('card.id')
                ->get(['pipeline.name', 'stage.name as stage', 'card.next_contact_at'])->all(),
        ];
    }

    /** Freeze just the bounded selection of scalar IDs before starting a stream. */
    public function exportSelection(array $filters, array $period): array
    {
        $ids = $this->sorted($this->filtered($filters, $period), $filters)
            ->limit(self::EXPORT_LIMIT + 1)->pluck('unit_id')->map(fn ($id) => (int) $id)->all();
        if (count($ids) > self::EXPORT_LIMIT) {
            throw ValidationException::withMessages(['export' => 'В выгрузке больше 10 000 поставщиков. Уточните фильтры.']);
        }

        return $ids;
    }

    public function exportRows(array $ids, array $period): Generator
    {
        foreach (array_chunk($ids, 200) as $batch) {
            $units = DB::table('units')->whereIn('id', $batch)->get(['id as unit_id', 'name', 'is_supplier'])->keyBy('unit_id');
            $ordered = collect($batch)->map(fn ($id) => $units->get($id))->filter()->values();
            foreach ($this->rows($ordered, $period, false) as $row) {
                $profile = $row['profile'];
                $orders = $row['metrics']['orders'];
                $purchases = $row['metrics']['purchases'];
                yield [$row['unit']['id'], $row['unit']['name'], $profile['status'], $profile['priority'],
                    $profile['is_watched'] ? 'Да' : 'Нет', $profile['owner']['name'] ?? '',
                    $profile['next_review_on'] ?? '', $profile['review_state'], $period['date_from'], $period['date_to'],
                    $orders['count'], $this->currencyTotals($orders['totals_by_currency']),
                    $orders['unpriced_items_count'], $orders['per_30_days'], $orders['average_interval_days'],
                    $orders['last_at_all_time'], $purchases['count'], $this->currencyTotals($purchases['totals_by_currency']),
                    $purchases['unpriced_items_count'], $purchases['per_30_days'], $purchases['average_interval_days'],
                    $purchases['last_at_all_time'], $row['metrics']['shared_purchases']['count'],
                    $row['metrics']['monitoring']['overdue_delivery_count'], $row['metrics']['monitoring']['uncertain_send_count']];
            }
        }
    }

    private function filtered(array $filters, array $period): Builder
    {
        [$from, $to] = $this->analytics->datetimeBounds($period);
        $today = now('Europe/Moscow')->toDateString();
        $placed = fn () => DB::table('supplier_orders as so')->whereColumn('so.unit_id', 'sp.unit_id')
            ->whereIn('so.status', ['sent', 'confirmed', 'closed'])->whereNotNull('so.sent_at');
        // Every path starts with a manually created profile: source evidence never adds a supplier.
        $base = DB::table('supplier_profiles as sp')->join('units as u', 'u.id', '=', 'sp.unit_id')
            ->select(['sp.unit_id', 'u.name', 'u.is_supplier', 'sp.status', 'sp.priority', 'sp.owner_user_id', 'sp.is_watched', 'sp.next_review_on'])
            ->selectSub($placed()->where('so.sent_at', '>=', $from)->where('so.sent_at', '<', $to)->selectRaw('COUNT(*)'), 'period_orders_count')
            ->selectSub($placed()->selectRaw('MAX(so.sent_at)'), 'last_order_at')
            ->selectSub(DB::table('supplier_orders as overdue')->whereColumn('overdue.unit_id', 'sp.unit_id')
                ->whereIn('overdue.status', ['sent', 'confirmed'])->where('overdue.delivery_date', '<', $today)->selectRaw('COUNT(*)'), 'overdue_deliveries')
            ->selectSub(DB::table('supplier_orders as uncertain')->whereColumn('uncertain.unit_id', 'sp.unit_id')
                ->whereIn('uncertain.status', ['sending', 'send_unknown'])->selectRaw('COUNT(*)'), 'uncertain_sends')
            ->selectRaw('CASE WHEN sp.is_watched = 1 AND sp.status IN (?, ?) AND sp.next_review_on <= ? THEN 1 ELSE 0 END as review_due', ['active', 'potential', $today]);
        $query = DB::query()->fromSub($base, 'registry');
        $scope = $filters['scope'] ?? 'watched';
        if ($scope === 'watched') {
            $query->where('is_watched', true)->where('status', '<>', 'archived');
        } elseif ($scope === 'archived') {
            $query->where('status', 'archived');
        }
        foreach (['status', 'priority', 'owner_user_id'] as $field) {
            if (isset($filters[$field]) && $filters[$field] !== '') {
                $query->where($field, $filters[$field]);
            }
        }
        $search = trim($filters['search'] ?? '');
        if ($search !== '') {
            $query->where(function (Builder $query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhereExists(fn (Builder $entities) => $entities->selectRaw('1')->from('entity_unit as eu')
                        ->join('entities as e', 'e.id', '=', 'eu.entity_id')->whereColumn('eu.unit_id', 'registry.unit_id')
                        ->where('e.name', 'like', '%'.$search.'%'));
            });
        }
        match ($filters['attention'] ?? 'all') {
            'review_due' => $query->where('review_due', 1),
            'delivery_overdue' => $query->where('overdue_deliveries', '>', 0),
            'uncertain_send' => $query->where('uncertain_sends', '>', 0),
            'no_orders' => $query->where('period_orders_count', 0),
            default => null,
        };

        return $query;
    }

    private function sorted(Builder $query, array $filters): Builder
    {
        $column = match ($filters['sort'] ?? 'name') {
            'order_count' => 'period_orders_count', 'last_order' => 'last_order_at',
            'next_review' => 'next_review_on', default => 'name',
        };
        if (in_array($column, ['last_order_at', 'next_review_on'], true)) {
            $query->orderByRaw($column.' IS NULL');
        }

        return $query->orderBy($column, $filters['direction'] ?? 'asc')->orderBy('unit_id');
    }

    private function rows(Collection $units, array $period, bool $withContext = true): array
    {
        $ids = $units->pluck('unit_id')->map(fn ($id) => (int) $id)->all();
        if ($ids === []) {
            return [];
        }
        $profiles = SupplierProfile::with('owner:id,name')->whereIn('unit_id', $ids)->get()->keyBy('unit_id');
        $metrics = $this->analytics->summaries($ids, $period);
        $entities = $withContext ? $this->entities($ids) : [];
        $sources = $withContext ? $this->sources($units, $profiles->keys()->all()) : [];

        return $units->map(fn ($unit) => [
            'unit' => ['id' => (int) $unit->unit_id, 'name' => $unit->name, 'is_supplier' => (bool) $unit->is_supplier],
            'profile' => $this->profiles->data($profiles->get($unit->unit_id), (int) $unit->unit_id),
            'sources' => $sources[$unit->unit_id] ?? [],
            'entities' => array_map(fn ($entity) => array_diff_key($entity, ['linked_unit_ids' => true]), $entities[$unit->unit_id] ?? []),
            'metrics' => $metrics[$unit->unit_id],
        ])->all();
    }

    private function entities(array $ids): array
    {
        $links = DB::table('entity_unit as eu')->join('entities as e', 'e.id', '=', 'eu.entity_id')
            ->whereIn('eu.unit_id', $ids)->orderBy('e.name')->distinct()->get(['eu.unit_id', 'e.id', 'e.name']);
        $linked = DB::table('entity_unit')->whereIn('entity_id', $links->pluck('id')->unique())
            ->select(['entity_id', 'unit_id'])->distinct()->orderBy('unit_id')->get()->groupBy('entity_id');
        $result = [];
        foreach ($links as $link) {
            $linkedIds = $linked->get($link->id, collect())->pluck('unit_id')->map(fn ($id) => (int) $id)->all();
            $result[$link->unit_id][] = ['id' => (int) $link->id, 'name' => $link->name,
                'shared' => count($linkedIds) > 1, 'linked_unit_ids' => $linkedIds];
        }

        return $result;
    }

    private function sources(Collection $units, array $profileIds): array
    {
        $ids = $units->pluck('unit_id')->all();
        $result = [];
        foreach ($units as $unit) {
            $result[$unit->unit_id] = in_array($unit->unit_id, $profileIds) ? [['kind' => 'manual', 'label' => 'Добавлен вручную']] : [];
            if ($unit->is_supplier) {
                $result[$unit->unit_id][] = ['kind' => 'supplier_flag', 'label' => 'Отмечен поставщиком'];
            }
        }
        $queries = [
            ['quotation', 'Есть предложения товаров', DB::table('quotations')->whereIn('unit_id', $ids)],
            ['supplier_order', 'Есть заявки поставщику', DB::table('supplier_orders')->whereIn('unit_id', $ids)],
            ['purchase', 'Есть закупки у связанного юрлица', DB::table('entity_unit')->join('purchases', 'purchases.entity_id', '=', 'entity_unit.entity_id')->whereIn('unit_id', $ids)],
            ['pipeline', 'Есть карточка воронки', DB::table('supplier_pipeline_cards')->whereIn('unit_id', $ids)],
        ];
        foreach ($queries as [$kind, $label, $query]) {
            foreach ($query->distinct()->pluck('unit_id') as $id) {
                $result[$id][] = ['kind' => $kind, 'label' => $label];
            }
        }

        return $result;
    }

    private function contactQuery(string $table, string $singular, int $unitId, array $entityIds): Builder
    {
        $unitPivot = $singular === 'email' ? 'email_unit' : ($singular === 'telephone' ? 'telephone_unit' : 'unit_uri');
        $entityPivot = $singular === 'email' ? 'email_entity' : 'entity_'.$singular;

        return DB::table($table)->where(function (Builder $query) use ($table, $singular, $unitPivot, $entityPivot, $unitId, $entityIds): void {
            $query->whereExists(fn (Builder $link) => $link->selectRaw('1')->from($unitPivot)
                ->whereColumn($unitPivot.'.'.$singular.'_id', $table.'.id')->where('unit_id', $unitId))
                ->orWhereExists(fn (Builder $link) => $link->selectRaw('1')->from($entityPivot)
                    ->whereColumn($entityPivot.'.'.$singular.'_id', $table.'.id')->whereIn('entity_id', $entityIds));
        });
    }

    private function currencyTotals(array $totals): string
    {
        ksort($totals);

        return implode(' | ', array_map(fn ($currency, $amount) => ($currency === 'UNKNOWN' ? 'Валюта не указана' : $currency).': '.$amount,
            array_keys($totals), array_values($totals)));
    }
}
