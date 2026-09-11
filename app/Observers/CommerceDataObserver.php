<?php

namespace App\Observers;

use App\Events\CommerceDataChanged;
use App\Models\GoodStockMovement;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Throwable;

class CommerceDataObserver
{
    public function saved(Model $model): void
    {
        // During saved, originals have not been synchronized yet. wasChanged()
        // and wasRecentlyCreated may still describe an earlier save of this instance.
        if ($model->isDirty()) {
            $this->publishAfterCommit($model);
        }
    }

    public function deleted(Model $model): void
    {
        $this->publishAfterCommit($model);
    }

    private function publishAfterCommit(Model $model): void
    {
        if (! config('realtime.enabled')) {
            return;
        }

        $topics = match (true) {
            $model instanceof Sale => ['sales'],
            $model instanceof Purchase => ['purchases'],
            $model instanceof GoodStockMovement => $this->goodsMovementTopics($model),
            $model instanceof Warehouse => ['warehouses'],
            $model instanceof StockMovement => ['commodity_stock'],
        };

        $model->getConnection()->afterCommit(function () use ($topics): void {
            try {
                $connection = (string) config('realtime.queue_connection', 'database');

                // A sync queue would perform network I/O during sale posting.
                if (config("queue.connections.{$connection}.driver") !== 'database') {
                    CommerceDataChanged::warnUnavailable();

                    return;
                }

                Event::dispatch(new CommerceDataChanged($topics));
            } catch (Throwable) {
                CommerceDataChanged::warnUnavailable();
            }
        });
    }

    private function goodsMovementTopics(GoodStockMovement $movement): array
    {
        $topics = ['goods_stock'];

        // A zero-price line can change a document without changing its total or
        // its second-resolution timestamp, so its stock movement also signals it.
        if ($movement->sale_id !== null || $movement->source_type === GoodStockMovement::SOURCE_GOOD_SALE) {
            $topics[] = 'sales';
        }

        if ($movement->purchase_id !== null || $movement->source_type === GoodStockMovement::SOURCE_GOOD_PURCHASE) {
            $topics[] = 'purchases';
        }

        return $topics;
    }
}
