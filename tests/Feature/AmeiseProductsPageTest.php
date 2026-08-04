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
}
