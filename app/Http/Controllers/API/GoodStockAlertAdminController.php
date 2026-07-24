<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\GoodStockAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoodStockAlertAdminController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit = min(max((int) $request->integer('limit', 100), 1), 500);

        $alerts = GoodStockAlert::query()
            ->with([
                'good:id,name,slug',
                'maxChat:id,chat_id,user_id,contact_name,title',
            ])
            ->when(
                $request->filled('status'),
                fn ($query) => $query->where('status', $request->input('status'))
            )
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (GoodStockAlert $alert) => [
                'id' => $alert->id,
                'good_id' => $alert->good_id,
                'max_chat_id' => $alert->max_chat_id,
                'status' => $alert->status,
                'attempts' => $alert->attempts,
                'error_message' => $alert->error_message,
                'expires_at' => $alert->expires_at?->toISOString(),
                'activated_at' => $alert->activated_at?->toISOString(),
                'confirmation_sent_at' => $alert->confirmation_sent_at?->toISOString(),
                'notified_at' => $alert->notified_at?->toISOString(),
                'cancelled_at' => $alert->cancelled_at?->toISOString(),
                'created_at' => $alert->created_at?->toISOString(),
                'good' => $alert->good ? [
                    'id' => $alert->good->id,
                    'name' => $alert->good->name,
                    'slug' => $alert->good->slug,
                ] : null,
                'max_chat' => $alert->maxChat ? [
                    'id' => $alert->maxChat->id,
                    'chat_id' => $alert->maxChat->chat_id,
                    'user_id' => $alert->maxChat->user_id,
                    'title' => $alert->maxChat->contact_name ?: $alert->maxChat->title,
                ] : null,
            ]);

        return response()->json($alerts->values());
    }

    public function destroy(GoodStockAlert $goodStockAlert): JsonResponse
    {
        if (in_array($goodStockAlert->status, [
            GoodStockAlert::STATUS_PENDING,
            GoodStockAlert::STATUS_ACTIVE,
            GoodStockAlert::STATUS_FAILED,
        ], true)) {
            $goodStockAlert->update([
                'status' => GoodStockAlert::STATUS_CANCELLED,
                'cancelled_at' => now(),
            ]);
        }

        return response()->json([
            'message' => 'Оповещение отменено.',
        ]);
    }
}
