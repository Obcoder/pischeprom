<?php

namespace Tests\Feature\AiSales;

use App\Jobs\FetchYandexProductSearchJob;
use App\Models\Product;
use App\Models\ProductSearchRequest;
use App\Services\Yandex\YandexSearchException;
use App\Services\YandexSearchService;
use Illuminate\Database\QueryException;
use Illuminate\Queue\TimeoutExceededException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;

class YandexProductSearchReliabilityTest extends UnitContextsTestCase
{
    protected bool $allowExpectedHttpRequests = true;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set([
            'services.yandex_search.api_key' => 'test-yandex-secret',
            'services.yandex_search.folder_id' => 'test-folder',
            'services.yandex_search.host' => 'searchapi.api.cloud.yandex.net',
        ]);
    }

    public function test_job_saves_variable_page_sizes_without_duplicate_positions(): void
    {
        $request = $this->searchRequest();
        // The production response for request 18 returned 11 documents on page 0.
        Http::fakeSequence()
            ->push(['rawData' => $this->xmlResults(range(1, 11))])
            ->push(['rawData' => $this->xmlResults(range(12, 21))]);

        (new FetchYandexProductSearchJob($request->id, 20))->handle(app(YandexSearchService::class));

        $request->refresh();
        $this->assertSame('done', $request->status);
        $this->assertSame(20, $request->results_count);
        $this->assertSame(range(1, 20), $request->results->pluck('position')->all());
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request['groupSpec'] === [
            'groupMode' => 'GROUP_MODE_FLAT',
            'groupsOnPage' => 10,
            'docsInGroup' => 1,
        ]);
    }

    public function test_long_unicode_titles_fit_the_mysql_column(): void
    {
        // SQLite normally ignores VARCHAR lengths; emulate the production limit.
        DB::unprepared("CREATE TRIGGER product_search_title_length BEFORE INSERT ON product_search_results
            WHEN length(NEW.title) > 255 BEGIN SELECT RAISE(ABORT, 'title too long'); END");
        $request = $this->searchRequest();
        Http::fakeSequence()
            ->push(['rawData' => $this->xmlResults([1], str_repeat('Рыба ', 110))]);

        (new FetchYandexProductSearchJob($request->id, 10))->handle(app(YandexSearchService::class));

        $this->assertSame('done', $request->fresh()->status);
        $this->assertSame(255, mb_strlen($request->results()->sole()->title));
    }

    public function test_repeated_pages_are_deduplicated_and_stop_extra_api_calls(): void
    {
        $request = $this->searchRequest();
        Http::fakeSequence()
            ->push(['rawData' => $this->xmlResults(range(1, 10))])
            ->push(['rawData' => $this->xmlResults(range(1, 10))]);

        (new FetchYandexProductSearchJob($request->id, 100))->handle(app(YandexSearchService::class));

        $this->assertSame('done', $request->fresh()->status);
        $this->assertSame(10, $request->fresh()->results_count);
        Http::assertSentCount(2);
    }

    public function test_duplicate_queue_delivery_does_not_restart_a_completed_search(): void
    {
        $request = $this->searchRequest(['status' => 'done', 'results_count' => 10]);

        (new FetchYandexProductSearchJob($request->id))->handle(app(YandexSearchService::class));

        $this->assertSame('done', $request->fresh()->status);
        $this->assertSame(10, $request->fresh()->results_count);
        Http::assertNothingSent();
    }

    public function test_provider_xml_errors_fail_the_job_instead_of_saving_empty_success(): void
    {
        $request = $this->searchRequest();
        Http::fakeSequence()->push(['rawData' => base64_encode(
            '<yandexsearch><response><error code="55">secret provider details</error></response></yandexsearch>',
        )]);

        try {
            (new FetchYandexProductSearchJob($request->id))->handle(app(YandexSearchService::class));
            $this->fail('The provider error must fail the search.');
        } catch (YandexSearchException $exception) {
            $this->assertSame('yandex_search_xml_error_55', $exception->safeCode);
            $this->assertSame('rate_limit', $exception->category);
            $this->assertStringNotContainsString('secret provider details', $exception->getMessage());
        }

        $this->assertSame('failed', $request->fresh()->status);
        $this->assertSame('yandex_search_xml_error_55', $request->fresh()->error_message);
        Http::assertSentCount(1);
    }

    public function test_provider_no_results_code_is_a_successful_empty_search(): void
    {
        $request = $this->searchRequest();
        Http::fakeSequence()->push(['rawData' => base64_encode(
            '<yandexsearch><response><error code="15">Nothing found</error></response></yandexsearch>',
        )]);

        (new FetchYandexProductSearchJob($request->id))->handle(app(YandexSearchService::class));

        $this->assertSame('done', $request->fresh()->status);
        $this->assertSame(0, $request->fresh()->results_count);
        Http::assertSentCount(1);
    }

    #[DataProvider('invalidEnvelopes')]
    public function test_missing_xml_is_not_reported_as_an_empty_success(array $response): void
    {
        Http::fakeSequence()->push($response);

        $this->expectException(YandexSearchException::class);
        try {
            app(YandexSearchService::class)->search('Скумбрия добыча');
        } catch (YandexSearchException $exception) {
            $this->assertSame('yandex_search_xml_envelope_invalid', $exception->safeCode);
            throw $exception;
        }
    }

    public static function invalidEnvelopes(): array
    {
        return [
            'missing' => [['requestId' => 'test']],
            'empty' => [['rawData' => '']],
            'wrong type' => [['rawData' => ['invalid']]],
        ];
    }

    public function test_valid_xml_with_no_search_response_is_rejected(): void
    {
        $this->expectException(YandexSearchException::class);
        try {
            app(YandexSearchService::class)->parseXmlResults('<yandexsearch><response/></yandexsearch>');
        } catch (YandexSearchException $exception) {
            $this->assertSame('yandex_search_xml_invalid', $exception->safeCode);
            throw $exception;
        }
    }

    public function test_timeout_callback_releases_active_request_and_preserves_existing_failure(): void
    {
        $request = $this->searchRequest(['status' => 'processing']);
        $job = new FetchYandexProductSearchJob($request->id);
        $job->failed(new TimeoutExceededException('worker timeout'));

        $this->assertSame('failed', $request->fresh()->status);
        $this->assertSame('yandex_search_timed_out', $request->fresh()->error_message);
        $this->assertNotNull($request->fresh()->finished_at);

        $job->failed(new RuntimeException('later queue exception'));
        $this->assertSame('yandex_search_timed_out', $request->fresh()->error_message);
    }

    public function test_queue_payload_timeout_finishes_before_default_connection_redelivery(): void
    {
        config()->set([
            'queue.default' => 'database',
            'queue.connections.database.retry_after' => 90,
        ]);
        $job = new FetchYandexProductSearchJob(123);
        $jobId = Queue::connection('database')->push($job);
        $payload = json_decode(DB::table('jobs')->where('id', $jobId)->value('payload'), true);

        $this->assertSame(80, $payload['timeout']);
        $this->assertTrue($payload['failOnTimeout']);
        $this->assertSame(1, $payload['maxTries']);
        $this->assertLessThan(config('queue.connections.database.retry_after'), $payload['timeout']);
        Http::assertNothingSent();
    }

    public function test_explicit_queue_connection_recomputes_the_timeout_in_its_payload(): void
    {
        config()->set([
            'queue.default' => 'database',
            'queue.connections.database.retry_after' => 900,
            'queue.connections.yandex-test' => [
                ...config('queue.connections.database'),
                'retry_after' => 45,
            ],
        ]);
        $job = new FetchYandexProductSearchJob(123);
        $this->assertSame(330, $job->timeout);
        $job->onConnection('yandex-test');
        $jobId = Queue::connection('yandex-test')->push($job);
        $payload = json_decode(DB::table('jobs')->where('id', $jobId)->value('payload'), true);

        $this->assertSame(35, $payload['timeout']);
        $this->assertSame('yandex-test', unserialize($payload['data']['command'])->connection);
        $this->assertLessThan(config('queue.connections.yandex-test.retry_after'), $payload['timeout']);
        Http::assertNothingSent();
    }

    public function test_database_diagnostics_keep_sql_state_without_sql_or_secrets(): void
    {
        $request = $this->searchRequest();
        $previous = new PDOException('sensitive database value');
        $previous->errorInfo = ['23000', 1062, 'sensitive duplicate value'];
        $exception = new QueryException('sqlite', 'insert secret-sql', ['secret-binding'], $previous);
        $service = $this->mock(YandexSearchService::class);
        $service->shouldReceive('search')->once()->andThrow($exception);
        Log::spy();

        try {
            (new FetchYandexProductSearchJob($request->id))->handle($service);
            $this->fail('Database failure must be surfaced safely.');
        } catch (YandexSearchException $safeException) {
            $this->assertSame('yandex_search_storage_failed', $safeException->safeCode);
            $this->assertNull($safeException->getPrevious());
        }

        $this->assertSame('yandex_search_storage_failed', $request->fresh()->error_message);
        Log::shouldHaveReceived('warning')->once()->withArgs(function ($message, $context) use ($request): bool {
            $this->assertStringNotContainsString('secret', json_encode($context));
            $this->assertStringNotContainsString('sensitive', json_encode($context));

            return $message === 'Product Yandex search failed.'
                && $context['request_id'] === $request->id
                && $context['sql_state'] === '23000'
                && $context['driver_code'] === 1062
                && $context['exception_class'] === QueryException::class;
        });
    }

    public function test_default_query_uses_the_product_name_and_concurrent_clicks_reuse_active_request(): void
    {
        Queue::fake();
        $actor = $this->userWith(['products.view']);
        $product = $this->product();
        $url = "/api/products/{$product->id}/yandex-search";

        $first = $this->actingAs($actor)->postJson($url, [])
            ->assertCreated()->assertJsonPath('query', 'Скумбрия купить');
        $this->postJson($url, ['query' => 'Другой запрос'])->assertOk()
            ->assertJsonPath('request_id', $first->json('request_id'));
        Queue::assertPushed(FetchYandexProductSearchJob::class, 1);
    }

    public function test_latest_and_show_expose_safe_codes_and_readable_errors(): void
    {
        $actor = $this->userWith(['products.view']);
        $request = $this->searchRequest([
            'status' => 'failed',
            'error_message' => 'yandex_search_storage_failed',
        ]);

        foreach (['latest', (string) $request->id] as $suffix) {
            $this->actingAs($actor)->getJson("/api/products/{$request->product_id}/yandex-search/{$suffix}")
                ->assertOk()
                ->assertJsonPath('request.error_code', 'yandex_search_storage_failed')
                ->assertJsonPath('request.error_message', YandexSearchException::userMessage('yandex_search_storage_failed'))
                ->assertJsonStructure(['request' => ['created_at']]);
        }

        $request->update(['error_message' => 'SQLSTATE: old secret database payload']);
        $this->getJson("/api/products/{$request->product_id}/yandex-search/latest")
            ->assertOk()
            ->assertJsonPath('request.error_code', 'yandex_product_search_failed_safely')
            ->assertDontSee('old secret database payload');
    }

    public function test_unavailable_queue_does_not_leave_a_permanently_active_request(): void
    {
        $actor = $this->userWith(['products.view']);
        $product = $this->product();
        Bus::shouldReceive('dispatch')->once()->andThrow(new RuntimeException('private queue details'));

        $response = $this->actingAs($actor)->postJson("/api/products/{$product->id}/yandex-search", [])
            ->assertStatus(503)
            ->assertJsonPath('error_code', 'yandex_search_queue_unavailable')
            ->assertDontSee('private queue details');

        $this->assertDatabaseHas('product_search_requests', [
            'id' => $response->json('request_id'),
            'status' => 'failed',
            'error_message' => 'yandex_search_queue_unavailable',
        ]);
    }

    private function product(): Product
    {
        return Product::query()->without(['category', 'manufacturers'])->create([
            'rus' => 'Скумбрия',
            'is_published' => true,
        ]);
    }

    private function searchRequest(array $attributes = []): ProductSearchRequest
    {
        return ProductSearchRequest::query()->create([
            'product_id' => $this->product()->id,
            'engine' => 'yandex',
            'query' => 'Скумбрия добыча',
            'status' => 'queued',
            ...$attributes,
        ]);
    }

    private function xmlResults(array $ids, string $title = 'Скумбрия'): string
    {
        $docs = array_map(fn (int $id): string => '<group><doc><url>https://example.org/'.$id
            .'</url><title>'.htmlspecialchars($title, ENT_XML1).'</title></doc></group>', $ids);

        return base64_encode('<yandexsearch><response><results><grouping>'.implode('', $docs)
            .'</grouping></results></response></yandexsearch>');
    }
}
