<?php

namespace Tests\Feature;

use App\Models\CatalogLanding;
use App\Models\CatalogNode;
use App\Models\Currency;
use App\Models\Good;
use App\Models\GoodPriceTypeValue;
use App\Models\GoodSeo;
use App\Models\PriceType;
use App\Models\Product;
use App\Models\User;
use App\Services\Catalog\CatalogLandingTemplates;
use App\Services\Catalog\PublicCatalogService;
use App\Services\Catalog\PublicClassPage;
use App\Services\Seo\ClassPageHtmlVerifier;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Inertia\Ssr\SsrState;
use Tests\Support\CreatesClassCatalog;
use Tests\TestCase;

/** Run after npm build with the actual bootstrap/ssr/ssr.js server listening. */
class ClassPageSsrTest extends TestCase
{
    use CreatesClassCatalog;
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
        $this->createClassCatalog($product);
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

        $this->get('/p/124')->assertStatus(301)->assertRedirect($this->classUrl());
        $this->get('/catalog/160/skumbriia')->assertStatus(301)->assertRedirect($this->classUrl());
        $response = $this->rawPage($this->classUrl());
        app(ClassPageHtmlVerifier::class)->verify($response->getContent(), app(PublicClassPage::class)->for($product));
        $response->assertDontSee('hidden-mackerel-ssr');
        $this->assertHeaderFishUrl($response);
        $this->capture($response, 'configured-124');
        $this->artisan('app:check-class-pages')->assertSuccessful();

        $home = $this->rawPage('/');
        $this->assertHeaderFishUrl($home);
        $this->capture($home, 'home');
        $fish = $this->rawPage('/catalog/ryba');
        $this->assertHeaderFishUrl($fish);
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$fish->getContent(), LIBXML_NONET);
        $links = (new DOMXPath($document))->query('//body//a[@href="'.$this->classUrl().'"]');
        $this->assertGreaterThan(0, $links->length, 'The fish catalog must link to the class canonical in actual HTML.');
        $this->capture($fish, 'fish');

        $good->update(['slug' => 'latest-mackerel-ssr']);
        $response = $this->rawPage($this->classUrl());
        app(ClassPageHtmlVerifier::class)->verify($response->getContent(), app(PublicClassPage::class)->for($product));
        $response->assertDontSee('fresh-mackerel-ssr');
        $this->capture($response, 'changed-slug-124');

        $good->update(['is_published' => false]);
        $response = $this->rawPage($this->classUrl());
        app(ClassPageHtmlVerifier::class)->verify($response->getContent(), app(PublicClassPage::class)->for($product));
        $response->assertDontSee('latest-mackerel-ssr');
        $this->capture($response, 'hidden-75');
        Good::whereIn('id', [104, 172, 173])->update(['is_published' => false]);
        $response = $this->rawPage($this->classUrl());
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

    public function test_database_landing_renders_the_complete_migrated_article_with_current_metadata_and_goods(): void
    {
        [$product, $node, $landing, $good] = $this->migratedLanding();
        $draft = $landing->draft_content;
        $draft['hero']['title'] = 'Непубличный черновик скумбрии';
        $landing->update(['draft_content' => $draft]);

        $response = $this->rawPage($this->classUrl());
        $page = app(PublicClassPage::class)->for($product);
        app(ClassPageHtmlVerifier::class)->verify($response->getContent(), $page);
        $xpath = $this->xpath($response->getContent());
        $article = $xpath->query('//body//*[@data-guide-article="editorial"]')->item(0);
        $this->assertNotNull($article);
        $articleText = $this->normalizedText($article->ownerDocument->saveHTML($article));
        $prototype = file_get_contents(base_path('docs/prototypes/pischeprom-mackerel-prototype/dist/index.html'));
        $start = strpos($prototype, '<section id="guide"');
        $end = strpos($prototype, '<section id="contact"');
        $prototypeArticle = $this->xpath(substr($prototype, $start, $end - $start));
        foreach ($prototypeArticle->query('//p') as $paragraph) {
            $expected = $this->normalizedText($paragraph->ownerDocument->saveHTML($paragraph));
            $this->assertStringContainsString($expected, $articleText, 'Lost migrated article paragraph: '.$expected);
        }
        $this->assertSame(8, $xpath->query('.//details', $article)->length);
        $this->assertSame(3, $xpath->query('.//a[@data-inline-good-link]', $article)->length);
        $this->assertSame(2, $xpath->query('.//a[@data-good-id="75" and @data-inline-good-link]', $article)->length);
        $this->assertSame(1, $xpath->query('.//a[@data-good-id="104" and @data-inline-good-link]', $article)->length);
        $sources = $xpath->query('//body//*[@id="sources"]')->item(0)->textContent;
        foreach (['Petar Milošević', 'Jocian', 'CC BY-SA 4.0', 'CC BY-SA 3.0'] as $credit) {
            $this->assertStringContainsString($credit, $sources);
        }
        $this->assertSame('Скумбрия из карточки — SSR', $xpath->evaluate('string(//head/title)'));
        $this->assertSame('Описание из карточки для SSR', $xpath->evaluate('string(//head/meta[@name="description"]/@content)'));
        $this->assertSame('Скумбрия', $xpath->evaluate('string(//body//*[@data-class-guide]//h1)'));
        $response->assertDontSee('Непубличный черновик скумбрии');
        $this->capture($response, 'database-migrated-124');

        $node->update(['meta_title' => 'Изменённый Title карточки — SSR', 'meta_description' => 'Изменённый Description карточки']);
        $good->update(['slug' => 'database-current-mackerel']);
        $response = $this->rawPage($this->classUrl());
        app(ClassPageHtmlVerifier::class)->verify($response->getContent(), app(PublicClassPage::class)->for($product));
        $xpath = $this->xpath($response->getContent());
        $this->assertSame('Изменённый Title карточки — SSR', $xpath->evaluate('string(//head/title)'));
        $this->assertSame('Изменённый Description карточки', $xpath->evaluate('string(//head/meta[@name="description"]/@content)'));
        $this->assertSame(2, $xpath->query('//body//*[@data-guide-article]//a[@data-good-id="75" and contains(@href,"database-current-mackerel")]')->length);
        $response->assertDontSee('database-initial-mackerel');

        $good->update(['is_published' => false]);
        $response = $this->rawPage($this->classUrl());
        app(ClassPageHtmlVerifier::class)->verify($response->getContent(), app(PublicClassPage::class)->for($product));
        $xpath = $this->xpath($response->getContent());
        $this->assertSame(0, $xpath->query('//body//*[@data-class-guide]//*[@data-good-id="75"]')->length);
        $this->assertStringContainsString('скумбрию 600+ с Фарерских островов', $xpath->query('//body//*[@data-guide-article]')->item(0)->textContent);
        $this->artisan('app:check-class-pages')->assertSuccessful();
    }

    public function test_database_block_order_visibility_and_disabled_catalog_match_the_built_html(): void
    {
        [$product, , $landing] = $this->migratedLanding();
        $content = $landing->published_content;
        $content['hero']['title'] = '   ';
        $content['catalog']['enabled'] = false;
        $content['blocks'] = array_reverse($content['blocks']);
        foreach ($content['blocks'] as &$block) {
            if ($block['id'] === 'quality') {
                $block['enabled'] = false;
            }
        }
        unset($block);
        $landing->update(['published_content' => $content]);

        $response = $this->rawPage($this->classUrl());
        app(ClassPageHtmlVerifier::class)->verify($response->getContent(), app(PublicClassPage::class)->for($product));
        $xpath = $this->xpath($response->getContent());
        $actualOrder = [];
        foreach ($xpath->query('//body//*[@data-guide-article]/section[@data-landing-block]') as $section) {
            $actualOrder[] = $section->getAttribute('id');
        }
        $this->assertSame(['faq', 'glaze', 'uses', 'caliber', 'guide'], $actualOrder);
        $navigation = [];
        foreach ($xpath->query('//body//nav[@aria-label="Разделы страницы"]/a') as $link) {
            $navigation[] = $link->getAttribute('href');
        }
        $this->assertSame(['#faq', '#glaze', '#uses', '#guide'], $navigation);
        $this->assertSame('Скумбрия', $xpath->evaluate('string(//body//*[@data-class-guide]//h1)'));
        $this->assertSame(0, $xpath->query('//body//*[@data-class-goods or @data-inline-good-link or @id="quality" or @id="catalog"]')->length);
        $this->assertSame(0, $xpath->query('//body//*[@data-class-guide]//a[@href="#quality" or @href="#catalog"]')->length);
        $this->capture($response, 'database-reordered-no-catalog');
    }

    public function test_empty_generic_landing_and_private_draft_preview_render_through_the_actual_ssr_server(): void
    {
        $node = CatalogNode::create(['name' => 'Ингредиенты SSR', 'slug' => 'ingredients-ssr', 'is_published' => true,
            'meta_title' => 'Обзор ингредиентов SSR', 'meta_description' => 'Метаописание обзора ингредиентов']);
        $content = app(CatalogLandingTemplates::class)->forNode($node)[0]['content'];
        $content['hero']['title'] = '   ';
        $content['catalog']['enabled'] = false;
        $draft = $content;
        $draft['hero']['title'] = 'Заголовок приватного предпросмотра';
        $draft['hero']['description'] = '<script>опасная разметка</script>';
        CatalogLanding::create(['catalog_node_id' => $node->id, 'draft_content' => $draft, 'published_content' => $content,
            'published_at' => now(), 'activated_at' => now(), 'version' => 1]);
        $publicPage = app(PublicCatalogService::class)->page($node->id);
        $response = $this->rawPage($publicPage['node']['public_url']);
        app(ClassPageHtmlVerifier::class)->verify($response->getContent(), app(PublicClassPage::class)->forCatalogPage($publicPage));
        $xpath = $this->xpath($response->getContent());
        $this->assertSame('Ингредиенты SSR', $xpath->evaluate('string(//body//*[@data-class-guide]//h1)'));
        $this->assertSame('', trim($xpath->evaluate('string(//body//*[@data-guide-article="overview"])')));
        $response->assertDontSee('Заголовок приватного предпросмотра');
        $this->capture($response, 'database-empty-overview');

        $node->update(['is_published' => false]);
        $this->actingAs(User::factory()->create(['type' => 'employee', 'status' => 'active']));
        $response = $this->rawPage('/Ameise/catalog/nodes/'.$node->id.'/landing/preview')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertHeader('Cache-Control', 'no-store, private');
        $expected = app(PublicClassPage::class)->fromContent(app(PublicCatalogService::class)->previewPage($node->id), $draft, preview: true);
        app(ClassPageHtmlVerifier::class)->verify($response->getContent(), $expected);
        $xpath = $this->xpath($response->getContent());
        $this->assertSame('Заголовок приватного предпросмотра', $xpath->evaluate('string(//body//*[@data-class-guide]//h1)'));
        $this->assertSame('noindex,nofollow', $xpath->evaluate('string(//head/meta[@name="robots"]/@content)'));
        $this->assertStringContainsString('<script>опасная разметка</script>', $xpath->query('//body//*[@data-class-guide]')->item(0)->textContent);
        $this->assertSame(0, $xpath->query('//body//*[@data-class-guide]//script')->length);
        $this->assertSame(1, $xpath->query('//body//*[@data-class-guide]//*[@role="status" and contains(@class,"landing-preview")]')->length);
        $this->capture($response, 'database-private-preview');
        $this->rawPage('/')->assertDontSee('Заголовок приватного предпросмотра');
        $this->get($publicPage['node']['public_url'])->assertNotFound();
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

    private function migratedLanding(): array
    {
        $product = Product::forceCreate(['id' => 124, 'rus' => 'Скумбрия', 'is_published' => true]);
        $node = $this->createClassCatalog($product);
        $node->update(['meta_title' => 'Скумбрия из карточки — SSR', 'meta_description' => 'Описание из карточки для SSR']);
        $source = Product::forceCreate(['id' => 201, 'rus' => 'Скумбрия замороженная', 'is_published' => true]);
        $first = null;
        foreach ([75 => 'Скумбрия 600+ — актуальная партия', 104 => 'Скумбрия 500+ — актуальная партия'] as $id => $name) {
            $good = Good::forceCreate(['id' => $id, 'name' => $name, 'is_published' => true,
                'slug' => $id === 75 ? 'database-initial-mackerel' : 'database-mackerel-104', 'denominator' => 10]);
            $good->products()->attach($source);
            $first ??= $good;
        }
        $content = app(CatalogLandingTemplates::class)->named('mackerel');
        $landing = CatalogLanding::create(['catalog_node_id' => $node->id, 'draft_content' => $content,
            'published_content' => $content, 'published_at' => now(), 'activated_at' => now(), 'version' => 1]);

        return [$product, $node, $landing, $first];
    }

    private function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET);

        return new DOMXPath($document);
    }

    private function normalizedText(string $html): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(preg_replace('/<br\s*\/?\s*>/i', ' ', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    private function assertHeaderFishUrl(TestResponse $response): void
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent(), LIBXML_NONET);
        $links = (new DOMXPath($document))->query('//header//a[contains(@class,"app-header__quick-link") and normalize-space(.)="Рыба"]');
        $this->assertSame(1, $links->length);
        $this->assertSame(url('/catalog/ryba'), $links->item(0)->getAttribute('href'));
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
