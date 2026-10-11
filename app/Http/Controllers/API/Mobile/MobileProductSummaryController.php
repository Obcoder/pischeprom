<?php

namespace App\Http\Controllers\API\Mobile;

use App\Http\Controllers\Controller;
use App\Services\Orders\MobileProductSummary;
use App\Services\Orders\OrderDeliveryDateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileProductSummaryController extends Controller
{
    public function index(Request $request, MobileProductSummary $products): JsonResponse
    {
        $data = $request->validate([
            'delivery_date' => OrderDeliveryDateService::dateRules(),
        ]);

        return response()->json($products->forDate($data['delivery_date'] ?? now()->toDateString()));
    }
}
