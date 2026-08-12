<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\CheckResource;
use App\Models\Check;
use App\Services\Checks\CheckCommodityService;
use App\Services\Checks\CheckServiceItemService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckController extends Controller
{
    public function __construct(
        private readonly CheckCommodityService $checkCommodityService,
        private readonly CheckServiceItemService $checkServiceItemService,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $baseQuery = $this->filteredChecksQuery($request);
        $totalAmount = (clone $baseQuery)->sum('amount');
        $checksQuery = (clone $baseQuery)
            ->with([
                'entity.classification',
                'entity.units' => fn ($query) => $query
                    ->select('units.id', 'units.name')
                    ->without(['fields', 'labels', 'telephones', 'uris']),
            ])
            ->withCount(['items', 'serviceItems']);

        $this->applySort($checksQuery, $request);

        $checks = $checksQuery->get();
        $this->attachTableSummaries($checks);
        $projectTotals = $this->projectTotals($request);

        return response()->json([
            'data' => CheckResource::collection($checks)->resolve($request),
            'meta' => [
                'total_amount' => (float) $totalAmount,
                'items_count' => (int) $checks->sum(fn ($check) => ($check->items_count ?? 0) + ($check->service_items_count ?? 0)),
                'project_totals' => $projectTotals,
                'without_project_total' => (float) (collect($projectTotals)
                    ->firstWhere('project_id', null)['total'] ?? 0),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $data = $this->validated($request, withItems: true);
        $commodities = $data['commodities'] ?? [];
        $services = $data['services'] ?? [];
        unset($data['commodities'], $data['services']);

        $check = DB::transaction(function () use ($data, $commodities, $services) {
            $check = Check::create($data);

            foreach ($commodities as $commodity) {
                $this->checkCommodityService->create($check, $commodity, withRelations: false);
            }

            foreach ($services as $service) {
                $this->checkServiceItemService->create($check, $service, withRelations: false);
            }

            return $check;
        });

        return response()->json(
            new CheckResource($this->findForResponse($check->id)),
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(Check $check)
    {
        return new CheckResource($this->findForResponse($check->id));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Check $check)
    {
        $check->update($this->validated($request, true));

        return new CheckResource($this->findForResponse($check->id));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Check $check)
    {
        if ($check->logisticsExpenses()->exists()) {
            return response()->json([
                'message' => 'Нельзя удалить чек, связанный с расходами рейса. Сначала отвяжите его от рейсов.',
            ], 409);
        }

        $check->delete();

        return response()->json(null, 204);
    }

    private function validated(
        Request $request,
        bool $partial = false,
        bool $withItems = false
    ): array {
        $dateRule = $partial ? 'sometimes' : 'required';
        $entityRule = $partial ? 'sometimes' : 'required';

        $rules = [
            'date' => [$dateRule, 'date'],
            'entity_id' => [$entityRule, 'exists:entities,id'],
            'amount' => ['nullable', 'numeric', 'min:0'],
        ];

        if ($withItems) {
            $rules = [
                ...$rules,
                'commodities' => ['nullable', 'array', 'max:100'],
                'commodities.*.commodity_id' => ['required', 'exists:commodities,id'],
                'commodities.*.warehouse_id' => ['nullable', 'exists:warehouses,id'],
                'commodities.*.quantity' => ['nullable', 'numeric', 'min:0'],
                'commodities.*.measure_id' => ['nullable', 'exists:measures,id'],
                'commodities.*.expense_article_id' => ['nullable', 'exists:expense_articles,id'],
                'commodities.*.price' => ['nullable', 'numeric', 'min:0'],
                'services' => ['nullable', 'array', 'max:100'],
                'services.*.service_id' => ['required', 'exists:services,id'],
                'services.*.quantity' => ['nullable', 'numeric', 'min:0'],
                'services.*.measure_id' => ['nullable', 'exists:measures,id'],
                'services.*.expense_article_id' => ['nullable', 'exists:expense_articles,id'],
                'services.*.price' => ['nullable', 'numeric', 'min:0'],
            ];
        }

        return $request->validate($rules);
    }

    private function filteredChecksQuery(Request $request)
    {
        return Check::query()
            ->when($request->filled('entity_id'), fn ($query) => $query->where('entity_id', $request->input('entity_id')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('date', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('date', '<=', $request->input('date_to')))
            ->when($request->filled('project_id'), function ($query) use ($request) {
                $projectId = $request->input('project_id');

                $query->where(function ($query) use ($projectId) {
                    $query
                        ->whereHas('items.commodity', fn ($query) => $query->where('project_id', $projectId))
                        ->orWhereHas('serviceItems.service', fn ($query) => $query->where('project_id', $projectId));
                });
            });
    }

    private function applySort($query, Request $request): void
    {
        $sortBy = in_array($request->input('sort_by'), ['date', 'amount'], true)
            ? $request->input('sort_by')
            : 'date';

        $direction = filter_var($request->input('sort_desc', true), FILTER_VALIDATE_BOOLEAN)
            ? 'desc'
            : 'asc';

        $query->orderBy($sortBy, $direction);

        if ($sortBy !== 'date') {
            $query->orderByDesc('date');
        }

        $query->orderByDesc('id');
    }

    private function projectTotals(Request $request): array
    {
        $rows = collect()
            ->merge($this->commodityProjectTotals($request))
            ->merge($this->serviceProjectTotals($request));

        $grouped = $rows->reduce(function ($carry, $row) {
            $key = $row->project_id ?: 'without_project';

            if (! isset($carry[$key])) {
                $carry[$key] = [
                    'project_id' => $row->project_id ? (int) $row->project_id : null,
                    'project_name' => $row->project_name ?: 'Без проекта',
                    'total' => 0.0,
                ];
            }

            $carry[$key]['total'] += (float) $row->total;

            return $carry;
        }, []);

        return collect($grouped)
            ->sortByDesc('total')
            ->values()
            ->all();
    }

    private function commodityProjectTotals(Request $request)
    {
        return DB::table('check_commodity as cc')
            ->join('checks as ch', 'ch.id', '=', 'cc.check_id')
            ->join('commodities as c', 'c.id', '=', 'cc.commodity_id')
            ->leftJoin('projects as p', 'p.id', '=', 'c.project_id')
            ->when($request->filled('entity_id'), fn ($query) => $query->where('ch.entity_id', $request->input('entity_id')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('ch.date', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('ch.date', '<=', $request->input('date_to')))
            ->when($request->filled('project_id'), fn ($query) => $query->where('c.project_id', $request->input('project_id')))
            ->select([
                'c.project_id',
                'p.name as project_name',
            ])
            ->selectRaw('SUM(cc.quantity * cc.price) as total')
            ->groupBy('c.project_id', 'p.name')
            ->get();
    }

    private function serviceProjectTotals(Request $request)
    {
        return DB::table('check_service as cs')
            ->join('checks as ch', 'ch.id', '=', 'cs.check_id')
            ->join('services as s', 's.id', '=', 'cs.service_id')
            ->leftJoin('projects as p', 'p.id', '=', 's.project_id')
            ->when($request->filled('entity_id'), fn ($query) => $query->where('ch.entity_id', $request->input('entity_id')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('ch.date', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('ch.date', '<=', $request->input('date_to')))
            ->when($request->filled('project_id'), fn ($query) => $query->where('s.project_id', $request->input('project_id')))
            ->select([
                's.project_id',
                'p.name as project_name',
            ])
            ->selectRaw('SUM(cs.quantity * cs.price) as total')
            ->groupBy('s.project_id', 'p.name')
            ->get();
    }

    private function attachTableSummaries($checks): void
    {
        $checkIds = $checks->modelKeys();

        if ($checkIds === []) {
            return;
        }

        $rows = collect()
            ->merge($this->commodityTableSummaryRows($checkIds))
            ->merge($this->serviceTableSummaryRows($checkIds))
            ->groupBy('check_id');

        foreach ($checks as $check) {
            $checkRows = $rows->get($check->id, collect());

            $check->setAttribute('table_summary', [
                'expense_articles' => $this->uniqueTableSummaryValues(
                    $checkRows,
                    'expense_article_id',
                    'expense_article_name',
                    'Без статьи',
                    'expense_article_color',
                ),
                'projects' => $this->uniqueTableSummaryValues(
                    $checkRows,
                    'project_id',
                    'project_name',
                    'Без проекта',
                ),
            ]);
        }
    }

    private function commodityTableSummaryRows(array $checkIds)
    {
        return DB::table('check_commodity as item')
            ->join('commodities as source', 'source.id', '=', 'item.commodity_id')
            ->leftJoin('expense_articles as item_article', 'item_article.id', '=', 'item.expense_article_id')
            ->leftJoin('expense_articles as default_article', 'default_article.id', '=', 'source.expense_article_id')
            ->leftJoin('projects as project', 'project.id', '=', 'source.project_id')
            ->whereIn('item.check_id', $checkIds)
            ->select('item.check_id', 'source.project_id', 'project.name as project_name')
            ->selectRaw('COALESCE(item_article.id, default_article.id) as expense_article_id')
            ->selectRaw('COALESCE(item_article.name, default_article.name) as expense_article_name')
            ->selectRaw('COALESCE(item_article.color, default_article.color) as expense_article_color')
            ->get();
    }

    private function serviceTableSummaryRows(array $checkIds)
    {
        return DB::table('check_service as item')
            ->join('services as source', 'source.id', '=', 'item.service_id')
            ->leftJoin('expense_articles as item_article', 'item_article.id', '=', 'item.expense_article_id')
            ->leftJoin('expense_articles as default_article', 'default_article.id', '=', 'source.expense_article_id')
            ->leftJoin('projects as project', 'project.id', '=', 'source.project_id')
            ->whereIn('item.check_id', $checkIds)
            ->select('item.check_id', 'source.project_id', 'project.name as project_name')
            ->selectRaw('COALESCE(item_article.id, default_article.id) as expense_article_id')
            ->selectRaw('COALESCE(item_article.name, default_article.name) as expense_article_name')
            ->selectRaw('COALESCE(item_article.color, default_article.color) as expense_article_color')
            ->get();
    }

    private function uniqueTableSummaryValues(
        $rows,
        string $idField,
        string $nameField,
        string $fallbackName,
        ?string $colorField = null,
    ): array {
        return $rows
            ->map(fn ($row) => [
                'id' => $row->{$idField} ? (int) $row->{$idField} : null,
                'name' => $row->{$nameField} ?: $fallbackName,
                ...($colorField ? ['color' => $row->{$colorField} ?: null] : []),
            ])
            ->unique(fn ($value) => ($value['id'] ?? 'none').':'.$value['name'])
            ->values()
            ->all();
    }

    private function findForResponse(int $id): Check
    {
        return Check::query()
            ->with([
                'entity.classification',
                'entity.units' => fn ($query) => $query
                    ->select('units.id', 'units.name')
                    ->without(['fields', 'labels', 'telephones', 'uris']),
                'items',
                'serviceItems',
                'logisticsExpenses.category',
                'logisticsExpenses.trip',
            ])
            ->withCount(['items', 'serviceItems'])
            ->findOrFail($id);
    }
}
