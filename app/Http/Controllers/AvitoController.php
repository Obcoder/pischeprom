<?php

namespace App\Http\Controllers;

use App\Services\AvitoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class AvitoController extends Controller
{
    protected $avitoService;

    public function __construct(AvitoService $avitoService)
    {
        $this->avitoService = $avitoService;
    }

    /**
     * Получение списка объявлений из Avito
     */
    public function getAds(): JsonResponse
    {
        try {
            $ads = $this->avitoService->getAds();
            return response()->json([
                                        'success' => true,
                                        'data' => $ads,
                                    ]);
        } catch (\Exception $e) {
            Log::error('Avito API error: ' . $e->getMessage());
            return response()->json([
                                        'success' => false,
                                        'message' => 'Failed to fetch ads from Avito',
                                    ], 500);
        }
    }
}
