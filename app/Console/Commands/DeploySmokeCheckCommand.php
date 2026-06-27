<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class DeploySmokeCheckCommand extends Command
{
    protected $signature = 'app:deploy-smoke
        {--path=/ : Application path to request}
        {--status=200 : Expected HTTP status code}';

    protected $description = 'Run a production smoke request through Laravel and fail on HTTP errors.';

    public function handle(HttpKernel $kernel): int
    {
        $path = '/' . ltrim((string) $this->option('path'), '/');
        $expectedStatus = (int) $this->option('status');
        $baseUrl = rtrim((string) config('app.url', 'http://localhost'), '/');
        $uri = $baseUrl . ($path === '/' ? '/' : $path);
        $response = null;

        $request = Request::create($uri, 'GET', server: [
            'HTTP_ACCEPT' => 'text/html,application/xhtml+xml',
        ]);

        try {
            $response = $kernel->handle($request);
        } catch (Throwable $exception) {
            $this->error("Smoke request {$path} threw " . $exception::class . ': ' . $exception->getMessage());

            return self::FAILURE;
        } finally {
            if ($response instanceof Response) {
                $kernel->terminate($request, $response);
            }
        }

        $status = $response->getStatusCode();

        if ($status !== $expectedStatus) {
            $this->error("Smoke request {$path} returned HTTP {$status}; expected {$expectedStatus}.");

            return self::FAILURE;
        }

        $this->info("Smoke request {$path} returned HTTP {$status}.");

        return self::SUCCESS;
    }
}
