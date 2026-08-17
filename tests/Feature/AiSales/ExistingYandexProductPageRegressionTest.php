<?php

namespace Tests\Feature\AiSales;

use App\Jobs\FetchYandexProductSearchJob;
use App\Models\Product;
use App\Models\ProductSearchRequest;
use App\Services\Yandex\YandexSearchException;
use App\Services\YandexSearchService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;

class ExistingYandexProductPageRegressionTest extends UnitContextsTestCase
{
    protected bool $allowExpectedHttpRequests = true;

    public function test_product_page_search_routes_are_authenticated_and_preserve_the_json_contract(): void
    {
        Queue::fake();
        $product = Product::query()->without(['category', 'manufacturers'])->create([
            'rus' => 'Регрессионный продукт',
            'is_published' => true,
        ]);

        $this->getJson("/api/products/{$product->id}/yandex-search/latest")->assertUnauthorized();
        $blocked = $this->userWith(['products.view']);
        $blocked->update(['status' => 'blocked']);
        $this->actingAs($blocked)
            ->getJson("/api/products/{$product->id}/yandex-search/latest")
            ->assertForbidden();
        $actor = $this->userWith(['products.view']);
        $this->actingAs($actor)->getJson("/api/products/{$product->id}/yandex-search/latest")
            ->assertOk()
            ->assertExactJson(['request' => null, 'results' => []]);
        $response = $this->actingAs($actor)->postJson("/api/products/{$product->id}/yandex-search", [
            'query' => 'Регрессионный продукт купить',
            'max_results' => 20,
        ])->assertCreated();
        $response->assertJsonPath('ok', true)
            ->assertJsonPath('status', 'queued')
            ->assertJsonPath('query', 'Регрессионный продукт купить');
        $this->assertDatabaseHas('product_search_requests', [
            'product_id' => $product->id,
            'engine' => 'yandex',
            'query' => 'Регрессионный продукт купить',
            'status' => 'queued',
        ]);
        Queue::assertPushed(FetchYandexProductSearchJob::class, fn ($job) => $job->maxResults === 20);
        Http::assertNothingSent();
    }

    public function test_existing_service_keeps_exact_endpoint_auth_payload_and_parser_with_one_http_source(): void
    {
        config()->set([
            'services.yandex_search.api_key' => 'stage09-test-key',
            'services.yandex_search.folder_id' => 'stage09-test-folder',
            'services.yandex_search.host' => 'searchapi.api.cloud.yandex.net',
        ]);
        $xml = <<<'XML'
<yandexsearch><response><results><grouping><group><doc>
<url>https://company.example/about</url><domain>company.example</domain>
<title>Синтетическая компания</title><passages><passage>Публичный сниппет</passage></passages>
</doc></group></grouping></results></response></yandexsearch>
XML;
        Http::fake([
            'https://searchapi.api.cloud.yandex.net/v2/web/search' => Http::response(
                ['rawData' => base64_encode($xml)],
                200,
                ['Content-Type' => 'application/json', 'X-Request-Id' => 'safe-yandex-request-1'],
            ),
        ]);

        $service = app(YandexSearchService::class);
        $response = $service->search('Синтетический продукт купить', 0);
        $results = $service->parseXmlResults($response['rawData']);
        $this->assertSame('safe-yandex-request-1', $response['requestId']);
        $this->assertSame('https://company.example/about', $results[0]['url']);
        $this->assertSame('Синтетическая компания', $results[0]['title']);
        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://searchapi.api.cloud.yandex.net/v2/web/search'
                && $request->hasHeader('Authorization', 'Api-Key stage09-test-key')
                && $request['folderId'] === 'stage09-test-folder'
                && $request['query']['queryText'] === 'Синтетический продукт купить'
                && $request['responseFormat'] === 'FORMAT_XML';
        });
    }

    public function test_product_job_persists_only_normalized_results_and_safe_errors(): void
    {
        config()->set([
            'services.yandex_search.api_key' => 'stage09-test-key',
            'services.yandex_search.folder_id' => 'stage09-test-folder',
            'services.yandex_search.host' => 'searchapi.api.cloud.yandex.net',
        ]);
        $product = Product::query()->without(['category', 'manufacturers'])->create([
            'rus' => 'Нормализованный продукт',
            'is_published' => true,
        ]);
        $searchRequest = ProductSearchRequest::query()->create([
            'product_id' => $product->id,
            'engine' => 'yandex',
            'query' => 'Нормализованный продукт купить',
            'status' => 'queued',
        ]);
        $xml = '<yandexsearch><response><results><grouping><group><doc><url>https://company.example/</url><title>Company</title></doc></group></grouping></results></response></yandexsearch>';
        Http::fake(['*' => Http::response(
            ['rawData' => base64_encode($xml)],
            200,
            ['Content-Type' => 'application/json'],
        )]);
        (new FetchYandexProductSearchJob($searchRequest->id, 10))->handle(app(YandexSearchService::class));
        $this->assertDatabaseHas('product_search_results', [
            'request_id' => $searchRequest->id,
            'url' => 'https://company.example/',
            'title' => 'Company',
        ]);
        $this->assertSame('done', $searchRequest->fresh()->status);

        $malicious = '<!DOCTYPE x [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><response>&xxe;</response>';
        try {
            app(YandexSearchService::class)->parseXmlResults(base64_encode($malicious));
            $this->fail('DTD payload was parsed.');
        } catch (YandexSearchException $exception) {
            $this->assertSame('yandex_search_xml_dtd_blocked', $exception->safeCode);
        }
    }

    #[DataProvider('unsafeTransportResponses')]
    public function test_existing_transport_rejects_unsafe_or_malformed_responses_without_retry(
        string $body,
        int $status,
        array $headers,
        string $safeCode,
    ): void {
        config()->set([
            'services.yandex_search.api_key' => 'stage09-test-key',
            'services.yandex_search.folder_id' => 'stage09-test-folder',
            'services.yandex_search.host' => 'searchapi.api.cloud.yandex.net',
        ]);
        Http::fake([
            'https://searchapi.api.cloud.yandex.net/v2/web/search' => Http::response($body, $status, $headers),
        ]);

        try {
            app(YandexSearchService::class)->search('Безопасный синтетический запрос', 0);
            $this->fail('Unsafe Yandex response was accepted.');
        } catch (YandexSearchException $exception) {
            $this->assertSame($safeCode, $exception->safeCode);
            $this->assertStringNotContainsString('stage09-test-key', $exception->getMessage());
            if ($body !== '') {
                $this->assertStringNotContainsString($body, $exception->getMessage());
            }
        }

        Http::assertSentCount(1);
    }

    public static function unsafeTransportResponses(): array
    {
        return [
            'redirect' => [
                '', 302, ['Location' => 'https://example.invalid/', 'Content-Type' => 'application/json'],
                'yandex_search_redirect_blocked',
            ],
            'compressed envelope' => [
                '{"rawData":""}', 200, ['Content-Type' => 'application/json', 'Content-Encoding' => 'gzip'],
                'yandex_search_compressed_response_blocked',
            ],
            'malformed JSON' => [
                '{not-json', 200, ['Content-Type' => 'application/json'], 'yandex_search_json_invalid',
            ],
            'unexpected content type' => [
                '<html>not json</html>', 200, ['Content-Type' => 'text/html'], 'yandex_search_content_type_invalid',
            ],
            'declared oversized body' => [
                '{"rawData":""}', 200, ['Content-Type' => 'application/json', 'Content-Length' => '9999999'],
                'yandex_search_response_too_large',
            ],
        ];
    }

    public function test_existing_xml_parser_rejects_malformed_and_oversized_documents(): void
    {
        $service = app(YandexSearchService::class);
        foreach ([
            ['<response>', 'yandex_search_xml_invalid'],
            [str_repeat('x', 2_097_153), 'yandex_search_xml_too_large'],
        ] as [$xml, $safeCode]) {
            try {
                $service->parseXmlResults($xml);
                $this->fail('Unsafe XML was accepted.');
            } catch (YandexSearchException $exception) {
                $this->assertSame($safeCode, $exception->safeCode);
            }
        }

        Http::assertNothingSent();
    }
}
