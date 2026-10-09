<?php

namespace Tests\Support;

use App\Models\CatalogLevel;
use App\Models\CatalogNode;
use App\Models\Category;
use App\Models\Product;

trait CreatesClassCatalog
{
    private function createClassCatalog(Product $product, ?Category $category = null): CatalogNode
    {
        $category ??= Category::forceCreate(['id' => 25, 'name' => 'Рыба', 'slug' => 'ryba', 'is_published' => true]);
        $domainLevel = CatalogLevel::create(['name' => 'Домен SSR', 'entity_type' => 'custom', 'is_domain' => true]);
        $categoryLevel = CatalogLevel::create(['name' => 'Категория SSR', 'entity_type' => 'category']);
        $productLevel = CatalogLevel::create(['name' => 'Класс SSR', 'entity_type' => 'product']);
        CatalogNode::forceCreate(['id' => 417, 'level_id' => $domainLevel->id, 'name' => 'Продукты пищевые',
            'slug' => 'produkty-pishchevye', 'is_published' => true]);
        CatalogNode::forceCreate(['id' => 25, 'parent_id' => 417, 'level_id' => $categoryLevel->id,
            'entity_type' => 'category', 'entity_id' => $category->id, 'name' => $category->name,
            'slug' => $category->slug, 'is_published' => true]);

        return CatalogNode::forceCreate(['id' => 160, 'parent_id' => 25, 'level_id' => $productLevel->id,
            'entity_type' => 'product', 'entity_id' => $product->id, 'name' => $product->rus,
            'slug' => 'skumbriia', 'is_published' => true]);
    }

    private function classUrl(): string
    {
        return url('/catalog/ryba/skumbriia');
    }
}
