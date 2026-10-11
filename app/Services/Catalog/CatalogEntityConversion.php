<?php

namespace App\Services\Catalog;

use App\Models\CatalogLanding;
use App\Models\CatalogNode;
use App\Models\Category;
use App\Models\Good;
use App\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/** Safeguards for replacing a catalog node's backing record inside saveNode's transaction. */
class CatalogEntityConversion
{
    public function validate(CatalogNode $node, ?Model $source, ?string $type): void
    {
        if ($type === 'good' && $node->children()->exists()) {
            $this->reject('Товар не может содержать дочерние элементы. Сначала перенесите их в другой раздел.');
        }
        if ($type === 'good' && CatalogLanding::where('catalog_node_id', $node->id)->exists()) {
            $this->reject('У раздела настроена страница сайта. Её нельзя перенести в карточку товара.');
        }
        if (! $source) {
            return;
        }
        if (CatalogNode::where('entity_type', $node->entity_type)->where('entity_id', $source->id)->whereKeyNot($node->id)->exists()) {
            $this->reject('Эта сущность размещена в нескольких ветках. Сначала удалите дополнительные размещения.');
        }

        $mapped = match (true) {
            $source instanceof Category => ['name', 'slug', 'image', 'description', 'h1', 'meta_title', 'meta_description', 'is_published', 'is_featured', 'sort_order'],
            $source instanceof Product => ['rus', 'category_id', 'is_published'],
            $source instanceof Good => ['name', 'slug', 'ava_image', 'description', 'is_published'],
        };
        $defaults = match (true) {
            $source instanceof Category => ['robots' => 'index,follow'],
            $source instanceof Good => [
                'denominator' => 1,
                'measure_id' => $source->measure?->name === 'кг' ? $source->measure_id : null,
                'ava_thumb' => $source->ava_image ?: $source->ava_thumb,
            ],
            default => [],
        };
        $this->assertMappedAttributes($source, $mapped, $defaults);
        if ($source instanceof Good && $source->seo) {
            $this->assertMappedAttributes($source->seo, ['good_id', 'h1', 'meta_title', 'meta_description'], [
                'slug_override' => $source->slug,
                'canonical_url' => route('public.goods.show', ['good' => $source->slug ?: (string) $source->id]),
                'robots' => 'index,follow', 'is_active' => true, 'include_in_sitemap' => true,
                'include_in_yandex_feed' => true, 'availability_status' => 'on_request',
            ]);
        }
        $keys = $source instanceof Product ? ['source_product_ids'] : ($source instanceof Good ? ['good_ids', 'inline_good_ids'] : []);
        if ($keys !== []) {
            foreach (CatalogLanding::get(['draft_content', 'published_content']) as $landing) {
                foreach ([$landing->draft_content, $landing->published_content] as $content) {
                    foreach ($keys as $key) {
                        if (in_array($source->id, $content['catalog'][$key] ?? [])) {
                            $this->reject('Сущность включена в подборку на странице сайта. Сначала измените эту подборку.');
                        }
                    }
                }
            }
            foreach (config('product-pages.pages', []) as $productId => $settings) {
                foreach ($keys as $key) {
                    if (($source instanceof Product && (int) $productId === $source->id)
                        || in_array($source->id, $settings[$key] ?? [])) {
                        $this->reject('Сущность используется в настроенной странице сайта. Сначала измените настройки страницы.');
                    }
                }
            }
        }
    }

    private function assertMappedAttributes(Model $source, array $mapped, array $defaults): void
    {
        foreach (array_diff(array_keys($source->getAttributes()), [...$mapped, 'id', 'created_at', 'updated_at']) as $key) {
            $value = $source->getAttribute($key);
            if ($value === null || $value === '' || $value === []
                || (array_key_exists($key, $defaults) && $value == $defaults[$key])) {
                continue;
            }
            $kind = $source instanceof Product ? 'переводы названия' : ($source instanceof Category ? 'дополнительные параметры категории' : ($source instanceof Good ? 'учётные параметры или отдельная миниатюра' : 'дополнительные настройки SEO'));
            $this->reject('В карточке заполнены '.$kind.', которые нельзя перенести в выбранный тип. Сначала сохраните или очистите их.');
        }
    }

    public function retire(Model $source, ?int $oldProductId): void
    {
        // Honor declared targets to distinguish, for example, a logistics expense
        // category from a catalog category. Also protect legacy conventional keys
        // without constraints. Ordinary card saves never scan the schema.
        $foreignKey = $source->getForeignKey();
        foreach (Schema::getTables() as $table) {
            $name = $table['name'];
            $targets = [];
            foreach (Schema::getForeignKeys($name) as $constraint) {
                foreach ($constraint['columns'] as $column) {
                    $targets[$column] = $constraint['foreign_table'];
                }
            }
            foreach (Schema::getColumnListing($name) as $column) {
                $referencesSource = isset($targets[$column])
                    ? $targets[$column] === $source->getTable()
                    : $column === $foreignKey;
                if (! $referencesSource) {
                    continue;
                }
                if ($source instanceof Good && $name === 'good_seos') {
                    continue; // The portable SEO fields have already been copied.
                }
                $query = DB::table($name)->where($column, $source->id);
                if ($source instanceof Good && $name === 'good_product' && $oldProductId) {
                    // This link represents the replaced placement itself.
                    $query->where('product_id', '<>', $oldProductId);
                }
                if ($query->lockForUpdate()->first()) {
                    $this->reject('Тип нельзя изменить: сущность используется в учёте, справочниках или других разделах каталога. Сначала перенесите связанные данные.');
                }
            }
        }
        $source->delete();
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['entity_type' => $message]);
    }
}
