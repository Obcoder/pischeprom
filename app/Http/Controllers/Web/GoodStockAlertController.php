<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Good;
use App\Services\Goods\GoodStockAlertManager;
use App\Services\Goods\GoodStockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class GoodStockAlertController extends Controller
{
    public function store(
        Request $request,
        Good $good,
        GoodStockService $stock,
        GoodStockAlertManager $alerts,
    ): JsonResponse {
        abort_unless($good->is_published, 404);

        $good->loadMissing(['seo', 'stockAvailability']);

        if (
            ! $stock->canSubscribe($good)
            || $stock->isInStock($good)
        ) {
            return response()->json([
                'message' => 'Товар уже есть в наличии.',
            ], 409);
        }

        try {
            $result = $alerts->createPending($good, $request->user());
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], str_contains($exception->getMessage(), 'не настроена') ? 503 : 409);
        }

        return response()->json([
            'message' => 'Откройте MAX и подтвердите запуск бота.',
            'deep_link' => $result['deep_link'],
            'expires_at' => $result['alert']->expires_at?->toISOString(),
        ], 201);
    }
}
