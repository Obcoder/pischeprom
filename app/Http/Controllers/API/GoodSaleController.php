<?php

namespace App\Http\Controllers\API;

use App\Domain\Banking\Services\PaymentAllocationService;
use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Services\Goods\GoodSaleStockSynchronizer;
use App\Services\Goods\SaleStockRequestService;
use Illuminate\Http\Request;

class GoodSaleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(
        Request $request,
        SaleController $sales,
        PaymentAllocationService $paymentAllocations,
        GoodSaleStockSynchronizer $stock,
        SaleStockRequestService $requests,
    ) {
        $validated = $request->validate([
            'sale_id' => ['required', 'integer', 'exists:sales,id'],
        ]);

        return $sales->storeGood(
            $request,
            Sale::query()->findOrFail($validated['sale_id']),
            $paymentAllocations,
            $stock,
            $requests,
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        abort(405, 'Изменение позиции проведённой продажи пока не поддерживается.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        abort(405, 'Отмена позиции проведённой продажи пока не поддерживается.');
    }
}
