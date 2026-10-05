<?php

namespace App\Services\HomeBanners;

use App\Models\HomeBanner;
use App\Models\HomeBannerSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class HomeBannerPublicationService
{
    public function save(array $data, ?HomeBanner $existing = null): HomeBanner
    {
        return DB::transaction(function () use ($data, $existing): HomeBanner {
            $this->lockFeed();
            $banner = $existing
                ? HomeBanner::query()->lockForUpdate()->findOrFail($existing->id)
                : new HomeBanner;
            $banner->fill($data);

            $this->validatePeriod($banner);
            $this->validateImages($banner);
            $this->validateAvailability($banner);
            $banner->save();

            return $banner;
        });
    }

    public function delete(HomeBanner $banner): void
    {
        DB::transaction(function () use ($banner): void {
            $this->lockFeed();
            $banner->delete();
        });
    }

    private function lockFeed(): void
    {
        HomeBannerSetting::singleton();
        HomeBannerSetting::query()->whereKey(1)->lockForUpdate()->firstOrFail();
    }

    private function validatePeriod(HomeBanner $banner): void
    {
        if ($banner->starts_at && $banner->ends_at && $banner->ends_at->lte($banner->starts_at)) {
            throw ValidationException::withMessages([
                'ends_at' => 'Конец публикации должен быть позже её начала.',
            ]);
        }
    }

    private function validateImages(HomeBanner $banner): void
    {
        if (! $banner->is_published || ! $banner->slot_number || $banner->content_mode === 'text') {
            return;
        }

        if ($banner->show_on_desktop && ! $banner->image_url) {
            throw ValidationException::withMessages(['image_url' => 'Для публикации на компьютере загрузите баннер.']);
        }
        if ($banner->show_on_mobile && ! $banner->image_url && ! $banner->mobile_image_url) {
            throw ValidationException::withMessages(['mobile_image_url' => 'Для публикации на телефоне загрузите основной или мобильный баннер.']);
        }
    }

    private function validateAvailability(HomeBanner $banner): void
    {
        if (! $banner->is_published || ! $banner->slot_number || (! $banner->show_on_desktop && ! $banner->show_on_mobile)) {
            return;
        }

        $query = HomeBanner::query()
            ->published()
            ->where('slot_number', $banner->slot_number)
            ->when($banner->exists, fn ($query) => $query->whereKeyNot($banner->id))
            ->where(function ($query) use ($banner): void {
                if ($banner->show_on_desktop) {
                    $query->orWhere('show_on_desktop', true);
                }
                if ($banner->show_on_mobile) {
                    $query->orWhere('show_on_mobile', true);
                }
            });

        // Half-open periods [start, end): a next campaign may start at the exact end.
        if ($banner->starts_at) {
            $query->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', $banner->starts_at));
        }
        if ($banner->ends_at) {
            $query->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<', $banner->ends_at));
        }

        // A current locking read sees concurrent commits after waiting for the feed
        // lock, even if singleton() established a MySQL REPEATABLE READ snapshot.
        if ($conflict = $query->orderBy('id')->lockForUpdate()->first()) {
            throw ValidationException::withMessages([
                'slot_number' => "В этом слоте уже запланирован баннер «{$conflict->title}» на пересекающийся период и устройство. Измените слот, сроки или устройства.",
            ]);
        }
    }
}
