<?php

namespace Tests\Unit;

use App\Models\Good;
use App\Services\Goods\GoodAvatarImages;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GoodAvatarImagesTest extends TestCase
{
    public function test_thumbnail_upload_sets_immutable_cache_headers_and_changes_url_with_content(): void
    {
        config()->set('filesystems.disks.yandex.url', 'https://storage.yandexcloud.net/goods-test');
        $uploads = [];
        Storage::shouldReceive('disk')->with('yandex')->andReturnSelf();
        Storage::shouldReceive('put')->twice()->andReturnUsing(function ($path, $contents, $options) use (&$uploads) {
            $uploads[] = compact('path', 'contents', 'options');

            return true;
        });

        $image = imagecreatetruecolor(640, 480);
        imagefill($image, 0, 0, imagecolorallocate($image, 200, 30, 40));
        ob_start();
        imagejpeg($image);
        $red = ob_get_clean();
        imagedestroy($image);
        $blackFile = UploadedFile::fake()->image('black.jpg', 640, 480);
        $black = file_get_contents($blackFile->getRealPath());
        $images = new GoodAvatarImages;

        $this->assertNotSame($images->storeThumbnail(7, $red), $images->storeThumbnail(7, $black));
        foreach ($uploads as $upload) {
            $this->assertStringStartsWith('goods/7/avatars/', $upload['path']);
            $this->assertSame('public, max-age=31536000, immutable', $upload['options']['CacheControl']);
            $this->assertSame('image/jpeg', $upload['options']['ContentType']);
        }
    }

    public function test_cdn_only_rewrites_urls_from_the_configured_bucket(): void
    {
        config()->set([
            'filesystems.disks.yandex.bucket' => 'goods-test',
            'filesystems.disks.yandex.url' => 'https://objects.example.test/goods-test',
            'goods-media.avatar_cdn_url' => 'https://images.example.test',
        ]);
        $images = new GoodAvatarImages;

        $this->assertSame('https://images.example.test/goods/7/thumb.jpg', $images->url(new Good([
            'ava_thumb' => 'https://storage.yandexcloud.net/goods-test/goods/7/thumb.jpg',
            'ava_image' => 'https://external.example.test/original.jpg',
        ])));
        $this->assertSame('https://external.example.test/original.jpg', $images->url(new Good([
            'ava_image' => 'https://external.example.test/original.jpg',
        ])));
        $this->assertNull($images->url(new Good));
        $this->assertNull($images->storagePath('https://storage.yandexcloud.net/another-bucket/goods/7/thumb.jpg'));
    }
}
