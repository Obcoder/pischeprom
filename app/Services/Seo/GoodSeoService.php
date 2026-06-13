<?php

namespace App\Services\Seo;

use App\Models\Good;
use Illuminate\Support\Str;

class GoodSeoService
{
    public function publicUrl(Good $good): string
    {
        return route('public.goods.show', [
            'good' => $good->slug,
        ]);
    }

    public function title(Good $good): string
    {
        return $good->seo?->meta_title
            ?: "{$good->name} купить оптом для пищевой промышленности";
    }

    public function description(Good $good): string
    {
        $description = $good->seo?->meta_description
            ?: $good->description
                ?: "{$good->name}: оптовые поставки для пищевой промышленности, HoReCa, производств и дистрибьюторов.";

        return Str::limit(trim(strip_tags($description)), 160, '');
    }

    public function h1(Good $good): string
    {
        return $good->seo?->h1 ?: $good->name;
    }

    public function canonical(Good $good): string
    {
        return $good->seo?->canonical_url ?: $this->publicUrl($good);
    }

    public function robots(Good $good): string
    {
        if (!$good->is_published) {
            return 'noindex,nofollow';
        }

        if ($good->seo && !$good->seo->is_active) {
            return 'noindex,follow';
        }

        return $good->seo?->robots ?: 'index,follow';
    }

    public function image(Good $good): ?string
    {
        if ($good->seo?->og_image) {
            return $good->seo->og_image;
        }

        $media = $good->publishedMedia
            ->where('type', 'image')
            ->sortByDesc('is_ava')
            ->first();

        return $media?->url
            ?: $good->ava_image
                ?: $good->ava_thumb;
    }

    public function category(Good $good): ?object
    {
        return $good->products
            ->pluck('category')
            ->filter()
            ->first();
    }

    public function categoryTitle(Good $good): ?string
    {
        return $this->category($good)?->name;
    }

    public function categoryId(Good $good): ?int
    {
        return $this->category($good)?->id;
    }

    public function price(Good $good): ?float
    {
        $priceTypeValue = $good->priceTypeValues
            ->where('is_published', true)
            ->sortByDesc('updated_at')
            ->first();

        $value = $priceTypeValue?->price_gross
            ?? $priceTypeValue?->price_net;

        return is_numeric($value) && (float) $value > 0
            ? (float) $value
            : null;
    }

    public function currency(Good $good): string
    {
        $priceTypeValue = $good->priceTypeValues
            ->where('is_published', true)
            ->sortByDesc('updated_at')
            ->first();

        return $priceTypeValue?->currency?->code
            ?: $priceTypeValue?->priceType?->currency?->code
                ?: 'RUB';
    }

    public function availability(Good $good): string
    {
        return $good->seo?->availability_status ?: 'on_request';
    }

    public function schemaAvailability(Good $good): string
    {
        return match ($this->availability($good)) {
            'in_stock' => 'https://schema.org/InStock',
            'preorder' => 'https://schema.org/PreOrder',
            'out_of_stock' => 'https://schema.org/OutOfStock',
            default => 'https://schema.org/PreOrder',
        };
    }
}
