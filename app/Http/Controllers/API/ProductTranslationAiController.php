<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\TranslateProductAiRequest;
use App\Models\Category;
use App\Services\Products\ProductTranslationAiException;
use App\Services\Products\ProductTranslationAiService;
use Illuminate\Http\JsonResponse;

class ProductTranslationAiController extends Controller
{
    public function __invoke(TranslateProductAiRequest $request, ProductTranslationAiService $service): JsonResponse
    {
        $validated = $request->validated();
        $category = empty($validated['category_id'])
            ? null
            : Category::query()->whereKey($validated['category_id'])->value('name');

        try {
            $translations = $service->translate($validated['rus'], $validated['languages'], $category);
        } catch (ProductTranslationAiException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'code' => $exception->errorCode,
            ], $exception->httpStatus);
        }

        return response()->json(['translations' => $translations]);
    }
}
