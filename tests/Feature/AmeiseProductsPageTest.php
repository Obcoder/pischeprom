<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AmeiseProductsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_catalog_has_its_own_page_and_header_navigation(): void
    {
        $this->get('/Ameise/products')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Ameise/Products'));

        $productsPage = (string) file_get_contents(resource_path('js/Pages/Ameise/Products.vue'));
        $grossbuchPage = (string) file_get_contents(resource_path('js/Pages/Ameise/Grossbuch.vue'));
        $layout = (string) file_get_contents(resource_path('js/Layouts/VerwalterLayout.vue'));

        foreach (['Categories', 'Products', 'Goods', 'Components', 'Commodities', 'Услуги'] as $title) {
            $this->assertStringContainsString($title, $productsPage);
        }

        $this->assertStringNotContainsString('<v-tab value="products">Products</v-tab>', $grossbuchPage);
        $this->assertStringNotContainsString('v-tabs-window-item value="products"', $grossbuchPage);
        $this->assertStringContainsString("route('Ameise.products')", $layout);
        $this->assertStringContainsString('mdi-package-variant-closed', $layout);
    }

    public function test_products_tables_use_the_shared_viewport_layout(): void
    {
        $productsPage = (string) file_get_contents(resource_path('js/Pages/Ameise/Products.vue'));
        $categories = (string) file_get_contents(resource_path('js/Components/Dictionaries/Categories.vue'));
        $products = (string) file_get_contents(resource_path('js/Components/Dictionaries/Products.vue'));
        $goods = (string) file_get_contents(resource_path('js/Components/Dictionaries/Goods.vue'));
        $commodities = (string) file_get_contents(resource_path('js/Components/Dictionaries/Commodities/CommoditiesPage.vue'));
        $services = (string) file_get_contents(resource_path('js/Components/Dictionaries/Services/ServicesPage.vue'));

        $this->assertStringContainsString('height: calc(100dvh - 58px)', $productsPage);
        $this->assertStringContainsString('.products-page__tabs :deep(.v-tab)', $productsPage);
        $this->assertStringContainsString('.v-field:not(.v-field--variant-plain)', $productsPage);

        $this->assertStringNotContainsString('height="calc(100vh - 300px)"', $productsPage);
        $this->assertStringNotContainsString('height="720px"', $categories);
        $this->assertStringNotContainsString('height="calc(100vh - 290px)"', $products);
        $this->assertStringNotContainsString('height="760px"', $goods);
        $this->assertStringNotContainsString('tableHeight', $commodities);
        $this->assertStringNotContainsString('tableHeight', $services);

        foreach ([$categories, $products, $goods, $commodities, $services] as $tableSource) {
            $this->assertStringContainsString('.v-table__wrapper', $tableSource);
            $this->assertStringContainsString('min-height: 0', $tableSource);
        }

        $this->assertStringContainsString('<v-avatar size="44"', $goods);
        $this->assertStringContainsString('height: 52px !important', $goods);
        $this->assertStringContainsString('class="goods-table-region"', $goods);
        $this->assertStringContainsString('flex: 1 1 0', $goods);
    }
}
