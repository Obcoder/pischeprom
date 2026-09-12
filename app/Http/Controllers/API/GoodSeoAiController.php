<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\GenerateGoodSeoAiRequest;
use App\Models\Good;
use App\Services\Seo\GoodSeoAiException;
use App\Services\Seo\GoodSeoAiService;
use Illuminate\Http\JsonResponse;

class GoodSeoAiController extends Controller
{
    public function __invoke(GenerateGoodSeoAiRequest $request, Good $good, GoodSeoAiService $service): JsonResponse
    {
        $validated = $request->validated();

        try {
            $value = $service->generate($good, $validated['field'], $validated['context'] ?? []);
        } catch (GoodSeoAiException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'code' => $exception->errorCode,
            ], $exception->httpStatus);
        }

        return response()->json(['field' => $validated['field'], 'value' => $value]);
    }
}
