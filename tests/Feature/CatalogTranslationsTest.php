<?php

namespace Tests\Feature;

use App\Models\CatalogLevel;
use App\Models\CatalogNode;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CatalogTranslationsTest extends TestCase
{
    use RefreshDatabase;

    private const LOCALES = ['ru', 'en', 'zh', 'es', 'ar', 'hi', 'ur', 'de', 'fr', 'pt', 'it', 'nl', 'tr', 'fa', 'vi', 'ja', 'ko', 'he', 'id'];

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

    #[DataProvider('nodeKinds')]
    public function test_every_kind_and_classification_can_edit_languages(string $kind): void
    {
        $this->staff();
        $node = $this->createNode($kind);
        $endpoint = '/api/catalog/nodes/'.$node['id'].'/translations';
        $initial = $this->getJson($endpoint)->assertOk()
            ->assertJsonPath('data.id', $node['id'])
            ->assertJsonPath('data.translations.ru', 'Пектин яблочный')
            ->assertJsonPath('data.translations.en', null);
        $this->assertEqualsCanonicalizing(self::LOCALES, array_keys($initial->json('data.translations')));

        $this->patchJson($endpoint, ['translations' => ['ru' => 'Пектин цитрусовый', 'en' => 'Citrus pectin', 'zh' => '柑橘果胶']])
            ->assertOk()->assertJsonPath('data.name', 'Пектин цитрусовый')
            ->assertJsonPath('data.entity_type', $node['entity_type'])
            ->assertJsonPath('data.entity_id', $node['entity_id'])
            ->assertJsonPath('data.level_id', $node['level_id'])
            ->assertJsonPath('data.parent_id', $node['parent_id'])
            ->assertJsonPath('data.translations.ru', 'Пектин цитрусовый')
            ->assertJsonPath('data.translations.en', 'Citrus pectin')
            ->assertJsonPath('data.translations.zh', '柑橘果胶');
        $this->assertDatabaseHas('catalog_nodes', ['id' => $node['id'], 'name' => 'Пектин цитрусовый']);
        $this->assertDatabaseHas('catalog_node_translations', ['node_id' => $node['id'], 'locale' => 'en', 'name' => 'Citrus pectin']);
        $this->assertDatabaseMissing('catalog_node_translations', ['node_id' => $node['id'], 'locale' => 'ru']);
        if ($node['entity_type']) {
            $this->assertDatabaseHas(match ($node['entity_type']) {
                'product' => 'products', 'category' => 'categories', 'good' => 'goods',
            }, ['id' => $node['entity_id'], $kind === 'product' ? 'rus' : 'name' => 'Пектин цитрусовый']);
        }
        $this->getJson($endpoint)->assertOk()->assertJsonPath('data.translations.en', 'Citrus pectin');
        $snapshot = collect($this->getJson('/api/catalog')->assertOk()->json('nodes'))->keyBy('id');
        $this->assertSame('Citrus pectin', $snapshot[$node['id']]['translations']['en']);
    }

    public static function nodeKinds(): array
    {
        return ['category' => ['category'], 'product' => ['product'], 'good' => ['good'], 'custom level' => ['custom'], 'domain' => ['domain'], 'unclassified' => ['unclassified']];
    }

    public function test_partial_updates_clear_only_requested_languages_and_preserve_catalog_fields(): void
    {
        $this->staff();
        $node = $this->createNode('product', ['slug' => 'pectin', 'is_published' => false, 'meta_title' => 'SEO пектина']);
        $endpoint = '/api/catalog/nodes/'.$node['id'].'/translations';
        $this->patchJson($endpoint, ['translations' => ['en' => 'Apple pectin', 'de' => 'Apfelpektin', 'pt' => 'Pectina de maçã']])->assertOk();
        $this->patchJson($endpoint, ['translations' => ['en' => null, 'pt' => '   ']])
            ->assertOk()->assertJsonPath('data.translations.en', null)->assertJsonPath('data.translations.pt', null)
            ->assertJsonPath('data.translations.de', 'Apfelpektin')->assertJsonPath('data.translations.ru', 'Пектин яблочный')
            ->assertJsonPath('data.slug', 'pectin')->assertJsonPath('data.is_published', false)
            ->assertJsonPath('data.meta_title', 'SEO пектина');
        $this->assertDatabaseHas('products', ['id' => $node['entity_id'], 'eng' => null, 'po' => null, 'de' => 'Apfelpektin']);
        $this->assertDatabaseMissing('catalog_node_translations', ['node_id' => $node['id'], 'locale' => 'en']);
        $this->assertDatabaseMissing('catalog_node_translations', ['node_id' => $node['id'], 'locale' => 'pt']);
        $this->getJson('/api/catalog')->assertOk();
        $this->getJson($endpoint)->assertOk()->assertJsonPath('data.translations.en', null)->assertJsonPath('data.translations.de', 'Apfelpektin');
    }

    #[DataProvider('invalidTranslations')]
    public function test_invalid_language_updates_are_atomic(array $translations, string $error): void
    {
        $this->staff();
        $node = $this->createNode('product');
        $endpoint = '/api/catalog/nodes/'.$node['id'].'/translations';
        $this->patchJson($endpoint, ['translations' => ['en' => 'Apple pectin', 'de' => 'Apfelpektin']])->assertOk();
        $this->patchJson($endpoint, ['translations' => ['en' => 'Must not save', ...$translations]])
            ->assertUnprocessable()->assertJsonValidationErrors($error);
        $this->getJson($endpoint)->assertOk()->assertJsonPath('data.name', 'Пектин яблочный')
            ->assertJsonPath('data.translations.en', 'Apple pectin')->assertJsonPath('data.translations.de', 'Apfelpektin');
        $this->assertDatabaseHas('products', ['id' => $node['entity_id'], 'rus' => 'Пектин яблочный', 'eng' => 'Apple pectin']);
        $this->assertDatabaseHas('catalog_node_translations', ['node_id' => $node['id'], 'locale' => 'en', 'name' => 'Apple pectin']);
    }

    public static function invalidTranslations(): array
    {
        return [
            'unknown language' => [['xx' => 'Unsupported'], 'translations'],
            'legacy code' => [['eng' => 'Legacy'], 'translations'],
            'oversized translation' => [['de' => str_repeat('я', 256)], 'translations.de'],
            'nested value' => [['de' => ['Apfelpektin']], 'translations.de'],
            'empty Russian' => [['ru' => '  '], 'translations.ru'],
            'null Russian' => [['ru' => null], 'translations.ru'],
        ];
    }

    public function test_guest_cannot_read_or_change_translations(): void
    {
        $node = CatalogNode::create(['name' => 'Пектин']);
        $this->getJson('/api/catalog/nodes/'.$node->id.'/translations')->assertUnauthorized();
        $this->patchJson('/api/catalog/nodes/'.$node->id.'/translations', ['translations' => ['en' => 'Forbidden']])->assertUnauthorized();
        $this->assertDatabaseCount('catalog_node_translations', 0);
    }

    #[DataProvider('unauthorizedUsers')]
    public function test_translation_access_requires_active_staff(array $attributes, bool $admin): void
    {
        $node = CatalogNode::create(['name' => 'Пектин']);
        $actor = User::factory()->create(['type' => 'employee', 'status' => 'active', ...$attributes]);
        if ($admin) {
            $actor->assignRole(Role::findOrCreate('admin', 'crm'));
        }
        $this->actingAs($actor);
        $this->getJson('/api/catalog/nodes/'.$node->id.'/translations')->assertForbidden();
        $this->patchJson('/api/catalog/nodes/'.$node->id.'/translations', ['translations' => ['en' => 'Forbidden']])->assertForbidden();
        $this->assertDatabaseCount('catalog_node_translations', 0);
    }

    public static function unauthorizedUsers(): array
    {
        return [
            'customer' => [['type' => 'customer'], false],
            'blocked employee' => [['status' => 'blocked'], false],
            'blocked administrator' => [['status' => 'blocked'], true],
        ];
    }

    public function test_active_administrator_with_legacy_customer_type_can_edit_translations(): void
    {
        $actor = User::factory()->create(['type' => 'customer', 'status' => 'active']);
        $actor->assignRole(Role::findOrCreate('admin', 'crm'));
        $this->actingAs($actor);
        $node = $this->createNode('custom');
        $this->patchJson('/api/catalog/nodes/'.$node['id'].'/translations', ['translations' => ['en' => 'Pectin']])
            ->assertOk()->assertJsonPath('data.translations.en', 'Pectin');
    }

    public function test_migration_backfills_all_legacy_languages_without_losing_unicode_or_source_values(): void
    {
        $migration = require database_path('migrations/2026_10_11_130000_create_catalog_node_translations_table.php');
        $migration->down();
        $product = Product::create(['rus' => 'Пектин яблочный', ...$this->legacyTranslations()]);
        $node = CatalogNode::create(['entity_type' => 'product', 'entity_id' => $product->id, 'name' => 'Прежнее имя']);
        $custom = CatalogNode::create(['name' => 'Произвольная группа']);
        $migration->up();

        $this->assertDatabaseCount('catalog_node_translations', 18);
        foreach ($this->foreignTranslations() as $locale => $name) {
            $this->assertDatabaseHas('catalog_node_translations', ['node_id' => $node->id, 'locale' => $locale, 'name' => $name]);
        }
        $this->assertDatabaseMissing('catalog_node_translations', ['node_id' => $custom->id]);
        $this->assertDatabaseMissing('catalog_node_translations', ['locale' => 'ru']);
        $this->assertSame('Прежнее имя', $node->fresh()->name);
        foreach ($this->legacyTranslations() as $column => $name) {
            $this->assertSame($name, $product->fresh()->getAttribute($column));
        }
    }

    public function test_new_legacy_products_import_all_languages_and_legacy_edits_remain_visible(): void
    {
        $this->staff();
        $this->getJson('/api/catalog')->assertOk()->assertJsonCount(0, 'nodes');
        $product = Product::create(['rus' => 'Пектин яблочный', ...$this->legacyTranslations()]);
        $node = $this->getJson('/api/catalog')->assertOk()->json('nodes.0');
        $this->assertSame(['ru' => 'Пектин яблочный', ...$this->foreignTranslations()], $node['translations']);
        $this->assertDatabaseCount('catalog_node_translations', 18);
        $this->putJson('/api/products/'.$product->id, ['rus' => 'Пектин новый', 'eng' => 'Updated pectin', 'de' => null])->assertOk();
        $this->getJson('/api/catalog/nodes/'.$node['id'].'/translations')->assertOk()
            ->assertJsonPath('data.name', 'Пектин новый')->assertJsonPath('data.translations.ru', 'Пектин новый')
            ->assertJsonPath('data.translations.en', 'Updated pectin')->assertJsonPath('data.translations.de', null)
            ->assertJsonPath('data.translations.pt', 'Pectina de maçã');
        DB::table('products')->where('id', $product->id)->update(['eng' => 'Bulk updated pectin']);
        $this->patchJson('/api/catalog/nodes/'.$node['id'], ['entity_type' => 'custom'])
            ->assertOk()->assertJsonPath('data.translations.en', 'Bulk updated pectin')
            ->assertJsonPath('data.translations.de', null)->assertJsonPath('data.translations.pt', 'Pectina de maçã');
    }

    public function test_rollback_preserves_translations_that_have_no_legacy_storage(): void
    {
        $this->staff();
        $node = $this->createNode('custom');
        $this->patchJson('/api/catalog/nodes/'.$node['id'].'/translations', ['translations' => ['en' => 'Apple pectin']])->assertOk();
        $migration = require database_path('migrations/2026_10_11_130000_create_catalog_node_translations_table.php');

        try {
            $migration->down();
            $this->fail('Rollback must not discard translations belonging to non-product nodes.');
        } catch (\RuntimeException $exception) {
            $this->assertNotEmpty($exception->getMessage());
        }

        $this->assertDatabaseHas('catalog_node_translations', ['node_id' => $node['id'], 'locale' => 'en', 'name' => 'Apple pectin']);
        $this->getJson('/api/catalog/nodes/'.$node['id'].'/translations')->assertOk()->assertJsonPath('data.translations.en', 'Apple pectin');
    }

    public function test_rollback_keeps_product_translations_available_in_legacy_columns(): void
    {
        $this->staff();
        $node = $this->createNode('product');
        $this->patchJson('/api/catalog/nodes/'.$node['id'].'/translations', ['translations' => $this->foreignTranslations()])->assertOk();
        $migration = require database_path('migrations/2026_10_11_130000_create_catalog_node_translations_table.php');
        $migration->down();

        $product = Product::findOrFail($node['entity_id']);
        foreach ($this->legacyTranslations() as $column => $name) {
            $this->assertSame($name, $product->getAttribute($column));
        }
        $migration->up();
        $this->assertDatabaseCount('catalog_node_translations', 18);
    }

    public function test_all_languages_survive_conversion_reclassification_and_return_to_product(): void
    {
        $this->staff();
        $node = $this->createNode('product');
        $this->patchJson('/api/catalog/nodes/'.$node['id'].'/translations', ['translations' => $this->foreignTranslations()])->assertOk();
        $parent = $this->createNode('custom', ['name' => 'Новая ветка']);
        $level = CatalogLevel::create(['name' => 'Сорт', 'entity_type' => 'custom']);
        $this->patchJson('/api/catalog/nodes/'.$node['id'], ['parent_id' => $parent['id'], 'level_id' => $level->id])
            ->assertOk()->assertJsonPath('data.translations.en', 'Apple pectin');
        $expected = ['ru' => 'Пектин яблочный', ...$this->foreignTranslations()];
        foreach (['category', 'custom', 'good', 'product'] as $type) {
            $response = $this->patchJson('/api/catalog/nodes/'.$node['id'], ['entity_type' => $type])
                ->assertOk()->assertJsonPath('data.id', $node['id'])->assertJsonPath('data.level_id', $level->id)
                ->assertJsonPath('data.parent_id', $parent['id']);
            $this->assertSame($expected, $response->json('data.translations'));
            $this->getJson('/api/catalog')->assertOk()->assertJsonCount(2, 'nodes');
        }
        $product = Product::findOrFail($response->json('data.entity_id'));
        foreach ($this->legacyTranslations() as $column => $name) {
            $this->assertSame($name, $product->getAttribute($column));
        }
        $this->patchJson('/api/catalog/nodes/'.$node['id'], ['level_id' => null, 'name' => 'Новое русское имя'])
            ->assertOk()->assertJsonPath('data.translations.ru', 'Новое русское имя')->assertJsonPath('data.translations.en', 'Apple pectin');
        $this->assertDatabaseCount('catalog_node_translations', 18);
    }

    public function test_rejected_conversion_rolls_back_archived_legacy_translations(): void
    {
        $this->staff();
        $node = $this->createNode('product');
        $this->patchJson('/api/catalog/nodes/'.$node['id'].'/translations', ['translations' => ['en' => 'Apple pectin']])->assertOk();
        Product::findOrFail($node['entity_id'])->update(['eng' => 'Current English name', 'de' => 'Apfelpektin']);
        DB::table('home_banners')->insert(['title' => 'Ссылка на продукт', 'product_id' => $node['entity_id']]);

        $this->patchJson('/api/catalog/nodes/'.$node['id'], ['entity_type' => 'category', 'name' => 'Не сохранять'])
            ->assertUnprocessable()->assertJsonValidationErrors('entity_type');

        $this->assertDatabaseHas('catalog_nodes', ['id' => $node['id'], 'entity_type' => 'product', 'entity_id' => $node['entity_id'], 'name' => 'Пектин яблочный']);
        $this->assertDatabaseHas('products', ['id' => $node['entity_id'], 'rus' => 'Пектин яблочный', 'eng' => 'Current English name', 'de' => 'Apfelpektin']);
        $this->assertDatabaseHas('catalog_node_translations', ['node_id' => $node['id'], 'locale' => 'en', 'name' => 'Apple pectin']);
        $this->assertDatabaseMissing('catalog_node_translations', ['node_id' => $node['id'], 'locale' => 'de']);
        $this->assertDatabaseCount('categories', 0);
        $this->assertDatabaseHas('home_banners', ['product_id' => $node['entity_id'], 'title' => 'Ссылка на продукт']);
    }

    public function test_deleting_a_classification_level_preserves_node_languages(): void
    {
        $this->staff();
        $node = $this->createNode('custom');
        $endpoint = '/api/catalog/nodes/'.$node['id'].'/translations';
        $this->patchJson($endpoint, ['translations' => ['en' => 'Apple pectin', 'zh' => '苹果果胶']])->assertOk();

        $this->deleteJson('/api/catalog/levels/'.$node['level_id'])->assertConflict();
        $this->patchJson('/api/catalog/nodes/'.$node['id'], ['level_id' => null])->assertOk();
        $this->deleteJson('/api/catalog/levels/'.$node['level_id'])->assertNoContent();

        $this->assertDatabaseMissing('catalog_levels', ['id' => $node['level_id']]);
        $this->getJson($endpoint)->assertOk()->assertJsonPath('data.id', $node['id'])
            ->assertJsonPath('data.level_id', null)->assertJsonPath('data.name', 'Пектин яблочный')
            ->assertJsonPath('data.translations.en', 'Apple pectin')->assertJsonPath('data.translations.zh', '苹果果胶');
        $this->patchJson('/api/catalog/nodes/'.$node['id'], ['level_id' => CatalogLevel::where('entity_type', 'category')->value('id')])
            ->assertOk()->assertJsonPath('data.entity_type', null)
            ->assertJsonPath('data.translations.en', 'Apple pectin')->assertJsonPath('data.translations.zh', '苹果果胶');
        $this->assertDatabaseCount('catalog_node_translations', 2);
    }

    public function test_deleting_a_node_cascades_only_its_translations(): void
    {
        $this->staff();
        $first = $this->createNode('custom');
        $second = $this->createNode('custom');
        foreach ([$first, $second] as $node) {
            $this->patchJson('/api/catalog/nodes/'.$node['id'].'/translations', ['translations' => ['en' => 'Pectin']])->assertOk();
        }
        $this->deleteJson('/api/catalog/nodes/'.$first['id'])->assertNoContent();
        $this->assertDatabaseMissing('catalog_node_translations', ['node_id' => $first['id']]);
        $this->assertDatabaseHas('catalog_node_translations', ['node_id' => $second['id'], 'locale' => 'en', 'name' => 'Pectin']);
    }

    private function staff(): void
    {
        $this->actingAs(User::factory()->create(['type' => 'employee', 'status' => 'active']));
    }

    private function createNode(string $kind, array $overrides = []): array
    {
        $levelId = match ($kind) {
            'unclassified' => null,
            'domain' => CatalogLevel::where('is_domain', true)->value('id'),
            'custom' => CatalogLevel::create(['name' => 'Класс', 'entity_type' => 'custom'])->id,
            default => CatalogLevel::where('entity_type', $kind)->value('id'),
        };

        return $this->postJson('/api/catalog/nodes', [
            'name' => 'Пектин яблочный', 'level_id' => $levelId,
            'entity_type' => in_array($kind, ['domain', 'unclassified'], true) ? 'custom' : $kind,
            ...$overrides,
        ])->assertCreated()->json('data');
    }

    private function foreignTranslations(): array
    {
        return [
            'en' => 'Apple pectin', 'zh' => '苹果果胶', 'es' => 'Pectina de manzana', 'ar' => 'بكتين التفاح',
            'hi' => 'सेब पेक्टिन', 'ur' => 'سیب پیکٹین', 'de' => 'Apfelpektin', 'fr' => 'Pectine de pomme',
            'pt' => 'Pectina de maçã', 'it' => 'Pectina di mela', 'nl' => 'Appelpectine', 'tr' => 'Elma pektini',
            'fa' => 'پکتین سیب', 'vi' => 'Pectin táo', 'ja' => 'リンゴペクチン', 'ko' => '사과 펙틴',
            'he' => 'פקטין תפוחים', 'id' => 'Pektin apel',
        ];
    }

    private function legacyTranslations(): array
    {
        $columns = ['en' => 'eng', 'pt' => 'po', 'tr' => 'tu', 'id' => 'idn'];

        return collect($this->foreignTranslations())->mapWithKeys(fn ($name, $locale) => [$columns[$locale] ?? $locale => $name])->all();
    }
}
