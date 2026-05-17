<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Feeds\YandexDirectFeedService;
use App\Services\Seo\SitemapService;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function robots(): Response
    {
        $content = implode("\n", [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /dashboard',
            'Disallow: /login',
            'Disallow: /register',
            'Disallow: /Ameise',
            'Disallow: /api/',
            '',
            'Allow: /',
            '',
            'Sitemap: ' . route('seo.sitemap'),
            '',
        ]);

        return response($content, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    public function sitemap(SitemapService $service): Response
    {
        return response($service->xml(), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    public function yandexFeed(YandexDirectFeedService $service): Response
    {
        return response($service->xml(), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    public function indexNowKey(): Response
    {
        return response((string) config('services.indexnow.key'), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
