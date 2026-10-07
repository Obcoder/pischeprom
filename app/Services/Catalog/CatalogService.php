<?php

namespace App\Services\Catalog;

use App\Models\CatalogLevel;
use App\Models\CatalogNode;
use App\Models\Category;
use App\Models\Good;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CatalogService
{
    public function snapshot(): array
    {
        $this->importMissing();
        $nodes = $this->nodes();

        return [
            'levels' => CatalogLevel::with('fields')->orderBy('sort_order')->orderBy('id')->get(),
            'nodes' => $nodes,
            'counts' => ['nodes' => $nodes->count(), 'published' => $nodes->where('is_published', true)->count()],
        ];
    }

    /** Read-only, with one source query per entity type rather than per node. */
    public function nodes(): Collection
    {
        $nodes = CatalogNode::orderBy('sort_order')->orderBy('name')->orderBy('id')->get();
        $sources = [
            'category' => Category::whereIn('id', $nodes->where('entity_type', 'category')->pluck('entity_id'))->get()->keyBy('id'),
            'product' => Product::without(['category', 'manufacturers'])->whereIn('id', $nodes->where('entity_type', 'product')->pluck('entity_id'))->get()->keyBy('id'),
            'good' => Good::with('seo')->whereIn('id', $nodes->where('entity_type', 'good')->pluck('entity_id'))->get()->keyBy('id'),
        ];

        return $nodes->map(fn (CatalogNode $node): array => $this->nodePayload($node, $sources[$node->entity_type][$node->entity_id] ?? null, true));
    }

    public function nodePayload(CatalogNode $node, ?Model $source = null, bool $resolved = false): array
    {
        $data = $node->only([
            'id', 'level_id', 'parent_id', 'entity_type', 'entity_id', 'name', 'slug', 'image',
            'description', 'meta_title', 'meta_description', 'is_published', 'is_featured', 'sort_order', 'properties',
        ]);
        $data['properties'] = $data['properties'] ?: (object) [];
        $source = $resolved ? $source : $this->source($node);
        if ($node->entity_type && ! $source) {
            $data['is_published'] = false;
        }
        if ($source) {
            $data['name'] = $source instanceof Product ? $source->rus : $source->name;
            $data['is_published'] = (bool) $source->is_published;
            if ($source instanceof Category) {
                foreach (['slug', 'image', 'description', 'meta_title', 'meta_description', 'is_featured'] as $key) {
                    $data[$key] = $source->{$key};
                }
            } elseif ($source instanceof Good) {
                $data['slug'] = $source->slug;
                $data['image'] = $source->ava_image ?: $source->ava_thumb;
                $data['description'] = $source->description;
                $data['meta_title'] = $source->seo?->meta_title;
                $data['meta_description'] = $source->seo?->meta_description;
            }
        }
        $data['slug'] = $data['slug'] ?: (Str::slug($data['name']) ?: 'catalog');
        $data['public_url'] = Route::has('public.catalog.show')
            ? route('public.catalog.show', ['node' => $node->id, 'slug' => $data['slug'] ?: Str::slug($data['name'])])
            : url('/catalog/'.$node->id.'/'.($data['slug'] ?: Str::slug($data['name'])));
        $data['offer_url'] = $source instanceof Good
            ? route('public.goods.show', ['good' => $source->slug ?: $source->id])
            : null;
        $data['edit_url'] = match ($node->entity_type) {
            'product' => route('product.show', ['product' => $node->entity_id]),
            'good' => route('Ameise.good.show', ['id' => $node->entity_id]),
            default => null,
        };

        return $data;
    }

    /** Reconcile legacy entries created elsewhere without flattening manually arranged branches. */
    public function importMissing(): void
    {
        DB::transaction(function (): void {
            $levels = CatalogLevel::whereIn('entity_type', ['category', 'product', 'good'])->orderBy('id')->lockForUpdate()->get()->keyBy('entity_type');
            if ($levels->count() !== 3) {
                return;
            }
            $categories = Category::orderBy('id')->get()->keyBy('id');
            $products = Product::without(['category', 'manufacturers'])->orderBy('id')->get()->keyBy('id');
            $goods = Good::orderBy('id')->get()->keyBy('id');
            $sources = ['category' => $categories, 'product' => $products, 'good' => $goods];
            foreach (CatalogNode::whereNotNull('entity_type')->get() as $node) {
                if (! isset($sources[$node->entity_type][$node->entity_id])) {
                    if ($fresh = $node->fresh()) {
                        $this->removePlacement($fresh, $fresh->parent_id);
                    }
                }
            }
            $allNodes = CatalogNode::get()->keyBy('id');
            $nodes = $allNodes->whereNotNull('import_key')->keyBy('import_key');
            foreach ($categories as $source) {
                $key = 'category:'.$source->id;
                $nodes[$key] ??= $this->importNode($levels['category'], $source, null, $key);
                $allNodes[$nodes[$key]->id] = $nodes[$key];
            }
            foreach ($products as $source) {
                $key = 'product:'.$source->id;
                $parentId = $nodes['category:'.$source->category_id]->id ?? null;
                $nodes[$key] ??= $this->importNode($levels['product'], $source, $parentId, $key);
                $allNodes[$nodes[$key]->id] = $nodes[$key];
                if ($this->ancestorFromMap($nodes[$key], 'category', $allNodes)?->entity_id !== $source->category_id) {
                    $this->placeImported($nodes[$key], $parentId);
                }
            }
            $links = DB::table('good_product')->get()->groupBy('good_id');
            $goodPlacements = $allNodes->where('entity_type', 'good')->groupBy('entity_id');
            foreach ($goods as $source) {
                $parents = ($links[$source->id] ?? collect())->pluck('product_id')->unique()->filter(fn ($id) => isset($products[$id]));
                $expected = $parents->isEmpty()
                    ? ['good:'.$source->id.':root' => null]
                    : $parents->mapWithKeys(fn ($id) => ['good:'.$source->id.':product:'.$id => $nodes['product:'.$id]->id])->all();
                $placements = ($goodPlacements[$source->id] ?? collect())->keyBy('import_key');
                $manualRoot = $placements['good:'.$source->id.':root'] ?? null;
                if ($manualRoot?->is_manual) {
                    $expected[$manualRoot->import_key] = $manualRoot->parent_id;
                }
                $obsolete = $placements->reject(fn ($node, $key) => array_key_exists($key, $expected))->values();
                $retained = null;
                foreach ($expected as $key => $parentId) {
                    $placement = $placements[$key] ?? $obsolete->shift();
                    if (! $placement) {
                        $placement = $this->importNode($levels['good'], $source, $parentId, $key);
                    } elseif ($placement->import_key !== $key) {
                        $this->placeImported($placement, $parentId);
                        $placement->update(['import_key' => $key]);
                    } else {
                        $expectedProductId = $parentId && ! str_ends_with($key, ':root') ? ($allNodes[$parentId]->entity_id ?? null) : null;
                        if ($this->ancestorFromMap($placement, 'product', $allNodes)?->entity_id !== $expectedProductId) {
                            $this->placeImported($placement, $parentId);
                        }
                    }
                    $allNodes[$placement->id] = $placement;
                    $retained ??= $placement;
                }
                foreach ($obsolete as $placement) {
                    $this->removePlacement($placement, $retained?->id ?? $placement->parent_id);
                }
            }
        }, 3);
    }

    private function ancestorFromMap(CatalogNode $node, string $type, Collection $nodes): ?CatalogNode
    {
        $seen = [$node->id => true];
        $parentId = $node->parent_id;
        while ($parentId && ! isset($seen[$parentId])) {
            $seen[$parentId] = true;
            $parent = $nodes[$parentId] ?? null;
            if (! $parent) {
                return null;
            }
            if ($parent->entity_type === $type) {
                return $parent;
            }
            $parentId = $parent->parent_id;
        }

        return null;
    }

    private function placeImported(CatalogNode $node, ?int $parentId): void
    {
        try {
            $this->validateParent($node, $parentId);
        } catch (ValidationException) {
            // An external legacy reassignment must never introduce a cycle.
            $parentId = null;
        }
        $node->update(['parent_id' => $parentId]);
    }

    private function removePlacement(CatalogNode $node, ?int $replacementParentId): void
    {
        foreach ($node->children()->get() as $child) {
            $this->placeImported($child, $replacementParentId);
        }
        $node->delete();
    }

    private function importNode(CatalogLevel $level, Model $source, ?int $parentId, string $key): CatalogNode
    {
        return CatalogNode::firstOrCreate(['import_key' => $key], [
            'level_id' => $level->id, 'parent_id' => $parentId, 'entity_type' => $level->entity_type,
            'entity_id' => $source->id, 'name' => $source instanceof Product ? $source->rus : $source->name,
            'slug' => $source->slug ?? Str::slug($source->rus ?? $source->name),
            'is_published' => $source->is_published, 'is_featured' => $source->is_featured ?? false,
            'sort_order' => $source->sort_order ?? 0, 'properties' => [],
        ]);
    }

    public function saveNode(array $data, ?CatalogNode $node = null): CatalogNode
    {
        return DB::transaction(function () use ($data, $node): CatalogNode {
            // Serialize structural edits and imports; this also closes concurrent cycle creation.
            CatalogLevel::orderBy('id')->lockForUpdate()->get();
            $creating = $node === null;
            $node = $node ? CatalogNode::lockForUpdate()->findOrFail($node->id) : new CatalogNode;
            abort_if(! $creating && $node->entity_type && ! $this->source($node, true), 409, 'Исходная сущность удалена. Обновите дерево.');
            $level = CatalogLevel::with('fields')->findOrFail($data['level_id'] ?? $node->level_id);
            $type = $level->entity_type === 'custom' ? null : $level->entity_type;
            if (! $creating && $type !== $node->entity_type) {
                throw ValidationException::withMessages(['level_id' => 'Существующую сущность нельзя преобразовать в другой тип.']);
            }
            if (array_key_exists('parent_id', $data)) {
                $this->validateParent($node, $data['parent_id']);
            }
            if ($creating || array_key_exists('properties', $data) || array_key_exists('level_id', $data)) {
                $data['properties'] = $this->validateProperties($level, array_key_exists('properties', $data) ? ($data['properties'] ?? []) : ($node->properties ?? []));
            }
            $subtree = $creating ? collect() : $this->subtree($node);
            $oldParents = $subtree->mapWithKeys(fn (CatalogNode $item): array => [$item->id => $this->nearestAncestor($item, 'product')?->entity_id]);
            if ($creating || (array_key_exists('parent_id', $data) && $node->parent_id !== $data['parent_id'])) {
                $node->is_manual = true;
            }
            $node->fill($data);
            $node->entity_type = $type;
            if (! $node->slug) {
                $node->slug = Str::slug($node->name) ?: 'catalog';
            }
            if ($creating) {
                $node->is_published ??= false;
                $node->is_featured ??= false;
                $node->sort_order ??= 0;
                $source = match ($type) {
                    'category' => Category::create(['name' => $node->name, 'is_published' => $node->is_published]),
                    'product' => Product::create(['rus' => $node->name, 'is_published' => $node->is_published]),
                    'good' => Good::create(['name' => $node->name, 'is_published' => $node->is_published]),
                    default => null,
                };
                $node->entity_id = $source?->id;
            }
            $node->save();
            $this->syncSource($node, $data);
            foreach ($this->subtree($node) as $item) {
                $this->syncRelationships($item, $oldParents[$item->id] ?? null);
            }

            return $node->fresh();
        }, 3);
    }

    public function validateProperties(CatalogLevel $level, array $values): array
    {
        $fields = $level->fields;
        $unknown = array_diff(array_keys($values), $fields->pluck('key')->all());
        if ($unknown) {
            throw ValidationException::withMessages(['properties' => 'Неизвестные свойства: '.implode(', ', $unknown)]);
        }
        $rules = [];
        foreach ($fields as $field) {
            $rules['properties.'.$field->key] = [
                $field->required ? 'required' : 'nullable',
                ...match ($field->type) {
                    'number' => ['numeric'],
                    'boolean' => ['boolean'],
                    'date' => ['date_format:Y-m-d'],
                    'url' => ['string', 'max:2048', 'url:http,https'],
                    'select' => ['string', Rule::in($field->options ?? [])],
                    'textarea' => ['string', 'max:20000'],
                    default => ['string', 'max:2000'],
                },
            ];
        }
        Validator::make(['properties' => $values], $rules)->validate();
        foreach ($fields as $field) {
            if (isset($values[$field->key])) {
                $values[$field->key] = match ($field->type) {
                    'boolean' => (bool) $values[$field->key],
                    'number' => $values[$field->key] + 0,
                    default => $values[$field->key],
                };
            }
        }

        return $values;
    }

    public function destroyNode(CatalogNode $node): void
    {
        DB::transaction(function () use ($node): void {
            CatalogLevel::orderBy('id')->lockForUpdate()->get();
            $node = CatalogNode::lockForUpdate()->findOrFail($node->id);
            abort_if($node->children()->exists(), 409, 'Сначала перенесите или удалите дочерние элементы.');
            $source = $this->source($node, true);
            if ($source instanceof Good && CatalogNode::where('entity_type', 'good')->where('entity_id', $source->id)->whereKeyNot($node->id)->exists()) {
                if ($parent = $this->nearestAncestor($node, 'product')) {
                    $source->products()->detach($parent->entity_id);
                }
                $node->delete();

                return;
            }
            if ($source instanceof Category) {
                abort_if(Product::where('category_id', $source->id)->exists() || DB::table('category_good')->where('category_id', $source->id)->exists(), 409, 'Категория используется товарами или продуктами.');
            } elseif ($source instanceof Product) {
                $this->assertUnused($source, ['goods', 'components', 'consumers', 'entityConsumptions', 'manufacturers', 'units', 'searchRequests', 'unitProductMatches', 'prospectingSearchJobs', 'prospectingCandidates', 'clientAcquisitionCampaigns']);
            } elseif ($source instanceof Good) {
                $this->assertUnused($source, ['stockMovements', 'purchases', 'sales', 'quotations', 'orderItems', 'stockAlerts', 'avitoListingLinks', 'avitoPublications', 'unitGoodMatches', 'priceCalculations', 'yandexDirectAds', 'directLaunchSessions', 'yandexDirectKeywords', 'yandexDirectDailyStats', 'yandexDirectAiDecisions', 'priceFormulas', 'priceTypeValues']);
                foreach (['good_inquiries', 'yandex_direct_campaigns', 'yandex_direct_ad_groups', 'supplier_good_prices'] as $table) {
                    abort_if(DB::table($table)->where('good_id', $source->id)->exists(), 409, 'Товар связан с обращениями, рекламой или закупочными ценами. Снимите его с публикации.');
                }
            }
            $node->delete();
            $source?->delete();
        }, 3);
    }

    private function assertUnused(Model $source, array $relations): void
    {
        foreach ($relations as $relation) {
            abort_if($source->{$relation}()->exists(), 409, 'Сущность используется в учёте или связанных справочниках. Снимите её с публикации.');
        }
    }

    private function source(CatalogNode $node, bool $lock = false): ?Model
    {
        $query = match ($node->entity_type) {
            'category' => Category::query(),
            'product' => Product::without(['category', 'manufacturers']),
            'good' => Good::with('seo'),
            default => null,
        };

        return $query?->when($lock, fn ($query) => $query->lockForUpdate())->find($node->entity_id);
    }

    private function syncSource(CatalogNode $node, array $data): void
    {
        $source = $this->source($node, true);
        if (! $source) {
            return;
        }
        $changes = [];
        foreach (['name', 'is_published'] as $key) {
            if (array_key_exists($key, $data)) {
                $changes[$key === 'name' && $source instanceof Product ? 'rus' : $key] = $data[$key];
            }
        }
        if ($source instanceof Category) {
            foreach (['slug', 'image', 'description', 'meta_title', 'meta_description', 'is_featured', 'sort_order'] as $key) {
                if (array_key_exists($key, $data)) {
                    $changes[$key] = $data[$key];
                }
            }
        } elseif ($source instanceof Good) {
            foreach (['slug', 'description'] as $key) {
                if (array_key_exists($key, $data)) {
                    $changes[$key] = $data[$key];
                }
            }
            if (array_key_exists('image', $data)) {
                $changes['ava_image'] = $data['image'];
                if ($source->ava_image !== $data['image']) {
                    $changes['ava_thumb'] = null;
                }
            }
            $seo = array_intersect_key($data, array_flip(['meta_title', 'meta_description']));
            if ($seo) {
                $source->seo()->updateOrCreate(['good_id' => $source->id], $seo);
            }
        }
        $source->update($changes);
    }

    private function syncRelationships(CatalogNode $node, ?int $oldProductId): void
    {
        if ($node->entity_type === 'category') {
            $node->update(['import_key' => 'category:'.$node->entity_id]);
        } elseif ($node->entity_type === 'product') {
            Product::whereKey($node->entity_id)->update(['category_id' => $this->nearestAncestor($node, 'category')?->entity_id]);
            $node->update(['import_key' => 'product:'.$node->entity_id]);
        } elseif ($node->entity_type === 'good') {
            $productId = $this->nearestAncestor($node, 'product')?->entity_id;
            $key = 'good:'.$node->entity_id.($productId ? ':product:'.$productId : ':root');
            abort_if(CatalogNode::where('import_key', $key)->whereKeyNot($node->id)->exists(), 422, 'Этот товар уже расположен в выбранной ветке.');
            $source = Good::find($node->entity_id);
            if ($source && $oldProductId && $oldProductId !== $productId) {
                $source->products()->detach($oldProductId);
            }
            if ($source && $productId) {
                $source->products()->syncWithoutDetaching([$productId]);
            }
            $node->update(['import_key' => $key]);
        }
    }

    private function validateParent(CatalogNode $node, ?int $parentId): void
    {
        $seen = $node->exists ? [$node->id => true] : [];
        while ($parentId) {
            if (isset($seen[$parentId])) {
                throw ValidationException::withMessages(['parent_id' => 'Нельзя переместить элемент внутрь собственной ветки.']);
            }
            $seen[$parentId] = true;
            $parentId = CatalogNode::findOrFail($parentId)->parent_id;
        }
    }

    private function nearestAncestor(CatalogNode $node, string $type): ?CatalogNode
    {
        $seen = [$node->id => true];
        $parentId = $node->parent_id;
        while ($parentId && ! isset($seen[$parentId])) {
            $seen[$parentId] = true;
            $parent = CatalogNode::find($parentId);
            if (! $parent) {
                return null;
            }
            if ($parent->entity_type === $type) {
                return $parent;
            }
            $parentId = $parent->parent_id;
        }

        return null;
    }

    private function subtree(CatalogNode $node): Collection
    {
        $result = collect([$node]);
        $pending = [$node->id];
        $seen = [$node->id => true];
        while ($pending) {
            $children = CatalogNode::whereIn('parent_id', $pending)->get()->reject(fn ($child) => isset($seen[$child->id]));
            $pending = $children->pluck('id')->all();
            foreach ($children as $child) {
                $seen[$child->id] = true;
                $result->push($child);
            }
        }

        return $result;
    }
}
