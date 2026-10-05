<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckGoodVatRequest;
use App\Services\Goods\GoodVatAiException;
use App\Services\Goods\GoodVatAiService;
use Illuminate\Http\JsonResponse;

class GoodVatAiController extends Controller
{
    public function availability(CheckGoodVatRequest $request, GoodVatAiService $service): JsonResponse
    {
        return response()->json($service->availability());
    }

    public function check(CheckGoodVatRequest $request, GoodVatAiService $service): JsonResponse
    {
        try {
            return response()->json($service->check($request->validated()));
        } catch (GoodVatAiException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'code' => $exception->errorCode,
            ], $exception->httpStatus);
        }
    }
}
