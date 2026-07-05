<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\CheckResource;
use App\Models\Check;
use Illuminate\Http\Request;

class CheckController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $checks = Check::query()
            ->with(['entity.classification'])
            ->withCount('items')
            ->when($request->filled('entity_id'), fn ($query) => $query->where('entity_id', $request->input('entity_id')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('date', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('date', '<=', $request->input('date_to')))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();

        return response()->json(CheckResource::collection($checks)->resolve($request));
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

    private function findForResponse(int $id): Check
    {
        return Check::query()
            ->with([
                'entity.classification',
                'items',
            ])
            ->withCount('items')
            ->findOrFail($id);
    }
}
