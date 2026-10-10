<?php

namespace App\Services\Catalog;

use App\Models\CatalogLanding;
use App\Models\CatalogNode;
use App\Models\Good;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CatalogLandingService
{
    public function __construct(
        private readonly CatalogService $catalog,
        private readonly CatalogLandingTemplates $templates,
    ) {}

    public function editor(CatalogNode $node): array
    {
        $nodes = $this->catalog->nodes()->keyBy('id');
        $payload = $nodes->get($node->id);
        $current = $payload;
        $seen = [];
        $reason = null;
        while ($current) {
            if (isset($seen[$current['id']])) {
                $reason = 'cycle';
                break;
            }
            $seen[$current['id']] = true;
            if (! $current['is_published']) {
                $reason = $current['id'] === $node->id ? 'hidden_node' : 'hidden_ancestor';
                break;
            }
            if (! $current['parent_id']) {
                break;
            }
            $current = $nodes->get($current['parent_id']);
            if (! $current) {
                $reason = 'missing_parent';
            }
        }

        $common = [
            'node_id' => $node->id, 'node_name' => $payload['name'] ?? $node->name,
            'public_url' => $payload['public_url'] ?? null,
            'effective_visible' => $payload !== null && $reason === null,
            'visibility_reason' => $reason,
        ];
        if ($node->entity_type === 'good') {
            return [
                ...$common, 'mode' => 'good', 'good_id' => $node->entity_id,
                // Every placement points to the same /g/ page. Its availability
                // is controlled by Good itself, independently of branch visibility.
                'effective_visible' => (bool) ($payload['is_published'] ?? false),
                'visibility_reason' => ($payload['is_published'] ?? false) ? null : 'hidden_node',
                'exists' => true, 'version' => 0, 'draft_content' => null, 'published_content' => null,
                'has_changes' => false, 'published_at' => null, 'preview_url' => null, 'templates' => [],
            ];
        }

        $landing = CatalogLanding::where('catalog_node_id', $node->id)->first();
        $legacy = $landing === null ? $this->templates->legacyForNode($node) : null;
        if ($legacy) {
            $landing = new CatalogLanding([
                'catalog_node_id' => $node->id, 'version' => 0,
                'draft_content' => $legacy['content'], 'published_content' => $legacy['content'],
            ]);
        }
        $selection = $landing?->draft_content['catalog'] ?? [];

        return [
            ...$common, 'mode' => 'catalog', 'exists' => $landing !== null,
            'legacy' => $legacy !== null,
            'version' => $landing?->version ?? 0,
            'draft_content' => $landing?->draft_content,
            'published_content' => $landing?->published_content,
            'has_changes' => $landing !== null && $landing->draft_content !== $landing->published_content,
            'published_at' => $landing?->published_at?->toIso8601String(),
            'activated_at' => $landing?->activated_at?->toIso8601String(),
            'preview_url' => url('/Ameise/catalog/nodes/'.$node->id.'/landing/preview'),
            'templates' => $this->templates->forNode($node, $payload),
            'source_products' => Product::without(['category', 'manufacturers'])
                ->whereIn('id', $selection['source_product_ids'] ?? [])->get(['id', 'rus'])
                ->map(fn (Product $product): array => ['id' => $product->id, 'name' => $product->rus])->all(),
            'selected_goods' => Good::whereIn('id', array_unique([
                ...($selection['good_ids'] ?? []), ...($selection['inline_good_ids'] ?? []),
            ]))->get(['id', 'name'])->map(fn (Good $good): array => ['id' => $good->id, 'name' => $good->name])->all(),
        ];
    }

    public function save(CatalogNode $node, int $version, array $content, ?int $userId): CatalogLanding
    {
        return $this->mutate($node, $version, function (CatalogLanding $landing) use ($content, $userId): void {
            $landing->draft_content = $content;
            $landing->updated_by = $userId;
        });
    }

    public function publish(CatalogNode $node, int $version, ?int $userId): CatalogLanding
    {
        return $this->mutate($node, $version, function (CatalogLanding $landing) use ($userId): void {
            if (! $landing->draft_content) {
                throw ValidationException::withMessages(['content' => 'Сначала сохраните черновик лендинга.']);
            }
            // Validate the stored snapshot too, so obsolete or malformed database
            // content cannot become public merely by posting a version number.
            $content = app(CatalogLandingContent::class)->validate($landing->draft_content);
            $landing->published_content = $content;
            $landing->published_at = now();
            $landing->activated_at ??= now();
            $landing->published_by = $userId;
            $landing->updated_by = $userId;
        });
    }

    public function unpublish(CatalogNode $node, int $version, ?int $userId): CatalogLanding
    {
        return $this->mutate($node, $version, function (CatalogLanding $landing) use ($userId): void {
            if (! $landing->exists && ! $landing->published_content) {
                throw ValidationException::withMessages(['content' => 'Лендинг ещё не создан.']);
            }
            // Retain the row and draft. A null published snapshot explicitly
            // disables the landing and must suppress the legacy config fallback.
            $landing->published_content = null;
            $landing->published_at = null;
            $landing->published_by = null;
            $landing->updated_by = $userId;
        });
    }

    public function ensureCatalogNode(CatalogNode $node): void
    {
        if ($node->entity_type === 'good') {
            throw ValidationException::withMessages(['node' => 'Страница товара общая для всех его размещений. Используйте настройки товара.']);
        }
    }

    private function mutate(CatalogNode $node, int $version, callable $mutation): CatalogLanding
    {
        return DB::transaction(function () use ($node, $version, $mutation): CatalogLanding {
            // Lock the owner as well: the first save has no landing row to lock.
            $node = CatalogNode::lockForUpdate()->findOrFail($node->id);
            $this->ensureCatalogNode($node);
            $landing = CatalogLanding::where('catalog_node_id', $node->id)->lockForUpdate()->first()
                ?? new CatalogLanding(['catalog_node_id' => $node->id, 'version' => 0]);
            abort_if($landing->version !== $version, 409, 'Лендинг изменён другим сотрудником. Загрузите актуальную версию перед сохранением.');
            if (! $landing->exists && ($legacy = $this->templates->legacyForNode($node))) {
                // First draft creation must preserve the currently public legacy
                // page; otherwise merely editing would silently disable it.
                $landing->draft_content = $legacy['content'];
                $landing->published_content = $legacy['content'];
                $landing->published_at = now();
                $landing->activated_at = now();
                if ($legacy['seo'] !== []) {
                    $node->update($legacy['seo']);
                }
            }
            $mutation($landing);
            $landing->version++;
            $landing->save();

            return $landing;
        }, 3);
    }
}
