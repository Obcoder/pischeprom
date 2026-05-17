<?php

namespace App\Services\Seo;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IndexNowService
{
    public function submit(array $urls): bool
    {
        $key = config('services.indexnow.key');

        if (!$key || !count($urls)) {
            return false;
        }

        $host = parse_url(config('app.url'), PHP_URL_HOST);

        try {
            $response = Http::timeout(10)->post('https://api.indexnow.org/indexnow', [
                'host' => $host,
                'key' => $key,
                'keyLocation' => route('seo.indexnow-key'),
                'urlList' => array_values($urls),
            ]);

            if (!$response->successful()) {
                Log::warning('IndexNow submit failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('IndexNow submit exception', [
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
