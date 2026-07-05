<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\CheckResource;
use App\Models\Check;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $baseQuery = $this->filteredChecksQuery($request);
        $totalAmount = (clone $baseQuery)->sum('amount');
        $checksQuery = (clone $baseQuery)
            ->with(['entity.classification'])
            ->withCount(['items', 'serviceItems']);

        $this->applySort($checksQuery, $request);

        $checks = $checksQuery->get();

        return response()->json([
            'data' => CheckResource::collection($checks)->resolve($request),
            'meta' => [
                'total_amount' => (float) $totalAmount,
                'items_count' => (int) $checks->sum(fn ($check) => ($check->items_count ?? 0) + ($check->service_items_count ?? 0)),
                'project_totals' => $this->projectTotals($request),
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $check = Check::create($this->validated($request));

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
        $check->delete();

        return response()->json(null, 204);
    }

    private function validated(Request $request, bool $partial = false): array
    {
        $dateRule = $partial ? 'sometimes' : 'required';
        $entityRule = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'date' => [$dateRule, 'date'],
            'entity_id' => [$entityRule, 'exists:entities,id'],
            'amount' => ['nullable', 'numeric', 'min:0'],
        ]);
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

    private function findForResponse(int $id): Check
    {
        return Check::query()
            ->with([
                'entity.classification',
                'items',
                'serviceItems',
            ])
            ->withCount(['items', 'serviceItems'])
            ->findOrFail($id);
    }
}
