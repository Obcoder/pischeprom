<?php

namespace App\Services\Seo;

use App\Models\Category;
use App\Models\Good;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use XMLWriter;

class SitemapService
{
    private const SITEMAP_NAMESPACE = 'http://www.sitemaps.org/schemas/sitemap/0.9';

    public function xml(): string
    {
        $xml = new XMLWriter();

        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');

        $xml->startElement('urlset');
        $xml->writeAttribute('xmlns', self::SITEMAP_NAMESPACE);

        $this->writeUrl($xml, route('home'), now()->toDateString(), 'daily', '1.0');

        Category::query()
            ->orderBy('id')
            ->chunk(500, function ($categories) use ($xml): void {
                /** @var Category $category */
                foreach ($categories as $category) {
                    $this->writeUrl(
                        $xml,
                        route('category.show', [
                            'category' => $category->getKey(),
                        ]),
                        $this->lastmod($category),
                        'weekly',
                        '0.8'
                    );
                }
            });

        Good::query()
            ->where('is_published', true)
            ->with('seo')
            ->whereHas('seo', function ($query): void {
                $query
                    ->where('is_active', true)
                    ->where('include_in_sitemap', true)
                    ->where(function ($q): void {
                        $q->whereNull('robots')
                            ->orWhere('robots', 'like', 'index%');
                    });
            })
            ->orderBy('id')
            ->chunk(500, function ($goods) use ($xml): void {
                /** @var Good $good */
                foreach ($goods as $good) {
                    $slug = $this->goodCanonicalSlug($good);

                    if ($slug === null) {
                        continue;
                    }

                    $this->writeUrl(
                        $xml,
                        route('public.goods.show', [
                            'good' => $slug,
                        ]),
                        $this->lastmod($good),
                        'weekly',
                        '0.9'
                    );
                }
            });

        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }

    private function goodCanonicalSlug(Good $good): ?string
    {
        $seo = $good->getRelationValue('seo');

        $seoIsActive = (bool) data_get($seo, 'is_active');
        $slugOverride = trim((string) data_get($seo, 'slug_override'));

        if ($seoIsActive && $slugOverride !== '') {
            return $slugOverride;
        }

        $slug = trim((string) $good->getAttribute('slug'));

        return $slug !== '' ? $slug : null;
    }

    private function lastmod(Model $model): string
    {
        $updatedAt = $model->getAttribute('updated_at');

        if ($updatedAt instanceof CarbonInterface) {
            return $updatedAt->toDateString();
        }

        return now()->toDateString();
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
