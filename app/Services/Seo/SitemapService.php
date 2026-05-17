<?php

namespace App\Services\Seo;

use App\Models\Category;
use App\Models\Good;
use XMLWriter;

class SitemapService
{
    public function xml(): string
    {
        $xml = new XMLWriter();

        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');

        $xml->startElement('urlset');
        $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        $this->writeUrl($xml, route('home'), now()->toDateString(), 'daily', '1.0');

        Category::query()
            ->orderBy('id')
            ->chunk(500, function ($categories) use ($xml) {
                foreach ($categories as $category) {
                    $this->writeUrl(
                        $xml,
                        route('category.show', [
                            'category' => $category->id,
                        ]),
                        optional($category->updated_at)->toDateString() ?: now()->toDateString(),
                        'weekly',
                        '0.8'
                    );
                }
            });

        Good::query()
            ->where('is_published', true)
            ->with('seo')
            ->whereHas('seo', function ($query) {
                $query
                    ->where('is_active', true)
                    ->where('include_in_sitemap', true)
                    ->where(function ($q) {
                        $q->whereNull('robots')
                            ->orWhere('robots', 'like', 'index%');
                    });
            })
            ->orderBy('id')
            ->chunk(500, function ($goods) use ($xml) {
                foreach ($goods as $good) {
                    if (!$good->slug) {
                        continue;
                    }

                    $this->writeUrl(
                        $xml,
                        route('public.goods.show', [
                            'good' => $good->slug,
                        ]),
                        optional($good->updated_at)->toDateString() ?: now()->toDateString(),
                        'weekly',
                        '0.9'
                    );
                }
            });

        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }

    private function writeUrl(
        XMLWriter $xml,
        string $loc,
        string $lastmod,
        string $changefreq,
        string $priority,
    ): void {
        $xml->startElement('url');

        $xml->writeElement('loc', $loc);
        $xml->writeElement('lastmod', $lastmod);
        $xml->writeElement('changefreq', $changefreq);
        $xml->writeElement('priority', $priority);

        $xml->endElement();
    }
}
