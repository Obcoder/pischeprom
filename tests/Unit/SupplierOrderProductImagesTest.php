<?php

namespace Tests\Unit;

use App\Models\Good;
use App\Services\SupplierOrders\SupplierOrderProductImages;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SupplierOrderProductImagesTest extends TestCase
{
    public function test_it_reuses_the_product_thumbnail_and_cdn_without_fetching_storage(): void
    {
        config()->set([
            'filesystems.disks.yandex.bucket' => 'goods-test',
            'filesystems.disks.yandex.url' => 'https://objects.example.com/goods-test',
            'goods-media.avatar_cdn_url' => 'https://images.example.com',
        ]);
        Storage::shouldReceive('disk')->never();
        $images = new SupplierOrderProductImages;

        $this->assertSame('https://images.example.com/goods/7/thumb.jpg', $images->url(new Good([
            'ava_thumb' => 'https://storage.yandexcloud.net/goods-test/goods/7/thumb.jpg',
            'ava_image' => 'https://objects.example.com/goods-test/goods/7/original.jpg',
        ])));
        $this->assertSame('https://images.example.com/goods/7/original.jpg', $images->url(new Good([
            'ava_image' => 'https://objects.example.com/goods-test/goods/7/original.jpg',
        ])));
        $this->assertNull($images->url(new Good));
    }

    public function test_it_allows_only_absolute_https_raster_images_without_private_addresses_or_credentials(): void
    {
        $images = new SupplierOrderProductImages;
        foreach ([
            null, [], '', '/goods/7/avatar.jpg', '//example.com/avatar.jpg',
            'http://example.com/avatar.jpg', 'javascript:alert(1)', 'data:image/png;base64,AAAA',
            'file:///tmp/avatar.jpg', 'https://user:secret@example.com/avatar.jpg',
            "https://example.com/\navatar.jpg", 'https://trusted.example.com\\@evil.example.com/avatar.jpg',
            'https://localhost/avatar.jpg', 'https://files.local/avatar.jpg',
            'https://127.0.0.1/avatar.jpg', 'https://10.1.2.3/avatar.jpg', 'https://[::1]/avatar.jpg',
            'https://example.com:8080/avatar.jpg', 'https://example.com/video.mp4',
            'https://example.com/document.pdf', 'https://example.com/vector.svg',
        ] as $value) {
            $this->assertNull($images->safeUrl($value), is_string($value) ? $value : get_debug_type($value));
        }

        foreach (['https://images.example.com/goods/7/avatar.jpg', 'https://example.com:443/avatar.PNG?width=160&height=160'] as $url) {
            $this->assertSame($url, $images->safeUrl($url));
        }
    }
}
