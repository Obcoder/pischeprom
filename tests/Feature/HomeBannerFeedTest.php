<?php

namespace Tests\Feature;

use App\Models\HomeBanner;
use App\Models\HomeBannerSetting;
use App\Models\User;
use App\Rules\HomeBannerUrl;
use App\Services\HomeBanners\HomeBannerFeedService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HomeBannerFeedTest extends TestCase
{
    private const MIGRATION = 'migrations/2026_10_05_180000_add_six_slot_home_banner_feed.php';

    private string $originalTimezone;

    protected function setUp(): void
    {
        parent::setUp();

        // This suite never migrates, queries or changes the developer database.
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'app.timezone' => 'UTC',
            'cache.default' => 'array',
            'session.driver' => 'array',
        ]);
        $this->originalTimezone = date_default_timezone_get();
        date_default_timezone_set('UTC');
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->boolean('is_published')->default(true);
        });
        Schema::create('goods', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug');
            $table->string('ava_image')->nullable();
            $table->string('ava_thumb')->nullable();
            $table->boolean('is_published')->default(true);
        });
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('rus');
            $table->string('eng')->nullable();
            $table->foreignId('category_id')->nullable()->constrained();
            $table->boolean('is_published')->default(true);
        });

        (require database_path('migrations/2026_06_27_193000_create_home_banners_table.php'))->up();
        DB::table('home_banners')->delete();
        (require database_path(self::MIGRATION))->up();
        $this->travelTo(now('UTC')->setDate(2026, 10, 5)->setTime(12, 0));
    }

    protected function tearDown(): void
    {
        $this->travelBack();
        date_default_timezone_set($this->originalTimezone);
        DB::purge('sqlite');

        parent::tearDown();
    }

    public function test_migration_assigns_first_six_slots_and_preserves_all_existing_campaigns_and_dates(): void
    {
        $migration = require database_path(self::MIGRATION);
        $migration->down();
        $category = DB::table('categories')->insertGetId(['name' => 'Категория', 'slug' => 'category']);
        $ids = [];
        foreach ([80, 70, 60, 50, 40, 30, 20, 10] as $index => $priority) {
            $ids[] = DB::table('home_banners')->insertGetId([
                'title' => "Сохранённый баннер {$index}",
                'image_url' => "/assets/banner-{$index}.webp",
                'mobile_image_url' => "/assets/mobile-{$index}.webp",
                'category_id' => $category,
                'sort_order' => $priority,
                'size' => 'wide',
                'starts_at' => '2026-12-01 12:00:00',
                'ends_at' => '2026-12-20 14:00:00',
            ]);
        }
        $original = DB::table('home_banners')->orderBy('id')->get()->toArray();

        $migration->up();

        $this->assertSame(8, HomeBanner::query()->count());
        foreach ($original as $row) {
            $this->assertDatabaseHas('home_banners', (array) $row);
        }
        $this->assertSame(array_reverse(array_slice($ids, 2)), HomeBanner::query()->whereNotNull('slot_number')->orderBy('slot_number')->pluck('id')->all());
        $this->assertSame([1, 2, 3, 4, 5, 6], HomeBanner::query()->whereNotNull('slot_number')->orderBy('slot_number')->pluck('slot_number')->all());
        $this->assertSame(2, HomeBanner::query()->whereNull('slot_number')->count());
        $this->assertSame(HomeBannerSetting::DEFAULTS, HomeBannerSetting::singleton()->settingsPayload());

        $migration->down();
        $this->assertFalse(Schema::hasColumn('home_banners', 'slot_number'));
        $this->assertFalse(Schema::hasTable('home_banner_settings'));
        foreach ($original as $row) {
            $this->assertDatabaseHas('home_banners', (array) $row);
        }
    }

    public function test_settings_and_banner_apis_require_active_staff_for_reads_and_writes(): void
    {
        $this->getJson('/api/home-banner-settings')->assertUnauthorized();
        $this->patchJson('/api/home-banner-settings', ['enabled' => false])->assertUnauthorized();
        $this->getJson('/api/home-banners')->assertUnauthorized();
        $this->postJson('/api/home-banners', ['title' => 'Banner'])->assertUnauthorized();

        $this->signIn('customer');
        $this->getJson('/api/home-banner-settings')->assertForbidden();
        $this->patchJson('/api/home-banner-settings', ['enabled' => false])->assertForbidden();
        $this->getJson('/api/home-banners')->assertForbidden();
        $this->postJson('/api/home-banners', ['title' => 'Banner'])->assertForbidden();

        $this->signIn('employee', 'blocked');
        $this->getJson('/api/home-banner-settings')->assertForbidden();
        $this->signIn();
        $this->getJson('/api/home-banner-settings')->assertOk()->assertJsonPath('specification.slot_count', 6);
        $this->postJson('/api/home-banners', ['title' => 'Draft'])->assertCreated()->assertJsonPath('data.published_status', 'draft');
    }

    public function test_settings_validate_dimensions_and_complete_mobile_slot_order_and_persist_partial_updates(): void
    {
        $this->signIn();
        $this->patchJson('/api/home-banner-settings', [
            'desktop_height' => 200,
            'gap' => 2,
            'mobile_columns' => 3,
            'mobile_order' => [1, 1, 3, 4, 5, 6],
        ])->assertUnprocessable()->assertJsonValidationErrors(['desktop_height', 'gap', 'mobile_columns', 'mobile_order.0']);
        $this->patchJson('/api/home-banner-settings', ['mobile_order' => [1, 2, 3]])
            ->assertUnprocessable()->assertJsonValidationErrors('mobile_order');

        $this->patchJson('/api/home-banner-settings', [
            'desktop_height' => 88,
            'mobile_layout' => 'grid',
            'mobile_columns' => 1,
            'mobile_order' => [6, 5, 4, 3, 2, 1],
        ])->assertOk()->assertJsonPath('data.desktop_height', 88)->assertJsonPath('data.mobile_layout', 'grid');
        $this->patchJson('/api/home-banner-settings', ['mobile_height' => 72])
            ->assertOk()->assertJsonPath('data.desktop_height', 88)->assertJsonPath('data.mobile_order', [6, 5, 4, 3, 2, 1]);
        $this->assertSame(1, HomeBannerSetting::query()->count());
        $this->assertSame(72, HomeBannerSetting::singleton()->mobile_height);
    }

    public function test_feed_resolves_six_fixed_slots_per_device_and_filters_drafts_future_expired_and_unassigned(): void
    {
        $first = $this->banner(['slot_number' => 1, 'sort_order' => 100]);
        $winner = $this->banner(['slot_number' => 1, 'sort_order' => 10, 'show_on_mobile' => false]);
        $this->banner(['slot_number' => 1, 'sort_order' => 10, 'show_on_mobile' => false]);
        $startBoundary = $this->banner(['slot_number' => 2, 'starts_at' => now(), 'show_on_desktop' => false]);
        $this->banner(['slot_number' => 3, 'starts_at' => now()->addSecond()]);
        $this->banner(['slot_number' => 4, 'ends_at' => now()]);
        $this->banner(['slot_number' => 5, 'is_published' => false]);
        $this->banner(['slot_number' => null]);
        $this->banner(['slot_number' => 6, 'show_on_desktop' => false, 'show_on_mobile' => false]);
        HomeBannerSetting::singleton()->update(['mobile_order' => [2, 1, 6, 5, 4, 3]]);

        $feed = app(HomeBannerFeedService::class)->build();

        $this->assertCount(6, $feed['desktop']);
        $this->assertCount(6, $feed['mobile']);
        $this->assertSame($winner->id, $feed['desktop'][0]['id']);
        $this->assertSame($first->id, $feed['mobile'][0]['id']);
        $this->assertSame($startBoundary->id, $feed['mobile'][1]['id']);
        $this->assertSame([2, 1, 6, 5, 4, 3], $feed['settings']['mobile_order']);
        $this->assertSame([null, null, null, null, null], array_slice($feed['desktop'], 1));
        $this->assertSame([null, null, null, null], array_slice($feed['mobile'], 2));
        $this->assertArrayNotHasKey('starts_at', $feed['desktop'][0]);
        $this->assertArrayNotHasKey('is_published', $feed['desktop'][0]);
        $this->assertArrayNotHasKey('created_at', $feed['desktop'][0]);

        HomeBannerSetting::singleton()->update(['mobile_enabled' => false]);
        $this->assertSame(array_fill(0, 6, null), app(HomeBannerFeedService::class)->build()['mobile']);
        HomeBannerSetting::singleton()->update(['enabled' => false]);
        $this->assertSame(array_fill(0, 6, null), app(HomeBannerFeedService::class)->build()['desktop']);
    }

    public function test_published_campaigns_reject_overlapping_periods_but_allow_boundary_and_device_disjoint_campaigns(): void
    {
        $this->signIn();
        $payload = $this->publicationPayload([
            'starts_at' => '2026-10-06T09:00:00+03:00',
            'ends_at' => '2026-10-07T09:00:00+03:00',
            'show_on_mobile' => false,
        ]);
        $id = $this->postJson('/api/home-banners', $payload)->assertCreated()->json('data.id');

        $this->postJson('/api/home-banners', $this->publicationPayload([
            'starts_at' => '2026-10-06T10:00:00+03:00',
            'ends_at' => '2026-10-06T11:00:00+03:00',
        ]))->assertUnprocessable()->assertJsonValidationErrors('slot_number');
        $this->postJson('/api/home-banners', $this->publicationPayload([
            'starts_at' => '2026-10-07T09:00:00+03:00',
            'ends_at' => '2026-10-08T09:00:00+03:00',
        ]))->assertCreated();
        $this->postJson('/api/home-banners', array_replace($payload, [
            'show_on_desktop' => false, 'show_on_mobile' => true,
        ]))->assertCreated();
        $draft = $this->postJson('/api/home-banners', array_replace($payload, ['is_published' => false]))
            ->assertCreated()->json('data.id');
        $this->patchJson("/api/home-banners/{$draft}", ['is_published' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('slot_number');
        $this->patchJson("/api/home-banners/{$id}", ['show_on_mobile' => true])
            ->assertUnprocessable()->assertJsonValidationErrors('slot_number');
        $this->patchJson("/api/home-banners/{$id}", ['title' => 'Переименованный баннер'])
            ->assertOk()->assertJsonPath('data.title', 'Переименованный баннер');
    }

    public function test_infinite_periods_and_partial_schedule_changes_use_existing_dates_and_devices(): void
    {
        $this->signIn();
        $this->postJson('/api/home-banners', $this->publicationPayload())->assertCreated();
        $this->postJson('/api/home-banners', $this->publicationPayload([
            'starts_at' => '2030-01-01T00:00:00Z',
            'ends_at' => '2030-02-01T00:00:00Z',
        ]))->assertUnprocessable()->assertJsonValidationErrors('slot_number');

        $id = $this->postJson('/api/home-banners', $this->publicationPayload([
            'slot_number' => 2,
            'starts_at' => '2026-10-06T09:00:00+03:00',
            'ends_at' => '2026-10-07T09:00:00+03:00',
            'show_on_mobile' => false,
            'sort_order' => 12,
        ]))->assertCreated()->json('data.id');
        $this->patchJson("/api/home-banners/{$id}", ['ends_at' => '2026-10-06T09:00:00+03:00'])
            ->assertUnprocessable()->assertJsonValidationErrors('ends_at');
        $this->patchJson("/api/home-banners/{$id}", ['starts_at' => '2026-10-08T09:00:00+03:00'])
            ->assertUnprocessable()->assertJsonValidationErrors('ends_at');
        $this->patchJson("/api/home-banners/{$id}", ['alt_text' => 'Новое описание'])
            ->assertOk()->assertJsonPath('data.show_on_mobile', false)->assertJsonPath('data.sort_order', 12)
            ->assertJsonPath('data.starts_at', '2026-10-06T06:00:00.000000Z');
        $this->patchJson("/api/home-banners/{$id}", ['ends_at' => null])
            ->assertOk()->assertJsonPath('data.ends_at', null);
        $this->deleteJson("/api/home-banners/{$id}")->assertOk();
        $this->assertDatabaseMissing('home_banners', ['id' => $id]);

        $this->postJson('/api/home-banners', $this->publicationPayload([
            'slot_number' => 3, 'ends_at' => '2026-10-06T09:00:00+03:00',
        ]))->assertCreated();
        $this->postJson('/api/home-banners', $this->publicationPayload([
            'slot_number' => 3, 'starts_at' => '2026-10-06T09:00:00+03:00',
        ]))->assertCreated();
    }

    public function test_editor_interprets_unqualified_dates_as_moscow_time_and_serializes_utc(): void
    {
        $this->signIn();
        $this->postJson('/api/home-banners', $this->publicationPayload([
            'starts_at' => '2026-10-06T09:00',
            'ends_at' => '2026-10-07T09:00',
        ]))->assertCreated()
            ->assertJsonPath('data.starts_at', '2026-10-06T06:00:00.000000Z')
            ->assertJsonPath('data.ends_at', '2026-10-07T06:00:00.000000Z');
    }

    public function test_dates_remain_correct_when_application_and_legacy_database_use_moscow_timezone(): void
    {
        config()->set('app.timezone', 'Europe/Moscow');
        date_default_timezone_set('Europe/Moscow');
        $this->signIn();
        $id = $this->postJson('/api/home-banners', $this->publicationPayload([
            'starts_at' => '2026-10-06T09:00:00+03:00',
            'ends_at' => '2026-10-07T06:00:00Z',
        ]))->assertCreated()
            ->assertJsonPath('data.starts_at', '2026-10-06T06:00:00.000000Z')
            ->assertJsonPath('data.ends_at', '2026-10-07T06:00:00.000000Z')->json('data.id');
        $this->assertDatabaseHas('home_banners', [
            'id' => $id, 'starts_at' => '2026-10-06 09:00:00', 'ends_at' => '2026-10-07 09:00:00',
        ]);
        $this->travelTo(now('Europe/Moscow')->setDate(2026, 10, 6)->setTime(9, 0));
        $this->assertSame($id, app(HomeBannerFeedService::class)->build()['desktop'][0]['id']);
        $this->travelTo(now('Europe/Moscow')->setDate(2026, 10, 7)->setTime(9, 0));
        $this->assertNull(app(HomeBannerFeedService::class)->build()['desktop'][0]);
    }

    public function test_admin_status_distinguishes_draft_unassigned_hidden_scheduled_expired_and_active(): void
    {
        foreach ([
            'draft' => ['is_published' => false],
            'unassigned' => ['slot_number' => null],
            'hidden' => ['show_on_desktop' => false, 'show_on_mobile' => false],
            'scheduled' => ['starts_at' => now()->addDay()],
            'expired' => ['ends_at' => now()],
            'active' => ['starts_at' => now()->subDay(), 'ends_at' => now()->addDay()],
        ] as $status => $overrides) {
            $this->assertSame($status, $this->banner($overrides)->published_status);
        }
    }

    public function test_links_images_and_alignment_are_validated_and_legacy_unsafe_urls_never_reach_public_feed(): void
    {
        $this->signIn();
        foreach (['javascript:alert(1)', 'data:image/png;base64,xx', '//example.test/image', '/\\example.test/image'] as $url) {
            $this->postJson('/api/home-banners', ['title' => 'Unsafe', 'image_url' => $url, 'cta_url' => $url])
                ->assertUnprocessable()->assertJsonValidationErrors(['image_url', 'cta_url']);
        }
        $this->postJson('/api/home-banners', [
            'title' => 'Bad settings', 'slot_number' => 7, 'image_fit' => 'stretch', 'text_align' => 'justify',
        ])->assertUnprocessable()->assertJsonValidationErrors(['slot_number', 'image_fit', 'text_align']);
        $this->postJson('/api/home-banners', $this->publicationPayload(['content_mode' => 'image', 'image_url' => null]))
            ->assertUnprocessable()->assertJsonValidationErrors('image_url');

        $this->banner(['slot_number' => 1, 'image_url' => 'data:image/png;base64,xx', 'cta_url' => 'javascript:alert(1)']);
        $feed = app(HomeBannerFeedService::class)->build();
        $this->assertNull($feed['desktop'][0]['image_url']);
        $this->assertNull($feed['desktop'][0]['cta_url']);
    }

    public function test_public_feed_includes_only_published_safe_related_catalog_fields(): void
    {
        $category = DB::table('categories')->insertGetId(['name' => 'Категория', 'slug' => 'category']);
        $product = DB::table('products')->insertGetId(['rus' => 'Продукт', 'category_id' => $category]);
        $good = DB::table('goods')->insertGetId(['name' => 'Товар', 'slug' => 'good', 'ava_image' => '/good.webp']);
        $banner = $this->banner(['slot_number' => 1, 'category_id' => $category, 'product_id' => $product, 'good_id' => $good]);
        $this->signIn();
        $this->patchJson("/api/home-banners/{$banner->id}", ['title' => 'Новое название'])
            ->assertOk()->assertJsonPath('data.good_id', $good)->assertJsonPath('data.product_id', $product)
            ->assertJsonPath('data.category_id', $category);

        $feed = app(HomeBannerFeedService::class)->build();
        $this->assertSame('good', $feed['desktop'][0]['good']['slug']);
        $this->assertSame('category', $feed['desktop'][0]['product']['category']['slug']);
        $this->assertArrayNotHasKey('manufacturers', $feed['desktop'][0]['product']);

        DB::table('goods')->where('id', $good)->update(['is_published' => false]);
        DB::table('products')->where('id', $product)->update(['is_published' => false]);
        DB::table('categories')->where('id', $category)->update(['is_published' => false]);
        $feed = app(HomeBannerFeedService::class)->build();
        $this->assertNull($feed['desktop'][0]['good']);
        $this->assertNull($feed['desktop'][0]['product']);
        $this->assertNull($feed['desktop'][0]['category']);
    }

    public function test_asset_browser_encodes_legacy_unicode_and_space_paths_without_changing_the_storage_key(): void
    {
        $this->signIn();
        Storage::fake('yandex');
        config()->set('filesystems.disks.yandex.url', 'https://storage.yandexcloud.net/test');
        $folder = 'Папка с пробелом';
        $path = "banners/{$folder}/Баннер лецитин.webp";
        Storage::disk('yandex')->put($path, 'existing banner bytes');

        $response = $this->getJson('/api/home-banner-assets?folder='.rawurlencode($folder))
            ->assertOk()->assertJsonCount(1, 'files')->assertJsonPath('files.0.path', $path);
        $url = $response->json('files.0.url');
        $expected = 'https://storage.yandexcloud.net/test/banners/'
            .'%D0%9F%D0%B0%D0%BF%D0%BA%D0%B0%20%D1%81%20%D0%BF%D1%80%D0%BE%D0%B1%D0%B5%D0%BB%D0%BE%D0%BC/'
            .'%D0%91%D0%B0%D0%BD%D0%BD%D0%B5%D1%80%20%D0%BB%D0%B5%D1%86%D0%B8%D1%82%D0%B8%D0%BD.webp';

        $this->assertSame($expected, $url);
        $this->assertTrue(HomeBannerUrl::isSafe($url));
        $this->assertSame([$path], Storage::disk('yandex')->allFiles('banners'));
        $this->assertSame('existing banner bytes', Storage::disk('yandex')->get($path));
        $this->postJson('/api/home-banners', $this->publicationPayload([
            'content_mode' => 'image', 'image_url' => $url,
        ]))->assertCreated()->assertJsonPath('data.image_url', $expected);
    }

    private function signIn(string $type = 'employee', string $status = 'active'): void
    {
        $user = new User;
        $user->forceFill(['id' => 1, 'name' => 'Banner manager', 'email' => 'banner@example.test', 'type' => $type, 'status' => $status]);
        $this->actingAs($user);
    }

    private function banner(array $overrides = []): HomeBanner
    {
        return HomeBanner::query()->create(array_replace($this->publicationPayload(), $overrides));
    }

    private function publicationPayload(array $overrides = []): array
    {
        return array_replace([
            'title' => 'Баннер',
            'slot_number' => 1,
            'content_mode' => 'text',
            'is_published' => true,
            'show_on_desktop' => true,
            'show_on_mobile' => true,
            'cta_url' => '/g',
        ], $overrides);
    }
}
