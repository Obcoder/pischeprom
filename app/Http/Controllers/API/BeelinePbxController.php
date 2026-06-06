<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\Telephony\BeelinePbxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BeelinePbxController extends Controller
{
    public function __invoke(Request $request, BeelinePbxService $service): JsonResponse
    {
        if (!$this->hasValidToken($request)) {
            return response()->json(['error' => 'Invalid token'], 401);
        }

        $result = $service->handle($request->all());

        return response()->json($result['data'], $result['status']);
    }

    protected function hasValidToken(Request $request): bool
    {
        $expected = config('services.beeline_pbx.crm_token')
            ?: config('services.beeline_pbx.api_token');

        if (!$expected) {
            return false;
        }

        $actual = $request->input('crm_token')
            ?: $request->input('token')
            ?: $request->bearerToken()
            ?: $request->header('X-Beeline-Token');

        return is_string($actual) && hash_equals((string) $expected, $actual);
    }
}
