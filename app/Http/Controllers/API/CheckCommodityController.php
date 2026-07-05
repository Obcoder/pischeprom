<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\CheckCommodityResource;
use App\Models\Check;
use App\Models\CheckCommodity;
use App\Models\Commodity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckCommodityController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, ?Check $check = null)
    {
        $data = $this->validated($request, $check);
        $check = $check ?: Check::findOrFail($data['check_id']);
        $commodity = Commodity::findOrFail($data['commodity_id']);

        $item = DB::transaction(function () use ($check, $commodity, $data) {
            $item = CheckCommodity::create([
                'check_id' => $check->id,
                'commodity_id' => $commodity->id,
                'quantity' => $data['quantity'] ?? 1,
                'measure_id' => $data['measure_id'] ?? null,
                'expense_article_id' => $data['expense_article_id'] ?? $commodity->expense_article_id,
                'price' => $data['price'] ?? 0,
            ]);

            $this->refreshCheckAmount($check);

            return $item->fresh($this->relations());
        });

        return response()->json(new CheckCommodityResource($item), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, CheckCommodity $checkCommodity)
    {
        $data = $request->validate([
            'commodity_id' => ['sometimes', 'required', 'exists:commodities,id'],
            'quantity' => ['sometimes', 'required', 'numeric', 'min:0'],
            'measure_id' => ['sometimes', 'nullable', 'exists:measures,id'],
            'expense_article_id' => ['sometimes', 'nullable', 'exists:expense_articles,id'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
        ]);

        $item = DB::transaction(function () use ($checkCommodity, $data) {
            $checkCommodity->update($data);
            $this->refreshCheckAmount($checkCommodity->check);

            return $checkCommodity->fresh($this->relations());
        });

        return new CheckCommodityResource($item);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CheckCommodity $checkCommodity)
    {
        DB::transaction(function () use ($checkCommodity) {
            $check = $checkCommodity->check;
            $checkCommodity->delete();
            $this->refreshCheckAmount($check);
        });

        return response()->json(null, 204);
    }

    private function validated(Request $request, ?Check $check = null): array
    {
        return $request->validate([
            'check_id' => [$check ? 'nullable' : 'required', 'exists:checks,id'],
            'commodity_id' => ['required', 'exists:commodities,id'],
            'quantity' => ['nullable', 'numeric', 'min:0'],
            'measure_id' => ['nullable', 'exists:measures,id'],
            'expense_article_id' => ['nullable', 'exists:expense_articles,id'],
            'price' => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    private function refreshCheckAmount(Check $check): void
    {
        $check->forceFill([
            'amount' => CheckCommodity::query()
                ->where('check_id', $check->id)
                ->selectRaw('COALESCE(SUM(quantity * price), 0) as amount')
                ->value('amount') ?? 0,
        ])->save();
    }

    private function relations(): array
    {
        return [
            'commodity.avaMedia',
            'commodity.expenseArticle',
            'commodity.project',
            'expenseArticle',
            'measure',
        ];
    }
}
