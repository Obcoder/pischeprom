<?php

namespace Tests\Feature;

use App\Models\Action;
use App\Models\Category;
use App\Models\Component;
use App\Models\Consumption;
use App\Models\Entity;
use App\Models\Good;
use App\Models\Measure;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\Sale;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CatalogProductOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        config()->set(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
    }

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        config()->set('services.indexnow.key', null);
    }

    public function test_restored_product_panels_load_the_original_business_relationships(): void
    {
        $this->actingAs(User::factory()->create(['type' => 'employee', 'status' => 'active']));
        $category = Category::create(['name' => 'Рыба']);
        $product = Product::create(['rus' => 'Скумбрия', 'eng' => 'Mackerel', 'category_id' => $category->id]);
        $otherProduct = Product::create(['rus' => 'Треска']);
        $unit = Unit::create(['name' => 'Рыбозавод']);
        $component = Component::create(['name' => 'Рыбное филе']);
        $action = new Action;
        $action->name = 'Переработка';
        $action->save();
        $measure = Measure::firstOrCreate(['name' => 'кг']);
        $product->manufacturers()->attach($unit);
        $product->components()->attach($component);
        $product->units()->attach($unit, ['action_id' => $action->id]);
        Consumption::create(['unit_id' => $unit->id, 'product_id' => $product->id, 'quantity' => 100, 'measure_id' => $measure->id]);
        $good = Good::create(['name' => 'Скумбрия 300+', 'measure_id' => $measure->id]);
        $otherGood = Good::create(['name' => 'Треска 1+', 'measure_id' => $measure->id]);
        $product->goods()->attach($good);
        $otherProduct->goods()->attach($otherGood);
        $quotation = Quotation::create(['good_id' => $good->id, 'unit_id' => $unit->id, 'price' => 80, 'measure_id' => $measure->id, 'denominator' => 1]);
        $entity = Entity::create(['name' => 'Покупатель']);
        $sale = Sale::create(['date' => '2026-10-01', 'entity_id' => $entity->id, 'total' => 900]);
        $sale->goods()->attach($good, ['quantity' => 2, 'price' => 100, 'measure_id' => $measure->id]);
        $sale->goods()->attach($otherGood, ['quantity' => 7, 'price' => 100, 'measure_id' => $measure->id]);
        $unrelatedSale = Sale::create(['date' => '2026-10-02', 'entity_id' => $entity->id, 'total' => 100]);
        $unrelatedSale->goods()->attach($otherGood, ['quantity' => 1, 'price' => 100, 'measure_id' => $measure->id]);

        $this->getJson('/api/products/'.$product->id)->assertOk()
            ->assertJsonPath('id', $product->id)
            ->assertJsonPath('category.id', $category->id)
            ->assertJsonPath('manufacturers.0.id', $unit->id)
            ->assertJsonPath('components.0.id', $component->id)
            ->assertJsonPath('units.0.product_action.name', 'Переработка')
            ->assertJsonPath('consumers.0.unit.id', $unit->id)
            ->assertJsonPath('consumers.0.measure.id', $measure->id)
            ->assertJsonCount(1, 'goods')
            ->assertJsonPath('goods.0.quotations.0.id', $quotation->id)
            ->assertJsonPath('goods.0.quotations.0.unit.name', 'Рыбозавод')
            ->assertJsonCount(1, 'sales')
            ->assertJsonPath('sales.0.id', $sale->id)
            ->assertJsonPath('sales.0.total', '900.00')
            ->assertJsonCount(2, 'sales.0.goods');
        Http::assertNothingSent();
    }

    public function test_language_editor_preserves_category_publication_and_unedited_translations(): void
    {
        $this->actingAs(User::factory()->create(['type' => 'employee', 'status' => 'active']));
        $category = Category::create(['name' => 'Рыба']);
        $product = Product::create(['rus' => 'Скумбрия', 'eng' => 'Mackerel', 'de' => 'Makrele', 'category_id' => $category->id, 'is_published' => false]);

        $this->putJson('/api/products/'.$product->id, ['rus' => 'Скумбрия атлантическая', 'eng' => 'Atlantic mackerel'])
            ->assertOk()
            ->assertJsonPath('rus', 'Скумбрия атлантическая')
            ->assertJsonPath('eng', 'Atlantic mackerel')
            ->assertJsonPath('de', 'Makrele')
            ->assertJsonPath('is_published', false)
            ->assertJsonPath('category_id', $category->id);

        $this->putJson('/api/products/'.$product->id, ['rus' => ' '])->assertUnprocessable()->assertJsonValidationErrors('rus');
        $this->assertDatabaseHas('products', ['id' => $product->id, 'rus' => 'Скумбрия атлантическая']);
    }

    public function test_product_operations_remain_staff_only(): void
    {
        $product = Product::create(['rus' => 'Скумбрия']);
        $this->getJson('/api/products/'.$product->id)->assertUnauthorized();
        $this->putJson('/api/products/'.$product->id, ['rus' => 'Подмена'])->assertUnauthorized();

        foreach ([['type' => 'customer', 'status' => 'active'], ['type' => 'employee', 'status' => 'blocked']] as $attributes) {
            $this->actingAs(User::factory()->create($attributes));
            $this->getJson('/api/products/'.$product->id)->assertForbidden();
            $this->putJson('/api/products/'.$product->id, ['rus' => 'Подмена'])->assertForbidden();
        }
        $this->assertDatabaseHas('products', ['id' => $product->id, 'rus' => 'Скумбрия']);
    }
}
