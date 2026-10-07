<?php

use App\Models\CatalogLevel;
use App\Models\CatalogNode;
use App\Models\Good;
use App\Models\GoodSeo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            CatalogLevel::orderBy('id')->lockForUpdate()->get(['id']);
            $nodes = CatalogNode::where('entity_type', 'good')->orderBy('id')->lockForUpdate()->get();
            foreach ($nodes->groupBy('entity_id') as $goodId => $placements) {
                if (! Good::whereKey($goodId)->lockForUpdate()->exists()) {
                    continue;
                }
                $seo = GoodSeo::where('good_id', $goodId)->lockForUpdate()->first() ?? new GoodSeo(['good_id' => $goodId]);
                foreach (['meta_title', 'meta_description'] as $field) {
                    if (filled($seo->{$field})) {
                        continue;
                    }
                    $legacy = $placements->first(fn (CatalogNode $node): bool => filled($node->{$field}));
                    if ($legacy) {
                        $seo->{$field} = $legacy->{$field};
                    }
                }
                if ($seo->isDirty(['meta_title', 'meta_description'])) {
                    $seo->save();
                }
                CatalogNode::whereIn('id', $placements->pluck('id'))
                    ->where(fn ($query) => $query->whereNotNull('meta_title')->orWhereNotNull('meta_description'))
                    ->update(['meta_title' => null, 'meta_description' => null]);
            }
        }, 3);
    }

    public function down(): void
    {
        // Do not recreate competing copies or undo later canonical SEO edits.
    }
};
