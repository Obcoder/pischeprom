<?php

namespace App\Http\Controllers\API\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Orders\MobileOrderPresenter;
use App\Services\Orders\MobileOrderQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileDeliveryMapController extends Controller
{
    public function configuration(): JsonResponse
    {
        $key = trim((string) config('gis.providers.yandex.api_key'));
        $scriptUrl = (string) config('gis.providers.yandex.map_script_url');
        $trustedScript = preg_match('~\Ahttps://(?:api-maps|enterprise\.api-maps)\.yandex\.ru/2\.1/?\z~', $scriptUrl) === 1;

        return response()->json(['data' => [
            'configured' => $key !== '' && $trustedScript,
            'api_key' => $trustedScript && $key !== '' ? $key : null,
            'script_url' => $trustedScript ? $scriptUrl : null,
            'default_center' => [(float) config('gis.map.default_center.lat'), (float) config('gis.map.default_center.lon')],
            'default_zoom' => max(1, min(19, (int) config('gis.map.default_zoom', 5))),
        ]]);
    }

    public function orders(Request $request, MobileOrderQuery $query, MobileOrderPresenter $presenter): JsonResponse
    {
        $data = $query->validated($request);
        $orders = $query->build($data)->select(['id', 'number', 'entity_id', 'submitted_at'])
            ->with([
                'entity' => fn ($entity) => $entity->withoutEagerLoads()->select(['id', 'name']),
                ...$presenter->deliveryRelations(),
            ])->paginate($data['per_page'] ?? 100);

        return response()->json([
            'data' => $orders->getCollection()->map(fn (Order $order) => [
                'id' => $order->id,
                'number' => $order->number,
                'entity' => $order->entity?->only(['id', 'name']),
                'submitted_at' => $order->submitted_at?->toISOString(),
                'delivery_addresses' => $presenter->deliveryAddresses($order),
            ])->values()->all(),
            'meta' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'total' => $orders->total(),
                'per_page' => $orders->perPage(),
            ],
        ]);
    }
}
