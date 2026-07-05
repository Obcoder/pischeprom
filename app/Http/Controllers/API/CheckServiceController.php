<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\CheckServiceResource;
use App\Models\Check;
use App\Models\CheckService;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckServiceController extends Controller
{
    public function store(Request $request, ?Check $check = null)
    {
        $data = $this->validated($request, $check);
        $check = $check ?: Check::findOrFail($data['check_id']);
        $service = Service::findOrFail($data['service_id']);

        $item = DB::transaction(function () use ($check, $service, $data) {
            $item = CheckService::create([
                'check_id' => $check->id,
                'service_id' => $service->id,
                'quantity' => $data['quantity'] ?? 1,
                'measure_id' => $data['measure_id'] ?? null,
                'expense_article_id' => $data['expense_article_id'] ?? $service->expense_article_id,
                'price' => $data['price'] ?? 0,
            ]);

            return $item->fresh($this->relations());
        });

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

        $item = DB::transaction(function () use ($checkService, $data) {
            if (array_key_exists('service_id', $data) && ! array_key_exists('expense_article_id', $data)) {
                $service = Service::findOrFail($data['service_id']);
                $data['expense_article_id'] = $service->expense_article_id;
            }

            $checkService->update($data);

            return $checkService->fresh($this->relations());
        });

        return new CheckServiceResource($item);
    }

    public function destroy(CheckService $checkService)
    {
        DB::transaction(function () use ($checkService) {
            $checkService->delete();
        });

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

    private function relations(): array
    {
        return [
            'service.expenseArticle',
            'service.project',
            'expenseArticle',
            'measure',
        ];
    }
}
