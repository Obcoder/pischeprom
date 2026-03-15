<?php

namespace App\Jobs;

use App\Models\ProductSearchRequest;
use App\Models\ProductSearchResult;
use App\Services\YandexSearchService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class FetchYandexProductSearchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;

    public function __construct(
        public int $requestId,
        public int $maxResults = 100
    ) {}

    public function handle(YandexSearchService $service): void
    {
        /** @var ProductSearchRequest $request */
        $request = ProductSearchRequest::query()->findOrFail($this->requestId);

        $request->update([
                             'status' => 'processing',
                             'started_at' => now(),
                             'error_message' => null,
                         ]);

        $perPage = 10;
        $pages = (int) ceil($this->maxResults / $perPage);
        $allResults = [];

        try {
            for ($page = 0; $page < $pages; $page++) {
                $response = $service->search($request->query, $page);
                $parsed = $service->parseXmlResults($response['rawData'], $page * $perPage);

                if (empty($parsed)) {
                    break;
                }

                foreach ($parsed as $item) {
                    if (count($allResults) >= $this->maxResults) {
                        break 2;
                    }

                    $allResults[] = $item;
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

                if (!empty($rows)) {
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
            $request->update([
                                 'status' => 'failed',
                                 'error_message' => $e->getMessage(),
                                 'finished_at' => now(),
                             ]);

            throw $e;
        }
    }
}
