<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\SupplierOrder;
use App\Models\Unit;
use App\Services\SupplierOrders\SupplierOrderWriter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupplierOrderController extends Controller
{
    public function units(Request $request): JsonResponse
    {
        $input = $request->validate(['search' => ['nullable', 'string', 'max:120']]);
        $search = trim($input['search'] ?? '');

        return response()->json(Unit::without(['fields', 'labels', 'telephones', 'uris'])
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->orderBy('name')->limit(20)->get(['id', 'name']));
    }

    public function index(Request $request, SupplierOrderWriter $writer): JsonResponse
    {
        $input = $request->validate([
            'search' => ['nullable', 'string', 'max:150'], 'status' => ['nullable', Rule::in(SupplierOrder::STATUSES)],
            'unit_id' => ['nullable', 'integer', 'min:1'], 'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);
        $search = trim($input['search'] ?? '');
        $page = SupplierOrder::with(['unit', 'warehouse', 'items'])
            ->when($input['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($input['unit_id'] ?? null, fn ($query, $unitId) => $query->where('unit_id', $unitId))
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q->where('number', 'like', '%'.$search.'%')
                ->orWhereHas('unit', fn ($u) => $u->where('name', 'like', '%'.$search.'%'))
                ->orWhereHas('items', fn ($i) => $i->where('name', 'like', '%'.$search.'%'))))
            ->orderByDesc('id')->paginate($input['per_page'] ?? 25);
        $page->through(fn ($order) => $writer->data($order));

        return response()->json($page);
    }

    public function show(SupplierOrder $supplierOrder, SupplierOrderWriter $writer): JsonResponse
    {
        return response()->json($writer->data($supplierOrder));
    }

    public function store(Request $request, SupplierOrderWriter $writer): JsonResponse
    {
        $order = $writer->save(null, $request->all(), $request->user());

        return response()->json($writer->data($order), $order->wasRecentlyCreated ? 201 : 200);
    }

    public function update(Request $request, SupplierOrder $supplierOrder, SupplierOrderWriter $writer): JsonResponse
    {
        return response()->json($writer->data($writer->save($supplierOrder, $request->all(), $request->user())));
    }

    public function updateStatus(Request $request, SupplierOrder $supplierOrder, SupplierOrderWriter $writer): JsonResponse
    {
        $input = $request->validate(['revision' => ['required', 'integer', 'min:1'], 'status' => ['required', Rule::in(SupplierOrder::STATUSES)]]);

        return response()->json($writer->data($writer->changeStatus($supplierOrder, $input['status'], (int) $input['revision'], $request->user())));
    }

    public function destroy(Request $request, SupplierOrder $supplierOrder, SupplierOrderWriter $writer): JsonResponse
    {
        $input = $request->validate(['revision' => ['required', 'integer', 'min:1']]);
        $writer->delete($supplierOrder, (int) $input['revision']);

        return response()->json(null, 204);
    }
}
