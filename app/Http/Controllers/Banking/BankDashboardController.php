<?php

namespace App\Http\Controllers\Banking;

use App\Domain\Banking\Services\BankDashboardService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class BankDashboardController extends Controller
{
    public function __invoke(BankDashboardService $dashboard): JsonResponse
    {
        Gate::authorize('bank.view');

        return response()->json([
            'data' => $dashboard->summary(Gate::allows('bank.view_sensitive')),
        ]);
    }
}
