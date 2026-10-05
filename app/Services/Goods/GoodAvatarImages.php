<?php

namespace App\Services\Goods;

use App\Models\Good;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;
use RuntimeException;

class GoodAvatarImages
{
    public const CACHE_CONTROL = 'public, max-age=31536000, immutable';

    /** Resolve URLs from stored metadata only; a goods list must never contact storage. */
    public function url(Good $good): ?string
    {
        $url = $good->ava_thumb ?: $good->ava_image;
        if (! $url) {
            return null;
        }

        $cdn = rtrim((string) config('goods-media.avatar_cdn_url'), '/');
        $path = $cdn !== '' ? $this->storagePath($url) : null;

        return $path !== null ? $cdn.'/'.$path : $url;
    }

    public function publicUrl(string $path): string
    {
        $base = config('filesystems.disks.yandex.url')
            ?: 'https://storage.yandexcloud.net/'.config('filesystems.disks.yandex.bucket');

        return rtrim($base, '/').'/'.ltrim($path, '/');
    }

    public function storagePath(string $url): ?string
    {
        $prefixes = [
            $this->publicUrl(''),
            'https://storage.yandexcloud.net/'.config('filesystems.disks.yandex.bucket').'/',
        ];

        foreach (array_unique($prefixes) as $prefix) {
            if (str_starts_with($url, $prefix)) {
                $path = explode('?', substr($url, strlen($prefix)), 2)[0];

                return str_starts_with($path, 'goods/') ? $path : null;
            }
        }

        return null;
    }

    public function storeThumbnail(int $goodId, string $imageBytes): string
    {
        $image = (new ImageManager(new Driver))->read($imageBytes);
        $encoded = (string) $image->cover(160, 160)->encode(new JpegEncoder(78));
        // Content-addressed names make long-lived browser/CDN caching safe after edits.
        $path = "goods/{$goodId}/avatars/".hash('sha256', $encoded).'.jpg';

        if (! Storage::disk('yandex')->put($path, $encoded, [
            'ContentType' => 'image/jpeg',
            'CacheControl' => self::CACHE_CONTROL,
        ])) {
            throw new RuntimeException('Не удалось сохранить миниатюру товара.');
        }

        return $this->publicUrl($path);
    }

    public function regenerate(Good $good): string
    {
        $path = $this->storagePath((string) $good->ava_image);
        if ($path === null || ! str_starts_with($path, "goods/{$good->id}/")) {
            throw new RuntimeException('Аватар находится вне каталога товара в Object Storage.');
        }

        $bytes = Storage::disk('yandex')->get(rawurldecode($path));
        if (! is_string($bytes) || $bytes === '') {
            throw new RuntimeException('Исходный аватар не найден в Object Storage.');
        }

        return $this->storeThumbnail($good->id, $bytes);
    }
}
