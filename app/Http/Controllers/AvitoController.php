<?php

namespace App\Http\Controllers;

use App\Services\AvitoService;
use Illuminate\Http\Request;

class AvitoController extends Controller
{
    protected $avitoService;

    public function __construct(AvitoService $avitoService)
    {
        $this->avitoService = $avitoService;
    }

    public function getUserInfo(Request $request)
    {
        try {
            $userInfo = $this->avitoService->getUserInfo();
            return response()->json($userInfo);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
