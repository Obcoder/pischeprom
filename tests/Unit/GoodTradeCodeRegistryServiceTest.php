<?php

namespace Tests\Unit;

use App\Services\Goods\GoodTnVedPdfCatalog;
use App\Services\Goods\GoodTradeCodeRegistryService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Mockery;
use Smalot\PdfParser\Document;
use Smalot\PdfParser\Page;
use Smalot\PdfParser\Parser;
use Tests\TestCase;

class GoodTradeCodeRegistryServiceTest extends TestCase
{
    private const HS_URL = 'https://comtradeapi.un.org/files/v1/app/reference/H6.json';

    private const EEC_URL = 'https://eec.eaeunion.org/comission/department/catr/ett/';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('cache.default', 'array');
        Cache::flush();
        Http::preventStrayRequests();
    }

    public function test_hs_exact_lookup_uses_a_complete_versioned_catalog_and_cache(): void
    {
        Http::fake([self::HS_URL => Http::response($this->catalog())]);
        $service = app(GoodTradeCodeRegistryService::class);
        $found = $service->lookup('hs_code', ' 1604.20 ');
        $this->assertSame('found', $found['status']);
        $this->assertSame('160420', $found['code']);
        $this->assertSame('Fish preparations', $found['title']);
        $this->assertSame(self::HS_URL, $found['source_url']);
        $this->assertSame('HS 2022 (H6)', $found['version']);
        $this->assertNotEmpty($found['checked_at']);
        $this->assertSame('not_found', $service->lookup('hs_code', '019999')['status']);
        $this->assertSame('not_found', $service->lookup('hs_code', '999999')['status']);
        $this->assertSame('found', $service->lookup('hs_code', '010121')['status']);
        Http::assertSentCount(1);
    }

    public function test_partial_catalogs_and_wrong_editions_never_report_code_absence(): void
    {
        foreach ([
            ['classCode' => 'H5', 'className' => 'HS2017'],
            ['more' => true],
            ['results' => [['id' => '160420', 'text' => '160420 - Fish preparations']]],
        ] as $overrides) {
            Cache::flush();
            Http::fake([self::HS_URL => Http::response([...$this->catalog(), ...$overrides])]);
            $this->assertSame('unavailable', app(GoodTradeCodeRegistryService::class)->lookup('hs_code', '019999')['status']);
        }
    }

    public function test_failed_sources_are_briefly_cached_without_exposing_errors(): void
    {
        Http::fake([self::HS_URL => Http::sequence()->pushFailedConnection()->push($this->catalog())]);
        $service = app(GoodTradeCodeRegistryService::class);
        $this->assertSame('unavailable', $service->lookup('hs_code', '160420')['status']);
        $this->assertSame('unavailable', $service->lookup('hs_code', '010121')['status']);
        Http::assertSentCount(1);
        $this->travel(61)->seconds();
        $this->assertSame('found', $service->lookup('hs_code', '160420')['status']);
    }

    public function test_oversized_or_unexpected_content_is_unavailable(): void
    {
        foreach ([
            Http::response(str_repeat('x', 3_000_001), 200, ['Content-Type' => 'application/json']),
            Http::response('<html>challenge</html>', 200, ['Content-Type' => 'text/html']),
            Http::response('', 302, ['Location' => 'https://untrusted.example/catalog.json']),
        ] as $response) {
            Cache::flush();
            Http::fake([self::HS_URL => $response]);
            $this->assertSame('unavailable', app(GoodTradeCodeRegistryService::class)->lookup('hs_code', '160420')['status']);
        }
    }

    public function test_tn_ved_uses_only_a_chapter_link_from_the_official_index_and_caches_pdf(): void
    {
        $url = 'https://eec.eaeunion.org/comission/department/catr/ett/ru.2022/ru.16_2022_10.09.2023.pdf';
        Http::fake([
            self::EEC_URL => Http::response($this->chapterIndex(), 200, ['Content-Type' => 'text/html']),
            $url => Http::response('%PDF-fixture', 200, ['Content-Type' => 'application/pdf']),
        ]);
        $pdf = Mockery::mock(GoodTnVedPdfCatalog::class);
        $pdf->shouldReceive('title')->once()->with('%PDF-fixture', '1604200500')->andReturn('готовые продукты из сурими');
        $pdf->shouldReceive('title')->once()->with('%PDF-fixture', '1604209999')->andReturn(null);
        $service = new GoodTradeCodeRegistryService($pdf);
        $found = $service->lookup('tn_ved_code', '1604 20 050 0');
        $this->assertSame('found', $found['status']);
        $this->assertSame('готовые продукты из сурими', $found['title']);
        $this->assertSame($url, $found['source_url']);
        $this->assertSame('ru.16_2022_10.09.2023.pdf', $found['version']);
        $this->assertSame('found', $service->lookup('tn_ved_code', '1604200500')['status']);
        $this->assertSame('unavailable', $service->lookup('tn_ved_code', '1604209999')['status']);
        Http::assertSentCount(2);
    }

    public function test_external_chapter_links_are_never_downloaded(): void
    {
        $index = str_replace('/comission/department/catr/ett/ru.2022/ru.16_', 'https://untrusted.example/ru.16_', $this->chapterIndex());
        Http::fake([self::EEC_URL => Http::response($index, 200, ['Content-Type' => 'text/html'])]);
        $result = app(GoodTradeCodeRegistryService::class)->lookup('tn_ved_code', '1604200500');
        $this->assertSame('unavailable', $result['status']);
        Http::assertSentCount(1);
    }

    public function test_ambiguous_eec_chapter_versions_are_not_chosen_arbitrarily(): void
    {
        $index = $this->chapterIndex().'<a href="/upload/files/catr/ett/ru.16_2022.pdf">Архив</a>';
        Http::fake([self::EEC_URL => Http::response($index, 200, ['Content-Type' => 'text/html'])]);
        $result = app(GoodTradeCodeRegistryService::class)->lookup('tn_ved_code', '1604200500');
        $this->assertSame('unavailable', $result['status']);
        Http::assertSentCount(1);
    }

    public function test_unsupported_classifiers_are_honest_and_do_not_make_requests(): void
    {
        $service = app(GoodTradeCodeRegistryService::class);
        foreach (['okpd2_code' => '10.20.25.110', 'gtin' => '0123456789012', 'cas_number' => '7647-14-5'] as $field => $code) {
            $result = $service->lookup($field, $code);
            $this->assertSame('not_supported', $result['status']);
            $this->assertSame($code, $result['code']);
            $this->assertNotEmpty($result['source_url']);
        }
        Http::assertNothingSent();
    }

    public function test_pdf_parser_only_accepts_exact_table_rows_not_notes_or_longer_codes(): void
    {
        $page = Mockery::mock(Page::class);
        $page->shouldReceive('getText')->andReturn("1604 20 999 9 – упоминание в примечании\nКод\nТН ВЭД\nНаименование позиции\n1604 20 050 0 – – готовые продукты из сурими – 12,5\n1604 20 901 0 – – – прочие\n");
        $document = Mockery::mock(Document::class);
        $document->shouldReceive('getPages')->andReturn([$page]);
        $parser = Mockery::mock(Parser::class);
        $parser->shouldReceive('parseContent')->with('%PDF-fixture')->andReturn($document);
        $catalog = new GoodTnVedPdfCatalog($parser);
        $this->assertSame('готовые продукты из сурими', $catalog->title('%PDF-fixture', '1604200500'));
        $this->assertNull($catalog->title('%PDF-fixture', '1604209999'));
        $this->assertNull($catalog->title('%PDF-fixture', '1604209000'));
    }

    private function catalog(): array
    {
        $rows = [];
        for ($i = 10000; $i < 15000; $i++) {
            $code = sprintf('%06d', $i);
            $rows[] = ['id' => $code, 'text' => $code.' - Fixture commodity'];
        }
        $rows[] = ['id' => '160420', 'text' => '160420 - Fish preparations'];
        $rows[] = ['id' => '999999', 'text' => '999999 - Statistical reporting placeholder'];

        return ['classCode' => 'H6', 'className' => 'HS2022', 'more' => false, 'results' => $rows];
    }

    private function chapterIndex(): string
    {
        $html = '<html><body>';
        for ($chapter = 1; $chapter <= 97; $chapter++) {
            if ($chapter === 77) {
                continue;
            }
            $number = sprintf('%02d', $chapter);
            $date = $chapter === 16 ? '_10.09.2023' : '';
            $html .= '<a href="/comission/department/catr/ett/ru.2022/ru.'.$number.'_2022'.$date.'.pdf">Группа '.$number.'</a>';
        }

        return $html.'</body></html>';
    }
}
