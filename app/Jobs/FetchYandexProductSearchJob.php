<?php

namespace App\Jobs;

use App\Models\ProductSearchRequest;
use App\Models\ProductSearchResult;
use App\Services\Yandex\YandexSearchException;
use App\Services\YandexSearchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\TimeoutExceededException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class FetchYandexProductSearchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels {
        Queueable::onConnection as private setQueueConnection;
    }

    public int $timeout = 330;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    public function __construct(
        public int $requestId,
        public int $maxResults = 100
    ) {
        $this->onConnection(null);
    }

    public function onConnection($connection)
    {
        $this->setQueueConnection($connection);
        $connectionName = $this->connection ?? config('queue.default');
        $retryAfter = config('queue.connections.'.$connectionName.'.retry_after');

        // Stop before another worker can reserve the same job and fail its
        // active request as exceeding max attempts. Keep time for failed().
        $this->timeout = is_numeric($retryAfter) && (int) $retryAfter > 0
            ? max(1, min(330, (int) $retryAfter - 10))
            : 330;

        return $this;
    }

    public function handle(YandexSearchService $service): void
    {
        // A duplicate queue delivery must not restart an active or completed search.
        $claimed = ProductSearchRequest::query()
            ->whereKey($this->requestId)
            ->where('status', 'queued')
            ->update([
                'status' => 'processing',
                'started_at' => now(),
                'finished_at' => null,
                'error_message' => null,
            ]);
        if (! $claimed) {
            return;
        }

        $request = ProductSearchRequest::query()->findOrFail($this->requestId);

        $perPage = YandexSearchService::RESULTS_PER_PAGE;
        $maxResults = max(10, min(100, $this->maxResults));
        $pages = (int) ceil($maxResults / $perPage);
        $allResults = [];
        $seenUrls = [];
        $page = 0;
        $providerRequestId = null;

        try {
            for ($page = 0; $page < $pages; $page++) {
                $providerRequestId = null;
                $response = $service->search($request->query, $page);
                $providerRequestId = $response['requestId'] ?? null;
                $parsed = $service->parseXmlResults($response['rawData'], count($allResults));

                if (empty($parsed)) {
                    break;
                }

                $previousCount = count($allResults);
                foreach ($parsed as $item) {
                    if (isset($seenUrls[$item['url']])) {
                        continue;
                    }
                    if (count($allResults) >= $maxResults) {
                        break 2;
                    }

                    $seenUrls[$item['url']] = true;
                    $item['position'] = count($allResults) + 1;
                    $allResults[] = $item;
                }

                if (count($allResults) >= $maxResults || count($allResults) === $previousCount) {
                    break;
                }
            }

            DB::transaction(function () use ($request, $allResults) {
                ProductSearchResult::query()
                    ->where('request_id', $request->id)
                    ->delete();

                $now = Carbon::now();

                $rows = array_map(function (array $item) use ($request, $now) {
                    return [
                        'request_id' => $request->id,
                        'position' => $item['position'],
                        'title' => $item['title'],
                        'url' => $item['url'],
                        'domain' => $item['domain'],
                        'snippet' => $item['snippet'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }, $allResults);

                if (! empty($rows)) {
                    ProductSearchResult::query()->insert($rows);
                }

                $request->update([
                    'status' => 'done',
                    'results_count' => count($rows),
                    'searched_at' => now(),
                    'finished_at' => now(),
                ]);
            });
        } catch (Throwable $e) {
            $safeCode = match (true) {
                $e instanceof YandexSearchException => $e->safeCode,
                $e instanceof QueryException => 'yandex_search_storage_failed',
                default => 'yandex_product_search_failed_safely',
            };
            // Never log raw exception messages: HTTP errors can contain credentials,
            // and database errors include SQL and the complete provider response.
            Log::warning('Product Yandex search failed.', array_filter([
                'request_id' => $request->id,
                'product_id' => $request->product_id,
                'page' => $page,
                'provider_request_id' => $providerRequestId,
                'safe_code' => $safeCode,
                'exception_class' => $e::class,
                'exception_file' => str_replace(base_path().'/', '', $e->getFile()),
                'exception_line' => $e->getLine(),
                'sql_state' => $e instanceof QueryException && preg_match('/^[A-Z0-9]{5}$/', (string) ($e->errorInfo[0] ?? '')) === 1 ? $e->errorInfo[0] : null,
                'driver_code' => $e instanceof QueryException && is_numeric($e->errorInfo[1] ?? null) ? (int) $e->errorInfo[1] : null,
            ], static fn ($value) => $value !== null));
            $request->update([
                'status' => 'failed',
                'error_message' => $safeCode,
                'finished_at' => now(),
            ]);

            if ($e instanceof YandexSearchException) {
                throw $e;
            }

            throw new YandexSearchException('internal', $safeCode);
        }
    }

    public function failed(?Throwable $exception): void
    {
        ProductSearchRequest::query()
            ->whereKey($this->requestId)
            ->whereIn('status', ['queued', 'processing'])
            ->update([
                'status' => 'failed',
                'error_message' => match (true) {
                    $exception instanceof TimeoutExceededException => 'yandex_search_timed_out',
                    $exception instanceof YandexSearchException => $exception->safeCode,
                    default => 'yandex_search_queue_failed',
                },
                'finished_at' => now(),
            ]);
    }
}
