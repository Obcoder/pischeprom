<?php

namespace App\Services\Feeds;

use App\Models\Good;
use App\Services\Seo\GoodSeoService;
use XMLWriter;

class YandexDirectFeedService
{
    public function __construct(
        private readonly GoodSeoService $seo,
    ) {}

    public function xml(): string
    {
        $xml = new XMLWriter();

        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');

        $xml->startElement('yml_catalog');
        $xml->writeAttribute('date', now()->format('Y-m-d H:i'));

        $xml->startElement('shop');

        $xml->writeElement('name', config('app.name', 'pischeprom'));
        $xml->writeElement('company', config('app.name', 'pischeprom'));
        $xml->writeElement('url', config('app.url'));

        $xml->startElement('currencies');
        $xml->startElement('currency');
        $xml->writeAttribute('id', 'RUB');
        $xml->writeAttribute('rate', '1');
        $xml->endElement();
        $xml->endElement();

        $categories = $this->categories();

        $xml->startElement('categories');

        foreach ($categories as $id => $name) {
            $xml->startElement('category');
            $xml->writeAttribute('id', (string) $id);
            $xml->text($name);
            $xml->endElement();
        }

        $xml->endElement();

        $xml->startElement('offers');

        Good::query()
            ->where('is_published', true)
            ->with([
                       'seo',
                       'products.category',
                       'publishedMedia',
                       'latestPrice.currency',
                       'priceTypeValues.priceType.currency',
                       'priceTypeValues.currency',
                       'vatRate',
                   ])
            ->whereHas('seo', function ($query) {
                $query
                    ->where('is_active', true)
                    ->where('include_in_yandex_feed', true);
            })
            ->orderBy('id')
            ->chunk(300, function ($goods) use ($xml) {
                foreach ($goods as $good) {
                    $price = $this->seo->price($good);

                    if (!$price || !$good->slug) {
                        continue;
                    }

                    $this->writeOffer($xml, $good, $price);
                }
            });

        $xml->endElement();

        $xml->endElement();
        $xml->endElement();

        $xml->endDocument();

        return $xml->outputMemory();
    }

    private function categories(): array
    {
        $categories = [];

        Good::query()
            ->where('is_published', true)
            ->with(['seo', 'products.category'])
            ->whereHas('seo', function ($query) {
                $query
                    ->where('is_active', true)
                    ->where('include_in_yandex_feed', true);
            })
            ->chunk(300, function ($goods) use (&$categories) {
                foreach ($goods as $good) {
                    $category = $this->seo->category($good);

                    if ($category) {
                        $categories[$category->id] = $category->name;
                    }
                }
            });

        return $categories;
    }

    private function writeOffer(XMLWriter $xml, Good $good, float $price): void
    {
        $categoryId = $this->seo->categoryId($good);
        $image = $this->seo->image($good);

        $xml->startElement('offer');
        $xml->writeAttribute('id', (string) $good->id);
        $xml->writeAttribute('available', $this->seo->availability($good) === 'out_of_stock' ? 'false' : 'true');

        $xml->writeElement('url', $this->seo->publicUrl($good));
        $xml->writeElement('price', number_format($price, 2, '.', ''));
        $xml->writeElement('currencyId', $this->seo->currency($good));

        if ($categoryId) {
            $xml->writeElement('categoryId', (string) $categoryId);
        }

        if ($image) {
            $xml->writeElement('picture', $image);
        }

        $xml->writeElement('name', $good->seo?->yandex_direct_title_1 ?: $good->name);
        $xml->writeElement('description', $this->clean($this->seo->description($good)));

        if ($good->denominator) {
            $this->writeParam($xml, 'Фасовка', "{$good->denominator} кг");
        }

        if ($good->vatRate) {
            $this->writeParam($xml, 'НДС', "{$good->vatRate->rate}%");
        }

        $xml->endElement();
    }

    private function writeParam(XMLWriter $xml, string $name, string $value): void
    {
        $xml->startElement('param');
        $xml->writeAttribute('name', $name);
        $xml->text($value);
        $xml->endElement();
    }

    private function clean(string $value): string
    {
        return trim(strip_tags($value));
    }
}
