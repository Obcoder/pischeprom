<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Good;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GoodSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['type' => 'employee', 'status' => 'active']));
    }

    public function test_search_finds_cyrillic_names_in_any_case_and_ignores_descriptions(): void
    {
        $liver = Good::query()->create(['name' => 'Печень трески']);
        Good::query()->create([
            'name' => 'Масло подсолнечное',
            'description' => 'Обеспечивает полноценное питание.',
        ]);

        foreach (['печ', 'ПЕЧ', '  пЕч  '] as $search) {
            $this->getJson(route('goods.index', ['view' => 'table', 'search' => $search]))
                ->assertOk()
                ->assertJsonPath('total', 1)
                ->assertJsonPath('data.0.id', $liver->id);
        }
    }

    public function test_search_ignores_slug_and_denominator(): void
    {
        Good::query()->create([
            'name' => 'Печень трески',
            'slug' => 'hidden-cod-liver',
            'denominator' => 777,
        ]);

        foreach (['hidden', '777'] as $search) {
            $this->getJson(route('goods.index', ['view' => 'table', 'search' => $search]))
                ->assertOk()
                ->assertJsonPath('total', 0)
                ->assertJsonCount(0, 'data');
        }
    }

    public function test_search_matches_category_names_without_duplicates_or_other_product_fields(): void
    {
        $category = Category::query()->create(['name' => 'РЫБНЫЕ КОНСЕРВЫ']);
        $good = Good::query()->create(['name' => 'Печень трески']);
        foreach (['Печень', 'Консервы'] as $name) {
            $good->products()->attach(Product::query()->create([
                'rus' => $name,
                'category_id' => $category->id,
            ]));
        }

        $otherCategory = Category::query()->create([
            'name' => 'Морепродукты',
            'description' => 'Рыбные деликатесы',
        ]);
        $otherGood = Good::query()->create(['name' => 'Икра трески']);
        $otherGood->products()->attach(Product::query()->create([
            'rus' => 'Рыбные продукты',
            'category_id' => $otherCategory->id,
        ]));

        foreach (['рыбные', 'РыБнЫе'] as $search) {
            $this->getJson(route('goods.index', ['view' => 'table', 'search' => $search]))
                ->assertOk()
                ->assertJsonPath('total', 1)
                ->assertJsonPath('data.0.id', $good->id);
        }
    }

    public function test_name_and_category_matches_respect_publication_filter_and_pagination(): void
    {
        $nameMatch = Good::query()->create(['name' => 'А Печень трески', 'is_published' => true]);
        $category = Category::query()->create(['name' => 'Печень']);
        $product = Product::query()->create(['rus' => 'Треска', 'category_id' => $category->id]);
        $categoryMatch = Good::query()->create(['name' => 'Б Консервы', 'is_published' => true]);
        $categoryMatch->products()->attach($product);
        $unpublished = Good::query()->create(['name' => 'В Консервы', 'is_published' => false]);
        $unpublished->products()->attach($product);
        Good::query()->create(['name' => 'Г Орехи', 'is_published' => true]);

        foreach ([1 => $nameMatch, 2 => $categoryMatch] as $page => $good) {
            $this->getJson(route('goods.index', [
                'view' => 'table',
                'search' => 'печ',
                'is_published' => 'true',
                'per_page' => 1,
                'page' => $page,
            ]))->assertOk()
                ->assertJsonPath('total', 2)
                ->assertJsonPath('page', $page)
                ->assertJsonPath('last_page', 2)
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', $good->id);
        }

        $this->getJson(route('goods.index', ['search' => 'печ', 'is_published' => 'false']))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $unpublished->id);
    }

    #[DataProvider('literalSearches')]
    public function test_search_treats_sql_wildcards_and_escape_character_literally(
        string $search,
        string $matchingName,
        string $otherName,
    ): void {
        $good = Good::query()->create(['name' => $matchingName]);
        Good::query()->create(['name' => $otherName]);

        $category = Category::query()->create(['name' => $matchingName]);
        $categoryMatch = Good::query()->create(['name' => 'Товар в категории']);
        $categoryMatch->products()->attach(Product::query()->create([
            'rus' => 'Продукт',
            'category_id' => $category->id,
        ]));

        $this->getJson(route('goods.index', ['view' => 'table', 'search' => $search]))
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonPath('data', fn (array $data): bool => collect($data)->pluck('id')->sort()->values()->all()
                === [$good->id, $categoryMatch->id]);
    }

    public static function literalSearches(): array
    {
        return [
            'percent' => ['%', 'Масло 82.5%', 'Масло 82.5'],
            'underscore' => ['_', 'Товар_А', 'Товар А'],
            'escape character' => ['!', 'Скидка! 20%', 'Скидка 20%'],
        ];
    }
}
