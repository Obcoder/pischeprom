<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Country;
use App\Models\EntityClassification;
use App\Models\Field;
use App\Models\Good;
use App\Models\Industry;
use App\Models\Product;
use App\Models\User;
use App\Models\VatRate;
use App\Services\Goods\GoodTradeCodes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GoodCatalogManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('yandex');
        Http::preventStrayRequests();
        config()->set('filesystems.disks.yandex.url', 'https://storage.yandexcloud.net/goods-test');
        config()->set('filesystems.disks.yandex.bucket', 'goods-test');
        $this->actingAs(User::factory()->create(['type' => 'employee', 'status' => 'active']));
    }

    public function test_trade_codes_round_trip_through_create_list_card_update_and_export(): void
    {
        $codes = [
            'hs_code' => ' 0101.21 ',
            'tn_ved_code' => '0101 21 000 0',
            'okpd2_code' => '10 20 25 110',
            'cn_code' => '0101 21 00',
            'taric_code' => '0101210000',
            'htsus_code' => '0101.21.0000',
            'schedule_b_code' => '0101210000',
            'gtin' => '00012345600012',
            'unspsc_code' => '50121538',
            'cas_number' => ' 7647-14-5 ',
            'eccn_code' => 'ear99',
        ];

        $response = $this->postJson(route('goods.store'), ['name' => 'Печень трески', ...$codes])->assertCreated();
        $id = $response->json('id');
        $normalized = GoodTradeCodes::normalize($codes);
        foreach ($normalized as $field => $value) {
            $response->assertJsonPath($field, $value);
        }
        $this->assertSame('010121', $response->json('hs_code'));
        $this->assertSame('10.20.25.110', $response->json('okpd2_code'));
        $this->assertSame('EAR99', $response->json('eccn_code'));

        $this->getJson(route('goods.index', ['view' => 'table']))->assertOk()->assertJsonPath('data.0.gtin', '00012345600012');
        $this->getJson(route('good.fetch', ['id' => $id]))->assertOk()->assertJsonPath('tn_ved_code', '0101210000');
        $export = json_decode(Storage::disk('yandex')->get("goods/{$id}/good.json"), true);
        $this->assertSame($normalized, array_intersect_key($export, $normalized));

        $this->patchJson(route('goods.update', $id), ['hs_code' => '', 'name' => 'Консервы'])
            ->assertOk()->assertJsonPath('hs_code', null)->assertJsonPath('tn_ved_code', '0101210000');
        $this->deleteJson(route('goods.destroy', $id))->assertNoContent();
        $this->assertDatabaseMissing('goods', ['id' => $id]);
        Storage::disk('yandex')->assertMissing("goods/{$id}/good.json");
    }

    #[DataProvider('invalidCodes')]
    public function test_invalid_code_formats_are_rejected(string $field, mixed $value): void
    {
        $this->postJson(route('goods.store'), ['name' => 'Товар', $field => $value])
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('goods', 0);
    }

    public function test_htsus_accepts_eight_digit_tariff_and_ten_digit_statistical_codes(): void
    {
        $response = $this->postJson(route('goods.store'), ['name' => 'Товар', 'htsus_code' => '0106.19.91'])
            ->assertCreated()->assertJsonPath('htsus_code', '01061991');
        $this->patchJson(route('goods.update', $response->json('id')), ['htsus_code' => '0106.19.9120'])
            ->assertOk()->assertJsonPath('htsus_code', '0106199120');
        $this->patchJson(route('goods.update', $response->json('id')), ['htsus_code' => '010619912'])
            ->assertUnprocessable()->assertJsonValidationErrors('htsus_code');
    }

    public static function invalidCodes(): array
    {
        return [
            ['hs_code', '01012'], ['tn_ved_code', '010121000X'], ['okpd2_code', '1.2'],
            ['cn_code', '123'], ['taric_code', '123'], ['htsus_code', '123'],
            ['schedule_b_code', '123'], ['gtin', '123456789'], ['unspsc_code', 'ABC'],
            ['cas_number', '7647145'], ['eccn_code', 'ZZZZZ'], ['hs_code', 10121],
        ];
    }

    public function test_codes_are_optional_and_empty_relationship_inputs_detach_links(): void
    {
        $response = $this->postJson(route('goods.store'), ['name' => 'Без кодов'])->assertCreated();
        foreach (GoodTradeCodes::FIELDS as $field) {
            $response->assertJsonPath($field, null);
        }

        $good = Good::findOrFail($response->json('id'));
        $good->products()->attach(Product::query()->create(['rus' => 'Рыба']));
        $good->fields()->attach(Field::query()->create(['title' => 'Продукты питания']));
        $this->patchJson(route('goods.update', $good), ['products' => '', 'fields' => '', 'slug' => $good->slug])
            ->assertOk()->assertJsonCount(0, 'products')->assertJsonCount(0, 'fields');
    }

    public function test_external_avatar_urls_are_saved_without_download_and_blank_inputs_preserve_them(): void
    {
        $response = $this->postJson(route('goods.store'), [
            'name' => 'Рыба',
            'avatar_source_url' => 'https://cdn.example.test/fish.jpg',
            'avatar_thumb_source_url' => 'https://cdn.example.test/fish-160.jpg',
        ])->assertCreated()->assertJsonPath('ava_image', 'https://cdn.example.test/fish.jpg')
            ->assertJsonPath('ava_thumb', 'https://cdn.example.test/fish-160.jpg');
        $id = $response->json('id');

        $this->patchJson(route('goods.update', $id), [
            'description' => 'Новое описание', 'avatar_source_url' => '', 'avatar_thumb_source_url' => '',
        ])->assertOk()->assertJsonPath('ava_image', 'https://cdn.example.test/fish.jpg')
            ->assertJsonPath('ava_thumb', 'https://cdn.example.test/fish-160.jpg');
        $this->patchJson(route('goods.update', $id), ['avatar_source_url' => 'https://cdn.example.test/new.jpg'])
            ->assertOk()->assertJsonPath('ava_image', 'https://cdn.example.test/new.jpg')->assertJsonPath('ava_thumb', null);
        $this->getJson(route('goods.index', ['view' => 'table']))->assertOk()
            ->assertJsonPath('data.0.avatar_url', 'https://cdn.example.test/new.jpg');
        $this->patchJson(route('goods.update', $id), ['remove_ava' => true])
            ->assertOk()->assertJsonPath('ava_image', null)->assertJsonPath('ava_thumb', null);
        Http::assertNothingSent();
    }

    public function test_urls_require_http_and_cannot_be_mixed_with_uploads(): void
    {
        foreach (['javascript:alert(1)', 'file:///tmp/avatar.jpg', '//cdn.example.test/a.jpg'] as $url) {
            $this->postJson(route('goods.store'), ['name' => 'Рыба', 'avatar_source_url' => $url])
                ->assertUnprocessable()->assertJsonValidationErrors('avatar_source_url');
        }
        foreach (['avatar_source_url', 'avatar_thumb_source_url'] as $field) {
            $this->postJson(route('goods.store'), [
                'name' => 'Рыба', 'ava_image' => UploadedFile::fake()->image('fish.jpg'),
                $field => 'https://cdn.example.test/fish.jpg',
            ])->assertUnprocessable()->assertJsonValidationErrors('ava_image');
        }
    }

    public function test_long_cdn_urls_survive_create_and_method_spoofed_form_update(): void
    {
        $url = 'https://cdn.example.test/fish.jpg?signature='.str_repeat('a', 400);
        $response = $this->postJson(route('goods.store'), ['name' => 'Рыба', 'avatar_source_url' => $url])
            ->assertCreated()->assertJsonPath('ava_image', $url);

        $this->postJson(route('goods.update', $response->json('id')), [
            '_method' => 'PATCH', 'tn_ved_code' => '0302 11 200 0', 'avatar_source_url' => $url,
        ])->assertOk()->assertJsonPath('tn_ved_code', '0302112000')->assertJsonPath('ava_image', $url);
    }

    public function test_changing_url_query_or_replacing_a_media_avatar_preserves_the_image(): void
    {
        $good = Good::query()->create(['name' => 'Рыба']);
        $path = "goods/{$good->id}/original.jpg";
        $url = 'https://storage.yandexcloud.net/goods-test/'.$path;
        Storage::disk('yandex')->put($path, 'image');
        $good->update(['ava_image' => $url]);

        $this->patchJson(route('goods.update', $good), ['avatar_source_url' => $url.'?v=2'])->assertOk();
        Storage::disk('yandex')->assertExists($path);

        $good->media()->create(['type' => 'image', 'path' => $path, 'url' => $url]);
        $this->patchJson(route('goods.update', $good), ['avatar_source_url' => 'https://cdn.example.test/new.jpg'])->assertOk();
        Storage::disk('yandex')->assertExists($path);
    }

    public function test_replacing_avatar_only_deletes_files_owned_by_this_good(): void
    {
        $good = Good::query()->create(['name' => 'Рыба']);
        $oldPath = "goods/{$good->id}/original.jpg";
        $otherPath = 'goods/99999/thumb.jpg';
        Storage::disk('yandex')->put($oldPath, 'old');
        Storage::disk('yandex')->put($otherPath, 'other');
        $good->update([
            'ava_image' => 'https://storage.yandexcloud.net/goods-test/'.$oldPath,
            'ava_thumb' => 'https://storage.yandexcloud.net/goods-test/'.$otherPath,
        ]);

        $this->patchJson(route('goods.update', $good), ['avatar_source_url' => 'https://cdn.example.test/fish.jpg'])
            ->assertOk()->assertJsonPath('ava_thumb', null);
        Storage::disk('yandex')->assertMissing($oldPath);
        Storage::disk('yandex')->assertExists($otherPath);
    }

    public function test_relation_presence_date_filters_and_search_intersect_without_duplicate_goods(): void
    {
        $category = Category::query()->create(['name' => 'Рыбные консервы']);
        $product = Product::query()->create(['rus' => 'Рыба', 'category_id' => $category->id]);
        $country = Country::query()->create(['name' => 'Россия', 'сodeISO' => 'RU']);
        $field = Field::query()->create(['title' => 'Питание']);
        $vat = VatRate::query()->create(['title' => '10%', 'rate' => 10]);
        $good = Good::query()->create([
            'name' => 'Печень трески', 'country_id' => $country->id, 'vat_rate_id' => $vat->id,
            'is_published' => true, 'ava_thumb' => 'https://cdn.example.test/a.jpg', 'hs_code' => '010121',
        ]);
        $good->forceFill(['created_at' => '2026-10-05 23:59:59'])->save();
        $good->products()->attach($product);
        $good->products()->attach(Product::query()->create(['rus' => 'Рыба другая', 'category_id' => $category->id]));
        $good->fields()->attach($field);
        $industry = Industry::query()->create(['code' => '10', 'title' => 'Производство пищевых продуктов']);
        $classification = EntityClassification::query()->create(['name' => 'Производитель']);
        $good->industries()->attach($industry);
        $good->entityClassifications()->attach($classification);
        Good::query()->create(['name' => 'Печенье', 'is_published' => false]);

        $filters = [
            'category_id' => $category->id, 'product_id' => $product->id, 'country_id' => $country->id,
            'vat_rate_id' => $vat->id, 'field_id' => $field->id, 'has_avatar' => 'true',
            'industry_id' => $industry->id, 'entity_classification_id' => $classification->id,
            'has_trade_codes' => '1', 'created_from' => '2026-10-05', 'created_to' => '2026-10-05',
            'search' => 'печ', 'is_published' => 'true',
        ];
        $this->getJson(route('goods.index', ['view' => 'table', ...$filters]))->assertOk()
            ->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $good->id);
        foreach (array_keys($filters) as $filter) {
            if (in_array($filter, ['created_from', 'created_to', 'search', 'is_published'], true)) {
                continue;
            }
            $this->getJson(route('goods.index', ['view' => 'table', $filter => $filters[$filter]]))
                ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $good->id);
        }
        $this->getJson(route('goods.index', ['created_to' => '2026-10-04']))->assertOk()->assertJsonMissing(['id' => $good->id, 'name' => $good->name]);
    }

    public function test_missing_relation_and_presence_filters_include_empty_strings(): void
    {
        $empty = Good::query()->create(['name' => 'Незаполненный', 'ava_image' => '', 'ava_thumb' => '']);
        $country = Country::query()->create(['name' => 'Россия', 'сodeISO' => 'RU']);
        $vat = VatRate::query()->create(['title' => '10%', 'rate' => 10]);
        $complete = Good::query()->create([
            'name' => 'Заполненный', 'country_id' => $country->id, 'vat_rate_id' => $vat->id,
            'ava_image' => 'https://cdn.example.test/a.jpg', 'gtin' => '01234567',
        ]);
        $category = Category::query()->create(['name' => 'Рыба']);
        $complete->products()->attach(Product::query()->create(['rus' => 'Рыба', 'category_id' => $category->id]));
        $complete->fields()->attach(Field::query()->create(['title' => 'Питание']));
        $complete->industries()->attach(Industry::query()->create(['code' => '10', 'title' => 'Пищевые продукты']));
        $complete->entityClassifications()->attach(EntityClassification::query()->create(['name' => 'Производитель']));

        foreach (['category_id', 'product_id', 'country_id', 'field_id', 'industry_id', 'entity_classification_id', 'vat_rate_id', 'has_avatar', 'has_trade_codes'] as $filter) {
            $value = str_starts_with($filter, 'has_') ? 'false' : 'none';
            $this->getJson(route('goods.index', [$filter => $value]))->assertOk()
                ->assertJsonPath('total', 1)->assertJsonPath('data.0.id', $empty->id);
        }
    }

    public function test_invalid_filters_fail_validation_and_metadata_is_compact(): void
    {
        foreach ([['category_id' => 'x'], ['created_from' => 'bad'], ['has_avatar' => 'perhaps'], ['created_from' => '2026-10-05', 'created_to' => '2026-10-04']] as $filters) {
            $this->getJson(route('goods.index', $filters))->assertUnprocessable();
        }
        $category = Category::query()->create(['name' => 'Рыба']);
        Product::query()->create(['rus' => 'Треска', 'category_id' => $category->id]);
        $this->getJson(route('goods.index', ['view' => 'filters']))->assertOk()
            ->assertJsonStructure(['categories', 'products', 'countries', 'fields', 'industries', 'entity_classifications', 'vat_rates'])
            ->assertJsonPath('categories.0.name', 'Рыба')->assertJsonPath('products.0.category_id', $category->id)
            ->assertJsonMissingPath('products.0.manufacturers')->assertJsonMissingPath('products.0.category')
            ->assertJsonMissingPath('categories.0.description');
    }
}
