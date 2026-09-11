<?php

namespace App\Console\Commands;

use App\Models\GoodStockMovement;
use App\Models\Sale;
use App\Models\Warehouse;
use App\Services\Goods\GoodSaleStockSynchronizer;
use App\Services\Goods\GoodStockService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReconcileSaleGoodsStockCommand extends Command
{
    protected $signature = 'goods-stock:reconcile-sales
        {--apply : Учесть сохранённые продажи, в том числе исторический дефицит}
        {--sale=* : Ограничить сверку указанными ID Sale}';

    protected $description = 'Сверка продаж и склада goods; без --apply данные не изменяются';

    public function handle(GoodSaleStockSynchronizer $synchronizer, GoodStockService $stock): int
    {
        $saleIds = $this->option('sale');
        foreach ($saleIds as $id) {
            if (! ctype_digit((string) $id) || (int) $id < 1) {
                $this->error('Параметр --sale должен содержать положительный ID.');

                return self::INVALID;
            }
        }

        $warehouseId = Warehouse::query()->where('code', Warehouse::GOODS_CODE)
            ->where('is_active', true)->value('id');
        if (! $warehouseId) {
            $this->error('Активный системный склад goods не найден.');

            return self::FAILURE;
        }

        $sales = Sale::query()->without('entity')
            ->when($saleIds !== [], fn ($query) => $query->whereIn('id', $saleIds));
        if ($saleIds !== [] && (clone $sales)->count() !== count(array_unique($saleIds))) {
            $this->error('Некоторые указанные Sale не найдены.');

            return self::INVALID;
        }

        $projected = [];
        foreach (DB::table('good_stock_movements')->select(['warehouse_id', 'good_id', 'measure_id'])
            ->selectRaw('SUM(quantity_delta) as quantity')
            ->groupBy('warehouse_id', 'good_id', 'measure_id')->get() as $row) {
            $projected[$this->key($row->warehouse_id, $row->good_id, $row->measure_id)] = (float) $row->quantity;
        }

        $missing = 0;
        $changed = 0;
        $invalid = [];
        $affectedGoods = [];
        foreach ((clone $sales)->orderBy('id')->lazyById(200) as $sale) {
            $items = DB::table('good_sale')->where('sale_id', $sale->id)->get()->keyBy('id');
            $movements = DB::table('good_stock_movements')
                ->where('source_type', GoodStockMovement::SOURCE_GOOD_SALE)
                ->where('sale_id', $sale->id)->get()->keyBy('source_id');

            foreach ($movements as $movement) {
                $key = $this->key($movement->warehouse_id, $movement->good_id, $movement->measure_id);
                $projected[$key] = ($projected[$key] ?? 0) - $movement->quantity_delta;
                $affectedGoods[(int) $movement->good_id] = true;
                if (! $items->has($movement->source_id)) {
                    $changed++;
                }
            }

            foreach ($items as $item) {
                if (! is_finite((float) $item->quantity) || $item->quantity < 0.000001 || ! $item->measure_id) {
                    $invalid[] = "Sale #{$sale->id}, позиция #{$item->id}: некорректное количество или единица измерения";

                    continue;
                }

                $affectedGoods[(int) $item->good_id] = true;
                $key = $this->key($warehouseId, $item->good_id, $item->measure_id);
                $projected[$key] = ($projected[$key] ?? 0) - round((float) $item->quantity, 6);
                $movement = $movements->get($item->id);
                if (! $movement) {
                    $missing++;
                } elseif ((int) $movement->warehouse_id !== (int) $warehouseId
                    || (int) $movement->good_id !== (int) $item->good_id
                    || (int) $movement->measure_id !== (int) $item->measure_id
                    || abs($movement->quantity_delta + round((float) $item->quantity, 6)) > 0.0000001
                    || $movement->type !== GoodStockMovement::TYPE_WRITE_OFF
                    || $movement->moved_at !== $sale->date->toDateString()) {
                    $changed++;
                }
            }
        }

        $this->line('Режим: '.($this->option('apply') ? 'apply' : 'dry-run'));
        $this->line("Отсутствующих списаний: {$missing}; расхождений: {$changed}; некорректных позиций: ".count($invalid));
        foreach (array_slice($invalid, 0, 50) as $error) {
            $this->error($error);
        }

        $manualWriteOffs = DB::table('good_stock_movements')->where('warehouse_id', $warehouseId)
            ->whereIn('good_id', array_keys($affectedGoods))->whereNull('source_type')
            ->where('quantity_delta', '<', 0)->count();
        if ($manualWriteOffs > 0) {
            $this->warn("Ручных расходных движений затронутых товаров: {$manualWriteOffs}. Проверьте, не списаны ли эти продажи вручную; автоматического сопоставления нет.");
        }

        $shortages = [];
        foreach ($projected as $key => $quantity) {
            [$warehouse, $good, $measure] = explode(':', $key);
            if (isset($affectedGoods[(int) $good]) && $quantity < -0.000000001) {
                $shortages[] = [$warehouse, $good, $measure, round($quantity, 6)];
            }
        }
        $this->line('Позиций с дефицитом после сверки: '.count($shortages));
        if ($shortages !== []) {
            $this->table(['Склад ID', 'Good ID', 'Единица ID', 'Остаток'], array_slice($shortages, 0, 50));
        }

        if ($invalid !== []) {
            $this->error('Исправьте некорректные исторические позиции и повторите сверку. Изменения не применены.');

            return self::FAILURE;
        }
        if (! $this->option('apply')) {
            $this->info('Данные не изменены. Для записи движений используйте --apply; исторический дефицит сохраняется в отчёте склада.');

            return self::SUCCESS;
        }

        $processed = 0;
        foreach ((clone $sales)->orderBy('id')->lazyById(200) as $sale) {
            try {
                $synchronizer->sync($sale, allowNegativeStock: true);
                $processed++;
            } catch (ValidationException $exception) {
                $this->error("Sale #{$sale->id}: ".$exception->getMessage());
                $this->error("Уже обработано продаж: {$processed}. После исправления можно безопасно повторить команду.");

                return self::FAILURE;
            }
        }
        foreach (array_keys($affectedGoods) as $goodId) {
            $stock->syncAvailability($goodId);
        }

        $this->info("Обработано продаж: {$processed}. Повторный запуск не создаёт повторных списаний.");

        return self::SUCCESS;
    }

    private function key(int $warehouseId, int $goodId, ?int $measureId): string
    {
        return $warehouseId.':'.$goodId.':'.($measureId ?? 'null');
    }
}
