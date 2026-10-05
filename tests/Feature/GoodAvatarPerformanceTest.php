<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Good;
use App\Models\Product;
use App\Models\User;
use App\Services\Goods\GoodAvatarImages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GoodAvatarPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set([
            'filesystems.disks.yandex.bucket' => 'goods-test',
            'filesystems.disks.yandex.url' => 'https://storage.yandexcloud.net/goods-test',
            'goods-media.avatar_cdn_url' => null,
        ]);
        $this->actingAs(User::factory()->create(['type' => 'employee', 'status' => 'active']));
    }

    public function test_table_loads_thumbnail_metadata_and_only_relations_used_by_the_table(): void
    {
        $good = Good::query()->create([
            'name' => 'Арахис',
            'ava_image' => 'https://storage.yandexcloud.net/goods-test/goods/1/original.jpg',
            'ava_thumb' => 'https://storage.yandexcloud.net/goods-test/goods/1/thumb.jpg',
            'description' => 'Описание для редактирования',
        ]);
        $category = Category::query()->create(['name' => 'Орехи']);
        $product = Product::query()->create(['rus' => 'Арахис', 'category_id' => $category->id]);
        $good->products()->attach($product);
        config()->set('goods-media.avatar_cdn_url', 'https://images.example.test/');
        Storage::shouldReceive('disk')->never();
        DB::enableQueryLog();

        $this->getJson(route('goods.index', ['view' => 'table']))
            ->assertOk()
            ->assertJsonPath('data.0.avatar_url', 'https://images.example.test/goods/1/thumb.jpg')
            ->assertJsonPath('data.0.description', 'Описание для редактирования')
            ->assertJsonPath('data.0.products.0.category.name', 'Орехи')
            ->assertJsonMissingPath('data.0.products.0.manufacturers')
            ->assertJsonMissingPath('data.0.media')
            ->assertJsonMissingPath('data.0.seo')
            ->assertJsonMissingPath('data.0.price_type_values')
            ->assertJsonMissingPath('data.0.industries')
            ->assertJsonMissingPath('data.0.entity_classifications');

        $queries = implode("\n", array_column(DB::getQueryLog(), 'query'));
        DB::disableQueryLog();
        foreach (['good_media', 'good_seos', 'good_price_type_values', 'manufacturers'] as $table) {
            $this->assertStringNotContainsString($table, $queries);
        }
    }

    public function test_default_list_keeps_its_existing_relations_for_other_consumers(): void
    {
        Good::query()->create(['name' => 'Арахис']);

        $this->getJson(route('goods.index'))
            ->assertOk()
            ->assertJsonPath('data.0.media', [])
            ->assertJsonPath('data.0.price_type_values', [])
            ->assertJsonPath('data.0.industries', [])
            ->assertJsonPath('data.0.entity_classifications', []);
    }

    public function test_table_pagination_filters_and_original_avatar_fallback_are_preserved(): void
    {
        Good::query()->create(['name' => 'Арахис А', 'is_published' => true]);
        $good = Good::query()->create([
            'name' => 'Арахис Б',
            'is_published' => true,
            'ava_image' => 'https://external.example.test/image.jpg',
        ]);
        Good::query()->create(['name' => 'Арахис В', 'is_published' => false]);
        Good::query()->create(['name' => 'Грецкий орех', 'is_published' => true]);

        $this->getJson(route('goods.index', [
            'view' => 'table', 'search' => 'Арахис', 'is_published' => 'true', 'per_page' => 1, 'page' => 2,
        ]))->assertOk()->assertJsonPath('total', 2)->assertJsonPath('data.0.id', $good->id)
            ->assertJsonPath('data.0.avatar_url', 'https://external.example.test/image.jpg');

        $this->getJson(route('goods.index', ['view' => 'table', 'per_page' => 9999]))
            ->assertOk()->assertJsonPath('per_page', 100);
    }

    public function test_backfill_creates_a_small_thumbnail_and_force_refreshes_legacy_thumbnails(): void
    {
        $disk = Storage::fake('yandex');
        $images = app(GoodAvatarImages::class);
        $good = Good::query()->create(['name' => 'Арахис']);
        $path = "goods/{$good->id}/original.jpg";
        $original = UploadedFile::fake()->image('original.jpg', 1200, 800);
        $disk->put($path, file_get_contents($original->getRealPath()));
        $good->update(['ava_image' => $images->publicUrl($path)]);

        $this->artisan('goods:avatar-thumbnails')->assertSuccessful();

        $thumbnail = $good->fresh()->ava_thumb;
        $thumbPath = $images->storagePath($thumbnail);
        $disk->assertExists($thumbPath);
        $dimensions = getimagesizefromstring($disk->get($thumbPath));
        $this->assertSame([160, 160], array_slice($dimensions, 0, 2));
        $this->assertSame('image/jpeg', $dimensions['mime']);
        $this->assertNotSame($good->ava_image, $thumbnail);
        $this->assertLessThan(strlen($disk->get($path)), strlen($disk->get($thumbPath)));

        $good->update(['ava_thumb' => $images->publicUrl("goods/{$good->id}/old_thumb.jpg")]);
        $this->artisan('goods:avatar-thumbnails')->assertSuccessful();
        $this->assertStringEndsWith('/old_thumb.jpg', $good->fresh()->ava_thumb);
        $this->artisan('goods:avatar-thumbnails', ['--force' => true, '--good' => [$good->id]])->assertSuccessful();
        $this->assertSame($thumbnail, $good->fresh()->ava_thumb);
        $disk->assertExists($path);
    }

    public function test_backfill_does_not_fetch_external_urls_or_change_existing_data_on_failure(): void
    {
        $good = Good::query()->create([
            'name' => 'Арахис',
            'ava_image' => 'https://external.example.test/original.jpg',
        ]);
        Storage::shouldReceive('disk')->never();

        $this->artisan('goods:avatar-thumbnails', ['--good' => [$good->id]])->assertFailed();

        $this->assertNull($good->fresh()->ava_thumb);
        $this->assertSame('https://external.example.test/original.jpg', $good->fresh()->ava_image);
    }
}
