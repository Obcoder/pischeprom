<?php

namespace App\Services\HomeBanners;

use App\Models\HomeBanner;
use App\Models\HomeBannerSetting;
use App\Rules\HomeBannerUrl;
use App\Services\Catalog\CatalogSiteContext;
use Illuminate\Support\Facades\Schema;

class HomeBannerFeedService
{
    public const SLOT_COUNT = 6;

    public const TIMEZONE = 'Europe/Moscow';

    public function build(): array
    {
        $settings = Schema::hasTable('home_banner_settings')
            ? (HomeBannerSetting::query()->find(1)?->settingsPayload() ?? HomeBannerSetting::DEFAULTS)
            : HomeBannerSetting::DEFAULTS;
        $desktop = array_fill(0, self::SLOT_COUNT, null);
        $mobile = array_fill(0, self::SLOT_COUNT, null);

        if ($settings['enabled'] && Schema::hasColumn('home_banners', 'slot_number')) {
            $banners = HomeBanner::query()
                ->published()
                ->active()
                ->whereBetween('slot_number', [1, self::SLOT_COUNT])
                ->when(app(CatalogSiteContext::class)->isScoped(), function ($query): void {
                    foreach (['good', 'product', 'category'] as $type) {
                        $query->where(fn ($linked) => $linked->whereNull($type.'_id')
                            ->orWhereHas($type, fn ($entity) => app(CatalogSiteContext::class)->scopeEntities($entity, $type)));
                    }
                })
                ->with(self::relations(public: true))
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            foreach ($banners as $banner) {
                $index = $banner->slot_number - 1;
                if ($banner->show_on_desktop && $desktop[$index] === null) {
                    $desktop[$index] = $this->publicBanner($banner);
                }
                if ($settings['mobile_enabled'] && $banner->show_on_mobile && $mobile[$index] === null) {
                    $mobile[$index] = $this->publicBanner($banner);
                }
            }
        }

        return [
            'settings' => $settings,
            'desktop' => $desktop,
            'mobile' => $mobile,
        ];
    }

    public static function specification(): array
    {
        return [
            'slot_count' => self::SLOT_COUNT,
            'desktop' => ['width' => 800, 'height' => 400],
            'mobile' => ['width' => 800, 'height' => 400],
            'timezone' => self::TIMEZONE,
        ];
    }

    public static function relations(bool $public = false): array
    {
        $goodColumns = ['id', 'name', 'slug', 'ava_image'];
        if (Schema::hasColumn('goods', 'ava_thumb')) {
            $goodColumns[] = 'ava_thumb';
        }

        return [
            'good' => fn ($query) => $query->select($goodColumns)->when($public, fn ($query) => $query->where('is_published', true)),
            'product' => fn ($query) => $query->without('manufacturers')->select(['id', 'rus', 'eng', 'category_id'])->when($public, fn ($query) => $query->where('is_published', true)),
            'product.category' => fn ($query) => $query->select(['id', 'name', 'slug'])->when($public, fn ($query) => $query->where('is_published', true)),
            'category' => fn ($query) => $query->select(['id', 'name', 'slug'])->when($public, fn ($query) => $query->where('is_published', true)),
        ];
    }

    private function publicBanner(HomeBanner $banner): array
    {
        $payload = $banner->only([
            'id', 'slot_number', 'title', 'eyebrow', 'subtitle', 'description',
            'image_url', 'mobile_image_url', 'cta_label', 'cta_url',
            'background_color', 'text_color', 'accent_color', 'content_mode',
            'image_fit', 'image_position', 'mobile_image_fit', 'mobile_image_position',
            'text_align', 'vertical_align', 'alt_text', 'open_in_new_tab',
        ]);

        foreach (['image_url', 'mobile_image_url', 'cta_url'] as $field) {
            if (! HomeBannerUrl::isSafe($payload[$field])) {
                $payload[$field] = null;
            }
        }

        $payload['good'] = $banner->good?->only(['id', 'name', 'slug', 'ava_image', 'ava_thumb']);
        $payload['product'] = $banner->product?->only(['id', 'rus', 'eng', 'category_id']);
        if ($payload['product']) {
            $payload['product']['category'] = $banner->product->category?->only(['id', 'name', 'slug']);
        }
        $payload['category'] = $banner->category?->only(['id', 'name', 'slug']);

        return $payload;
    }
}
