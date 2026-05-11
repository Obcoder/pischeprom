<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Good;
use App\Models\GoodMedia;
use App\Models\GoodMediaFolder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;
use App\Jobs\ProcessGoodVideoJob;

class GoodMediaController extends Controller
{
    public function index(Good $good): JsonResponse
    {
        return response()->json(
            $good->media()
                ->with('folder')
                ->orderBy('type')
                ->orderBy('sort_order')
                ->orderByDesc('created_at')
                ->get()
        );
    }

    public function store(Request $request, Good $good): JsonResponse
    {
        $validated = $request->validate([
                                            'folder_id' => [
                                                'nullable',
                                                'integer',
                                                'exists:good_media_folders,id',
                                            ],
                                            'file' => [
                                                'required',
                                                'file',
                                                'max:204800',
                                                'mimetypes:image/jpeg,image/png,image/webp,video/mp4,video/quicktime,video/x-msvideo,video/x-matroska,video/webm,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                            ],
                                            'title' => [
                                                'nullable',
                                                'string',
                                                'max:255',
                                            ],
                                            'alt' => [
                                                'nullable',
                                                'string',
                                                'max:255',
                                            ],
                                            'caption' => [
                                                'nullable',
                                                'string',
                                            ],
                                            'is_published' => [
                                                'nullable',
                                                'boolean',
                                            ],
                                        ]);

        $folder = null;

        if (!empty($validated['folder_id'])) {
            $folder = GoodMediaFolder::query()
                ->where('good_id', $good->id)
                ->findOrFail($validated['folder_id']);
        }

        /** @var UploadedFile $file */
        $file = $request->file('file');

        $mime = (string) $file->getMimeType();

        $type = str_starts_with($mime, 'image/')
            ? 'image'
            : (str_starts_with($mime, 'video/') ? 'video' : 'document');

        $media = match ($type) {
            'image' => $this->storeImage($good, $file, $folder, $validated),
            'video' => $this->storeVideo($good, $file, $folder, $validated),
            default => $this->storeDocument($good, $file, $folder, $validated),
        };

        return response()->json(
            $media->fresh('folder'),
            201
        );
    }

    public function update(Request $request, Good $good, GoodMedia $media): JsonResponse
    {
        abort_unless($media->good_id === $good->id, 404);

        $validated = $request->validate([
                                            'folder_id' => [
                                                'nullable',
                                                'integer',
                                                'exists:good_media_folders,id',
                                            ],
                                            'title' => [
                                                'nullable',
                                                'string',
                                                'max:255',
                                            ],
                                            'alt' => [
                                                'nullable',
                                                'string',
                                                'max:255',
                                            ],
                                            'caption' => [
                                                'nullable',
                                                'string',
                                            ],
                                            'sort_order' => [
                                                'nullable',
                                                'integer',
                                                'min:0',
                                            ],
                                            'is_published' => [
                                                'nullable',
                                                'boolean',
                                            ],
                                        ]);

        if (array_key_exists('folder_id', $validated) && $validated['folder_id']) {
            GoodMediaFolder::query()
                ->where('good_id', $good->id)
                ->findOrFail($validated['folder_id']);
        }

        $media->update($validated);

        return response()->json(
            $media->fresh('folder')
        );
    }

    public function rename(Request $request, Good $good, GoodMedia $media): JsonResponse
    {
        abort_unless($media->good_id === $good->id, 404);

        $validated = $request->validate([
                                            'file_name' => [
                                                'required',
                                                'string',
                                                'max:255',
                                            ],
                                        ]);

        $newBaseName = pathinfo($validated['file_name'], PATHINFO_FILENAME);
        $newBaseName = Str::slug($newBaseName) ?: Str::random(8);

        $extension = $media->extension ?: pathinfo($media->path, PATHINFO_EXTENSION);
        $newFileName = "{$newBaseName}.{$extension}";

        $directory = trim(dirname($media->path), '.');
        $directory = $directory === '/' ? '' : $directory;

        $newPath = trim($directory, '/') . '/' . $newFileName;

        $this->copyAndDelete($media->path, $newPath);

        $newUrl = $this->publicUrl($newPath);

        $media->update([
                           'path' => $newPath,
                           'url' => $newUrl,
                           'file_name' => $newFileName,
                       ]);

        if ($media->is_ava) {
            $good->update([
                              'ava_image' => $newUrl,
                          ]);
        }

        return response()->json(
            $media->fresh('folder')
        );
    }

    public function setAva(Good $good, GoodMedia $media): JsonResponse
    {
        abort_unless($media->good_id === $good->id, 404);
        abort_unless($media->type === 'image', 422);

        GoodMedia::query()
            ->where('good_id', $good->id)
            ->update([
                         'is_ava' => false,
                     ]);

        $media->update([
                           'is_ava' => true,
                           'is_published' => true,
                       ]);

        $good->update([
                          'ava_image' => $media->url,
                          'ava_thumb' => $media->thumb_url ?: $media->url,
                      ]);

        $this->syncGoodJson($good);

        return response()->json(
            $media->fresh('folder')
        );
    }

    public function togglePublish(Request $request, Good $good, GoodMedia $media): JsonResponse
    {
        abort_unless($media->good_id === $good->id, 404);

        $validated = $request->validate([
                                            'is_published' => [
                                                'required',
                                                'boolean',
                                            ],
                                        ]);

        $media->update([
                           'is_published' => $validated['is_published'],
                       ]);

        $this->syncGoodJson($good);

        return response()->json(
            $media->fresh('folder')
        );
    }

    public function destroy(Good $good, GoodMedia $media): JsonResponse
    {
        abort_unless($media->good_id === $good->id, 404);

        $wasAva = $media->is_ava;

        $paths = [
            $media->path,
            $media->thumb_path,
            $media->poster_path,
            $media->video_mp4_path,
            $media->video_hls_path,
        ];

        foreach ($paths as $path) {
            if ($path) {
                Storage::disk($media->disk ?: 'yandex')->delete($path);
            }
        }

        if ($media->video_hls_path) {
            Storage::disk($media->disk ?: 'yandex')->deleteDirectory(dirname($media->video_hls_path));
        }

        $media->delete();

        if ($wasAva) {
            $good->update([
                              'ava_image' => null,
                              'ava_thumb' => null,
                          ]);
        }

        $this->syncGoodJson($good);

        return response()->json(null, 204);
    }

    private function storeImage(
        Good $good,
        UploadedFile $file,
        ?GoodMediaFolder $folder,
        array $data
    ): GoodMedia {
        $disk = Storage::disk('yandex');

        $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
        $baseName = $this->safeBaseName($file);
        $fileName = "{$baseName}.{$extension}";

        $basePath = $folder?->path ?: "goods/{$good->id}/images";
        $path = "{$basePath}/{$fileName}";

        $disk->put($path, file_get_contents($file->getRealPath()), [
            'ContentType' => $file->getMimeType(),
        ]);

        $manager = new ImageManager(new Driver());
        $image = $manager->read($file->getRealPath());

        $thumbPath = "{$basePath}/thumbs/{$baseName}_thumb.jpg";

        $thumb = clone $image;
        $thumb->cover(360, 360);

        $encoded = $thumb->encode(new JpegEncoder(78));

        $disk->put($thumbPath, (string) $encoded, [
            'ContentType' => 'image/jpeg',
        ]);

        return GoodMedia::create([
                                     'good_id' => $good->id,
                                     'folder_id' => $folder?->id,
                                     'type' => 'image',
                                     'disk' => 'yandex',

                                     'path' => $path,
                                     'url' => $this->publicUrl($path),

                                     'thumb_path' => $thumbPath,
                                     'thumb_url' => $this->publicUrl($thumbPath),

                                     'original_name' => $file->getClientOriginalName(),
                                     'file_name' => $fileName,
                                     'mime_type' => $file->getMimeType(),
                                     'size' => $file->getSize(),
                                     'extension' => $extension,

                                     'title' => $data['title'] ?? null,
                                     'alt' => $data['alt'] ?? $good->name,
                                     'caption' => $data['caption'] ?? null,

                                     'is_published' => $data['is_published'] ?? false,
                                     'is_processed' => true,
                                     'processing_status' => 'done',

                                     'width' => $image->width(),
                                     'height' => $image->height(),
                                 ]);
    }

    private function storeVideo(
        Good $good,
        UploadedFile $file,
        ?GoodMediaFolder $folder,
        array $data
    ): GoodMedia {
        $disk = Storage::disk('yandex');

        $extension = strtolower($file->getClientOriginalExtension() ?: 'mp4');
        $baseName = $this->safeBaseName($file);
        $fileName = "{$baseName}.{$extension}";

        $basePath = $folder?->path ?: "goods/{$good->id}/videos/originals";
        $path = "{$basePath}/{$fileName}";

        $disk->put($path, file_get_contents($file->getRealPath()), [
            'ContentType' => $file->getMimeType(),
        ]);

        return GoodMedia::create([
                                     'good_id' => $good->id,
                                     'folder_id' => $folder?->id,
                                     'type' => 'video',
                                     'disk' => 'yandex',

                                     'path' => $path,
                                     'url' => $this->publicUrl($path),

                                     'original_name' => $file->getClientOriginalName(),
                                     'file_name' => $fileName,
                                     'mime_type' => $file->getMimeType(),
                                     'size' => $file->getSize(),
                                     'extension' => $extension,

                                     'title' => $data['title'] ?? null,
                                     'alt' => $data['alt'] ?? $good->name,
                                     'caption' => $data['caption'] ?? null,

                                     'is_published' => false,
                                     'is_processed' => false,
                                     'processing_status' => 'pending',
                                 ]);
    }

    public function processVideo(Good $good, GoodMedia $media): JsonResponse
    {
        abort_unless($media->good_id === $good->id, 404);
        abort_unless($media->type === 'video', 422);

        $media->update([
                           'processing_status' => 'queued',
                           'processing_error' => null,
                       ]);

        ProcessGoodVideoJob::dispatch($media->id);

        return response()->json(
            $media->fresh('folder')
        );
    }

    private function storeDocument(
        Good $good,
        UploadedFile $file,
        ?GoodMediaFolder $folder,
        array $data
    ): GoodMedia {
        $disk = Storage::disk('yandex');

        $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $baseName = $this->safeBaseName($file);
        $fileName = "{$baseName}.{$extension}";

        $basePath = $folder?->path ?: "goods/{$good->id}/documents";
        $path = "{$basePath}/{$fileName}";

        $disk->put($path, file_get_contents($file->getRealPath()), [
            'ContentType' => $file->getMimeType(),
        ]);

        return GoodMedia::create([
                                     'good_id' => $good->id,
                                     'folder_id' => $folder?->id,
                                     'type' => 'document',
                                     'disk' => 'yandex',

                                     'path' => $path,
                                     'url' => $this->publicUrl($path),

                                     'original_name' => $file->getClientOriginalName(),
                                     'file_name' => $fileName,
                                     'mime_type' => $file->getMimeType(),
                                     'size' => $file->getSize(),
                                     'extension' => $extension,

                                     'title' => $data['title'] ?? null,
                                     'alt' => $data['alt'] ?? null,
                                     'caption' => $data['caption'] ?? null,

                                     'is_published' => $data['is_published'] ?? false,
                                     'is_processed' => true,
                                     'processing_status' => 'done',
                                 ]);
    }

    private function safeBaseName(UploadedFile $file): string
    {
        $original = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $base = Str::slug($original) ?: 'media';

        return $base . '-' . now()->format('YmdHis') . '-' . Str::random(6);
    }

    private function publicUrl(string $path): string
    {
        $bucket = config('filesystems.disks.yandex.bucket');

        return "https://storage.yandexcloud.net/{$bucket}/{$path}";
    }

    private function copyAndDelete(string $from, string $to): void
    {
        $disk = Storage::disk('yandex');

        if ($from === $to) {
            return;
        }

        $disk->copy($from, $to);
        $disk->delete($from);
    }

    private function syncGoodJson(Good $good): void
    {
        Storage::disk('yandex')->put(
            "goods/{$good->id}/good.json",
            $good->fresh([
                             'products.category',
                             'vatRate',
                             'seo',
                             'publishedMedia',
                             'priceTypeValues.priceType',
                             'priceTypeValues.currency',
                         ])->toJson(JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );
    }
}
