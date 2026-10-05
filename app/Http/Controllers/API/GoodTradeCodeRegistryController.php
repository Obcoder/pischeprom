<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\VerifyGoodTradeCodesRequest;
use App\Services\Goods\GoodTradeCodeRegistryService;
use Illuminate\Http\JsonResponse;

class GoodTradeCodeRegistryController extends Controller
{
    public function verify(VerifyGoodTradeCodesRequest $request, GoodTradeCodeRegistryService $service): JsonResponse
    {
        $results = [];
        foreach ($request->validated('codes') as $field => $code) {
            $results[] = $service->lookup($field, $code);
        }

        return response()->json([
            'results' => $results,
            'scope' => 'Проверяется наличие кода в указанном справочнике; данные кешируются до 24 часов. Соответствие кода конкретному товару и ставка НДС этой проверкой не устанавливаются.',
        ]);
    }
}
