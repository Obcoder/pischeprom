<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\CheckServiceResource;
use App\Models\Check;
use App\Models\CheckService;
use App\Services\Checks\CheckServiceItemService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckServiceController extends Controller
{
    public function __construct(private readonly CheckServiceItemService $checkServiceItemService) {}

    public function store(Request $request, ?Check $check = null)
    {
        $data = $this->validated($request, $check);
        $check = $check ?: Check::findOrFail($data['check_id']);

        $item = DB::transaction(
            fn () => $this->checkServiceItemService->create($check, $data)
        );

        return response()->json(new CheckServiceResource($item), 201);
    }

    public function update(Request $request, CheckService $checkService)
    {
        $data = $request->validate([
            'service_id' => ['sometimes', 'required', 'exists:services,id'],
            'quantity' => ['sometimes', 'required', 'numeric', 'min:0'],
            'measure_id' => ['sometimes', 'nullable', 'exists:measures,id'],
            'expense_article_id' => ['sometimes', 'nullable', 'exists:expense_articles,id'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
        ]);

        $item = DB::transaction(
            fn () => $this->checkServiceItemService->update($checkService, $data)
        );

        return new CheckServiceResource($item);
    }

    public function destroy(CheckService $checkService)
    {
        DB::transaction(fn () => $this->checkServiceItemService->delete($checkService));

        return response()->json(null, 204);
    }

    private function validated(Request $request, ?Check $check = null): array
    {
        return $request->validate([
            'check_id' => [$check ? 'nullable' : 'required', 'exists:checks,id'],
            'service_id' => ['required', 'exists:services,id'],
            'quantity' => ['nullable', 'numeric', 'min:0'],
            'measure_id' => ['nullable', 'exists:measures,id'],
            'expense_article_id' => ['nullable', 'exists:expense_articles,id'],
            'price' => ['nullable', 'numeric', 'min:0'],
        ]);
    }
}
