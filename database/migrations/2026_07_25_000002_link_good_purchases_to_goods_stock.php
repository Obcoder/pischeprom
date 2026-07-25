<?php

use App\Models\GoodStockMovement;
use App\Models\Warehouse;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('good_stock_movements', function (Blueprint $table): void {
            $table->string('source_type')->nullable()->after('moved_at');
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            $table->foreignId('purchase_id')
                ->nullable()
                ->after('source_id')
                ->constrained('purchases')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->index(
                ['source_type', 'source_id'],
                'good_stock_movements_source_index'
            );
            $table->unique(
                ['source_type', 'source_id'],
                'good_stock_movements_source_unique'
            );
            $table->index(
                ['source_type', 'purchase_id'],
                'good_stock_movements_purchase_source_index'
            );
        });

        $warehouseId = DB::table('warehouses')
            ->where('code', Warehouse::GOODS_CODE)
            ->value('id');

        if (! $warehouseId) {
            return;
        }

        $affectedGoodIds = [];

        DB::table('good_purchase as gp')
            ->join('purchases as p', 'p.id', '=', 'gp.purchase_id')
            ->select([
                'gp.id as pivot_id',
                'gp.created_at',
                'gp.updated_at',
                'gp.good_id',
                'gp.purchase_id',
                'gp.measure_id',
                'gp.quantity',
                'gp.price',
                'p.date as purchase_date',
            ])
            ->orderBy('gp.id')
            ->chunkById(
                500,
                function (Collection $items) use ($warehouseId, &$affectedGoodIds): void {
                    $movements = $items->map(function ($item) use ($warehouseId, &$affectedGoodIds): array {
                        $affectedGoodIds[] = (int) $item->good_id;

                        return [
                            'created_at' => $item->created_at ?: now(),
                            'updated_at' => $item->updated_at ?: now(),
                            'warehouse_id' => $warehouseId,
                            'good_id' => $item->good_id,
                            'measure_id' => $item->measure_id,
                            'type' => GoodStockMovement::TYPE_RECEIPT,
                            'quantity_delta' => abs((float) $item->quantity),
                            'unit_price' => (float) $item->price,
                            'moved_at' => $this->movementDate(
                                $item->purchase_date,
                                $item->created_at
                            ),
                            'source_type' => GoodStockMovement::SOURCE_GOOD_PURCHASE,
                            'source_id' => $item->pivot_id,
                            'purchase_id' => $item->purchase_id,
                            'note' => "Purchase #{$item->purchase_id}",
                        ];
                    })->all();

                    if ($movements !== []) {
                        DB::table('good_stock_movements')->insert($movements);
                    }
                },
                'gp.id',
                'pivot_id'
            );

        $this->syncAvailability($affectedGoodIds);
    }

    public function down(): void
    {
        $affectedGoodIds = DB::table('good_stock_movements')
            ->where('source_type', GoodStockMovement::SOURCE_GOOD_PURCHASE)
            ->pluck('good_id')
            ->all();

        DB::table('good_stock_movements')
            ->where('source_type', GoodStockMovement::SOURCE_GOOD_PURCHASE)
            ->delete();

        $this->syncAvailability($affectedGoodIds);

        Schema::table('good_stock_movements', function (Blueprint $table): void {
            $table->dropForeign(['purchase_id']);
            $table->dropUnique('good_stock_movements_source_unique');
            $table->dropIndex('good_stock_movements_source_index');
            $table->dropIndex('good_stock_movements_purchase_source_index');
            $table->dropColumn([
                'source_type',
                'source_id',
                'purchase_id',
            ]);
        });
    }

    private function syncAvailability(array $goodIds): void
    {
        if (
            ! Schema::hasTable('good_stock_availabilities')
            || ! Schema::hasTable('good_seos')
            || ! Schema::hasColumn('good_seos', 'availability_status')
        ) {
            return;
        }

        collect($goodIds)
            ->map(fn ($goodId) => (int) $goodId)
            ->filter()
            ->unique()
            ->each(function (int $goodId): void {
                $isInStock = DB::table('good_stock_movements')
                    ->where('good_id', $goodId)
                    ->selectRaw('SUM(quantity_delta) as quantity')
                    ->groupBy('warehouse_id', 'measure_id')
                    ->pluck('quantity')
                    ->contains(fn ($quantity) => (float) $quantity > 0.000001);

                $now = now();
                $availability = DB::table('good_stock_availabilities')
                    ->where('good_id', $goodId)
                    ->first();

                if ($availability) {
                    DB::table('good_stock_availabilities')
                        ->where('good_id', $goodId)
                        ->update([
                            'is_in_stock' => $isInStock,
                            'became_available_at' => $isInStock
                                ? ($availability->is_in_stock
                                    ? $availability->became_available_at
                                    : $now)
                                : null,
                            'checked_at' => $now,
                            'updated_at' => $now,
                        ]);
                } else {
                    DB::table('good_stock_availabilities')->insert([
                        'good_id' => $goodId,
                        'is_in_stock' => $isInStock,
                        'became_available_at' => $isInStock ? $now : null,
                        'checked_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                $goodSeo = DB::table('good_seos')
                    ->where('good_id', $goodId)
                    ->first();

                if ($goodSeo) {
                    DB::table('good_seos')
                        ->where('good_id', $goodId)
                        ->update([
                            'availability_status' => $isInStock
                                ? 'in_stock'
                                : 'out_of_stock',
                            'updated_at' => $now,
                        ]);
                } else {
                    DB::table('good_seos')->insert([
                        'good_id' => $goodId,
                        'availability_status' => $isInStock
                            ? 'in_stock'
                            : 'out_of_stock',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            });
    }

    private function movementDate(mixed $purchaseDate, mixed $createdAt): string
    {
        $purchaseDate = (string) $purchaseDate;

        if (
            $purchaseDate !== '0000-00-00'
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $purchaseDate) === 1
        ) {
            return $purchaseDate;
        }

        try {
            return \Carbon\Carbon::parse($createdAt)->toDateString();
        } catch (Throwable) {
            return now()->toDateString();
        }
    }
};
