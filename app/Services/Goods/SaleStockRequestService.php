<?php

namespace App\Services\Goods;

use App\Models\Sale;
use Illuminate\Support\Facades\DB;

class SaleStockRequestService
{
    /**
     * Claim the request and save its result in the same transaction as the stock change.
     * The request row is removed by rollback if posting fails, allowing a corrected retry.
     */
    public function run(?string $requestId, string $action, array $payload, callable $callback): Sale
    {
        $requestId = $requestId !== null ? strtolower($requestId) : null;

        return DB::transaction(function () use ($requestId, $action, $payload, $callback): Sale {
            if ($requestId === null) {
                return $callback();
            }

            $payloadHash = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
            $now = now();
            DB::table('sale_stock_requests')->insertOrIgnore([
                'request_id' => $requestId,
                'action' => $action,
                'payload_hash' => $payloadHash,
                'sale_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $request = DB::table('sale_stock_requests')->where('request_id', $requestId)
                ->lockForUpdate()->first();

            abort_unless(
                $request && $request->action === $action && hash_equals($request->payload_hash, $payloadHash),
                409,
                'Этот запрос уже использован с другими данными. Обновите продажу перед новой операцией.',
            );

            if ($request->sale_id !== null) {
                return Sale::query()->findOrFail($request->sale_id);
            }

            $sale = $callback();
            DB::table('sale_stock_requests')->where('request_id', $requestId)->update([
                'sale_id' => $sale->id,
                'updated_at' => now(),
            ]);

            return $sale;
        }, 3);
    }
}
