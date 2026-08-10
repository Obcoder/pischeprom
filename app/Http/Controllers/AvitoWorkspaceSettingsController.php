<?php

namespace App\Http\Controllers;

use App\Services\Avito\AvitoWorkspaceSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AvitoWorkspaceSettingsController extends Controller
{
    public function show(Request $request, AvitoWorkspaceSettingsService $settings): JsonResponse
    {
        $validated = $request->validate([
            'refresh' => ['nullable', 'boolean'],
        ]);

        return response()->json([
            'workspace' => $settings->settings((bool) ($validated['refresh'] ?? false)),
        ]);
    }

    public function update(Request $request, AvitoWorkspaceSettingsService $settings): JsonResponse
    {
        $validated = $request->validate([
            'selection' => ['required', 'string', 'max:80', 'regex:/^(server|connection:\d+)$/'],
        ]);

        return response()->json([
            'workspace' => $settings->select($validated['selection']),
            'message' => 'Рабочий кабинет Avito сохранён.',
        ]);
    }
}
