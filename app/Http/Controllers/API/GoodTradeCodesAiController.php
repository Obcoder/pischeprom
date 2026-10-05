<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\RecommendGoodTradeCodesRequest;
use App\Services\Goods\GoodTradeCodesAiException;
use App\Services\Goods\GoodTradeCodesAiService;
use Illuminate\Http\JsonResponse;

class GoodTradeCodesAiController extends Controller
{
    public function availability(RecommendGoodTradeCodesRequest $request, GoodTradeCodesAiService $service): JsonResponse
    {
        return response()->json($service->availability());
    }

    public function recommend(RecommendGoodTradeCodesRequest $request, GoodTradeCodesAiService $service): JsonResponse
    {
        try {
            return response()->json($service->recommend($request->validated()));
        } catch (GoodTradeCodesAiException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'code' => $exception->errorCode,
            ], $exception->httpStatus);
        }
    }
}
