<?php

namespace Tests\Feature;

use App\Models\Currency;
use App\Models\Good;
use App\Models\GoodPriceTypeValue;
use App\Models\GoodSeo;
use App\Models\PriceType;
use App\Models\Product;
use App\Services\Catalog\PublicClassPage;
use App\Services\Seo\ClassPageHtmlVerifier;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Inertia\Ssr\SsrState;
use Tests\TestCase;

/** Run after npm build with the actual bootstrap/ssr/ssr.js server listening. */
class ClassPageSsrTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('RUN_CLASS_SSR_TEST') !== '1') {
            $this->markTestSkipped('Set RUN_CLASS_SSR_TEST=1 and start the built Inertia SSR server.');
        }
        config()->set([
            'inertia.ssr.enabled' => true,
            'inertia.ssr.throw_on_error' => true,
            'inertia.ssr.url' => 'http://127.0.0.1:13714',
            'services.yandex_metrica.counter_id' => null,
        ]);
        Http::preventStrayRequests();
        Http::allowStrayRequests(['http://127.0.0.1:13714/*']);
    }

    public function test_real_raw_html_contains_article_current_catalog_and_metadata(): void
    {
        $product = Product::forceCreate(['id' => 124, 'rus' => 'Скумбрия', 'is_published' => true]);
        $source = Product::forceCreate(['id' => 201, 'rus' => 'Скумбрия замороженная', 'is_published' => true]);
        $good = Good::forceCreate(['id' => 75, 'name' => 'Скумбрия атлантическая 600+ неразделанная, Фарерские острова — тестовая партия',
            'slug' => 'fresh-mackerel-ssr', 'is_published' => true, 'denominator' => 20,
            'ava_thumb' => '/class-assets/mackerel/mackerel-hero.jpg']);
        $good->products()->attach($source);
        GoodSeo::create(['good_id' => $good->id, 'availability_status' => 'in_stock']);
        $currency = Currency::query()->where('code', 'RUB')->first()
            ?: Currency::forceCreate(['code' => 'RUB', 'name' => 'Рубль']);
        $priceType = PriceType::create(['name' => 'Розничная', 'code' => 'retail-ssr', 'is_public' => true,
            'is_active' => true, 'currency_id' => $currency->id]);
        GoodPriceTypeValue::create(['good_id' => $good->id, 'price_type_id' => $priceType->id,
            'currency_id' => $currency->id, 'price_gross' => 359.90, 'is_published' => true]);
        foreach ([104 => 'Скумбрия 500+ неразделанная Перу — тестовая партия',
            172 => 'Скумбрия японская 500–700 Китай — тестовая партия',
            173 => 'Скумбрия 300–500 неразделанная Китай — тестовая партия без цены'] as $id => $name) {
            $item = Good::forceCreate(['id' => $id, 'name' => $name, 'slug' => 'ssr-mackerel-'.$id,
                'is_published' => true, 'denominator' => $id === 173 ? null : 10,
                'ava_thumb' => $id === 173 ? null : '/class-assets/mackerel/mackerel-hero.jpg']);
            $item->products()->attach($source);
        }
        $hidden = Good::forceCreate(['id' => 95, 'name' => 'Скрытая партия SSR', 'slug' => 'hidden-mackerel-ssr', 'is_published' => false]);
        $hidden->products()->attach($source);

        $response = $this->rawPage('/p/124');
        app(ClassPageHtmlVerifier::class)->verify($response->getContent(), app(PublicClassPage::class)->for($product));
        $response->assertDontSee('hidden-mackerel-ssr');
        $this->capture($response, 'configured-124');
        $this->artisan('app:check-class-pages')->assertSuccessful();

        $good->update(['slug' => 'latest-mackerel-ssr']);
        $response = $this->rawPage('/p/124');
        app(ClassPageHtmlVerifier::class)->verify($response->getContent(), app(PublicClassPage::class)->for($product));
        $response->assertDontSee('fresh-mackerel-ssr');
        $this->capture($response, 'changed-slug-124');

        $good->update(['is_published' => false]);
        $response = $this->rawPage('/p/124');
        app(ClassPageHtmlVerifier::class)->verify($response->getContent(), app(PublicClassPage::class)->for($product));
        $response->assertDontSee('latest-mackerel-ssr');
        $this->capture($response, 'hidden-75');
        Good::whereIn('id', [104, 172, 173])->update(['is_published' => false]);
        $response = $this->rawPage('/p/124');
        app(ClassPageHtmlVerifier::class)->verify($response->getContent(), app(PublicClassPage::class)->for($product));
        $this->capture($response, 'empty-124');
    }

    public function test_other_product_classes_still_render_without_editorial_guide(): void
    {
        $product = Product::forceCreate(['id' => 201, 'rus' => 'Обычный класс SSR', 'is_published' => true]);
        $response = $this->rawPage('/p/'.$product->id)->assertSee('Обычный класс SSR')
            ->assertDontSee('data-class-guide=', false);
        $this->capture($response, 'generic-201');
    }

    public function test_client_rendered_pages_keep_one_managed_fallback_title(): void
    {
        config()->set('inertia.ssr.enabled', false);
        $product = Product::forceCreate(['id' => 201, 'rus' => 'Обычный класс', 'is_published' => true]);
        $response = $this->rawPage('/p/'.$product->id);
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent(), LIBXML_NONET);
        $titles = (new DOMXPath($document))->query('//head/title');
        $this->assertSame(1, $titles->length);
        $this->assertSame(config('app.name'), trim($titles->item(0)->textContent));
        $this->assertTrue($titles->item(0)->hasAttribute('data-inertia'));
    }

    private function rawPage(string $path): TestResponse
    {
        $this->app->forgetInstance(SsrState::class);

        return $this->get($path)->assertOk();
    }

    private function capture(TestResponse $response, string $name): void
    {
        $directory = getenv('CLASS_SSR_CAPTURE_DIR');
        if (! $directory) {
            return;
        }
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        file_put_contents($directory.'/'.$name.'.html', $response->getContent());
        file_put_contents($directory.'/'.$name.'.json', json_encode($response->viewData('page'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }
}
