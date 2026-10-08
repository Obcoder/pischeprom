<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\Catalog\PublicClassPage;
use App\Services\Seo\ClassPageHtmlVerifier;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Inertia\Ssr\SsrState;
use Throwable;

class CheckClassPagesCommand extends Command
{
    protected $signature = 'app:check-class-pages';

    protected $description = 'Verify published class guides and catalog links in actual server-rendered HTML.';

    public function handle(HttpKernel $kernel, PublicClassPage $pages, ClassPageHtmlVerifier $verifier): int
    {
        $ids = collect(config('product-pages.pages', []))
            ->filter(fn ($page): bool => is_array($page) && filled($page['guide'] ?? null))
            ->keys()->map(fn ($id): int => (int) $id)->filter()->unique();
        $products = Product::query()->without('manufacturers')->whereIn('id', $ids)
            ->where('is_published', true)->orderBy('id')->get();
        if ($products->isEmpty()) {
            $this->info('No published class guides are configured.');

            return self::SUCCESS;
        }
        if (! config('inertia.ssr.enabled')) {
            $this->error('Class guide verification requires Inertia SSR to be enabled.');

            return self::FAILURE;
        }

        $originalThrowSetting = config('inertia.ssr.throw_on_error');
        config()->set('inertia.ssr.throw_on_error', true);
        try {
            foreach ($products as $product) {
                $page = $pages->for($product);
                $request = Request::create($page['seo']['canonical'], 'GET', server: [
                    'HTTP_ACCEPT' => 'text/html,application/xhtml+xml',
                ]);
                // CLI smoke requests share one container; SSR state belongs to
                // a single HTTP request and must not reuse the previous page.
                app()->forgetInstance(SsrState::class);
                $response = $kernel->handle($request);
                try {
                    if ($response->getStatusCode() !== 200) {
                        $this->error('Class guide '.$product->id.' returned HTTP '.$response->getStatusCode().'.');

                        return self::FAILURE;
                    }
                    $verifier->verify($response->getContent(), $page);
                } finally {
                    $kernel->terminate($request, $response);
                }
                $this->info('Class guide '.$product->id.': rendered article, catalog, metadata and current URLs verified.');
            }
        } catch (Throwable $exception) {
            // A render error can include page props; avoid dumping it in deploy logs.
            $this->error('Class guide SSR verification failed ('.$exception::class.'). Run the class-page tests or inspect the application log.');

            return self::FAILURE;
        } finally {
            config()->set('inertia.ssr.throw_on_error', $originalThrowSetting);
        }

        return self::SUCCESS;
    }
}
