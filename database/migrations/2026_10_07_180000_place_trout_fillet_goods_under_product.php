<?php

use App\Models\CatalogLevel;
use App\Models\CatalogNode;
use App\Models\Good;
use App\Models\Product;
use App\Services\Catalog\CatalogService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            // Use the same structural lock order as catalog editing and importing.
            CatalogLevel::orderBy('id')->lockForUpdate()->get();
            CatalogNode::orderBy('id')->lockForUpdate()->get(['id']);
            $products = Product::without(['category', 'manufacturers'])->whereIn('id', [99, 186])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if (($products[99]->rus ?? null) !== 'Форель' || ($products[186]->rus ?? null) !== 'Форель филе'
                || $products[99]->category_id !== $products[186]->category_id) {
                return;
            }
            $class = CatalogNode::where('import_key', 'product:99')->where('entity_type', 'product')->where('entity_id', 99)->first();
            $fillet = CatalogNode::where('import_key', 'product:186')->where('entity_type', 'product')->where('entity_id', 186)->first();
            if (! $class || ! $fillet || $fillet->parent_id !== $class->id) {
                return;
            }

            $names = [
                99 => 'Форель филе-кусок б/к и/з вакуум 12/12',
                148 => 'Форель филе-кубики б/к зам. 1/12',
            ];
            foreach ($names as $goodId => $name) {
                $good = Good::whereKey($goodId)->lockForUpdate()->first();
                if (! $good || $good->name !== $name || ! $good->products()->whereKey(99)->exists()
                    || $good->products()->whereKey(186)->exists()) {
                    continue;
                }
                $node = CatalogNode::where('import_key', 'good:'.$goodId.':product:99')
                    ->where('entity_type', 'good')->where('entity_id', $goodId)->where('parent_id', $class->id)->first();
                if (! $node || $node->children()->exists() || $node->level?->is_domain) {
                    continue;
                }
                $duplicate = CatalogNode::where('entity_type', 'good')->where('entity_id', $goodId)
                    ->where(function ($query) use ($goodId, $fillet): void {
                        $query->where('import_key', 'good:'.$goodId.':product:186')->orWhere('parent_id', $fillet->id);
                    })->exists();
                if ($duplicate) {
                    continue;
                }

                $manual = $node->is_manual;
                $moved = app(CatalogService::class)->saveNode(['parent_id' => $fillet->id], $node);
                if ($moved->is_manual !== $manual) {
                    $moved->update(['is_manual' => $manual]);
                }
            }
        }, 3);
    }

    public function down(): void
    {
        // A data correction must not undo classification changes made afterwards.
    }
};
