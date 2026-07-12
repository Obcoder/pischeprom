<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\MaxSubscription;
use App\Services\MaxMessengerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaxSubscriptionController extends Controller
{
    public function __construct(private readonly MaxMessengerService $max)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $remote = null;

        if ($request->boolean('remote')) {
            $remote = $this->max->getSubscriptions();
        }

        return response()->json([
            'data' => MaxSubscription::query()
                ->latest('id')
                ->get()
                ->map(fn (MaxSubscription $subscription) => $this->payload($subscription)),
            'remote' => $remote,
            'webhook_url' => url('/api/max/webhook'),
            'default_update_types' => config('services.max.webhook_update_types', []),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'url' => ['nullable', 'url', 'max:512'],
            'secret' => ['nullable', 'string', 'max:255'],
            'update_types' => ['nullable', 'array'],
            'update_types.*' => ['string', 'max:80'],
        ]);

        $url = $data['url'] ?? url('/api/max/webhook');
        $secret = $data['secret'] ?? config('services.max.webhook_secret');
        $updateTypes = $data['update_types'] ?? config('services.max.webhook_update_types', []);

        $result = $this->max->createSubscription($url, $updateTypes, $secret);

        $subscription = MaxSubscription::query()->updateOrCreate([
            'url' => $url,
        ], [
            'secret' => $secret,
            'update_types' => $updateTypes,
            'is_active' => $result['ok'],
            'provider_response' => $result,
            'subscribed_at' => $result['ok'] ? now() : null,
            'unsubscribed_at' => null,
        ]);

        if (! $result['ok']) {
            return response()->json([
                'message' => $result['error'] ?: 'MAX не создал webhook-подписку.',
                'data' => $this->payload($subscription),
                'provider' => $result,
            ], 502);
        }

        return response()->json([
            'data' => $this->payload($subscription),
            'provider' => $result,
        ], 201);
    }

    public function destroy(Request $request, MaxSubscription $maxSubscription): JsonResponse
    {
        $result = null;

        if ($request->boolean('unsubscribe')) {
            $result = $this->max->deleteSubscription($maxSubscription->url);

            if (! $result['ok']) {
                return response()->json([
                    'message' => $result['error'] ?: 'MAX не удалил webhook-подписку.',
                    'provider' => $result,
                ], 502);
            }

            $maxSubscription->update([
                'is_active' => false,
                'provider_response' => $result,
                'unsubscribed_at' => now(),
            ]);
        }

        $maxSubscription->delete();

        return response()->json([
            'message' => 'MAX webhook-подписка удалена.',
            'provider' => $result,
        ]);
    }

    private function payload(MaxSubscription $subscription): array
    {
        return [
            'id' => $subscription->id,
            'url' => $subscription->url,
            'secret_configured' => filled($subscription->secret),
            'update_types' => $subscription->update_types ?: [],
            'is_active' => (bool) $subscription->is_active,
            'provider_response' => $subscription->provider_response,
            'subscribed_at' => $subscription->subscribed_at?->toISOString(),
            'unsubscribed_at' => $subscription->unsubscribed_at?->toISOString(),
            'created_at' => $subscription->created_at?->toISOString(),
            'updated_at' => $subscription->updated_at?->toISOString(),
        ];
    }
}
