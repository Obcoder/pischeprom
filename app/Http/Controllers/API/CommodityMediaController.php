<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\CommodityMediaResource;
use App\Models\Commodity;
use App\Models\CommodityMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CommodityMediaController extends Controller
{
    private function diskName(): string
    {
        return config('filesystems.unit_files_disk', 'yandex');
    }

    public function index(Commodity $commodity)
    {
        return CommodityMediaResource::collection(
            $commodity->media()->get()
        );
    }

    public function store(Request $request, Commodity $commodity)
    {
        $request->validate([
                               'files' => ['required', 'array'],
                               'files.*' => ['required', 'image', 'max:10240'],
                           ]);

        $diskName = $this->diskName();
        $disk = Storage::disk($diskName);

        $created = collect();

        DB::transaction(function () use ($request, $commodity, $disk, $diskName, &$created) {
            foreach ($request->file('files', []) as $file) {
                $extension = strtolower($file->getClientOriginalExtension() ?: 'jpg');
                $filename = Str::uuid() . '.' . $extension;

                $directory = "commodities/{$commodity->id}/media";
                $path = "{$directory}/{$filename}";

                $disk->putFileAs($directory, $file, $filename, [
                    'visibility' => 'public',
                ]);

                $media = CommodityMedia::create([
                                                    'commodity_id' => $commodity->id,
                                                    'disk' => $diskName,
                                                    'path' => $path,
                                                    'filename' => $filename,
                                                    'original_name' => $file->getClientOriginalName(),
                                                    'mime_type' => $file->getMimeType(),
                                                    'size' => $file->getSize(),
                                                    'is_ava' => false,
                                                    'sort_order' => 0,
                                                ]);

                $created->push($media);
            }

            if (!$commodity->ava && $created->isNotEmpty()) {
                $this->makeAva($commodity, $created->first());
            }
        });

        return CommodityMediaResource::collection(
            $commodity->fresh()->media()->get()
        );
    }

    public function destroy(Commodity $commodity, CommodityMedia $media)
    {
        abort_unless($media->commodity_id === $commodity->id, 404);

        $disk = Storage::disk($media->disk ?: $this->diskName());

        $wasAva = $media->is_ava || $commodity->ava === $media->path;

        DB::transaction(function () use ($commodity, $media, $disk, $wasAva) {
            if ($disk->exists($media->path)) {
                $disk->delete($media->path);
            }

            $media->delete();

            if ($wasAva) {
                $next = $commodity->media()
                    ->where('id', '!=', $media->id)
                    ->orderByDesc('created_at')
                    ->first();

                if ($next) {
                    $this->makeAva($commodity, $next);
                } else {
                    $commodity->update([
                                           'ava' => null,
                                       ]);
                }
            }
        });

        return response()->json([
                                    'message' => 'Media deleted',
                                ]);
    }

    public function rename(Request $request, Commodity $commodity, CommodityMedia $media)
    {
        abort_unless($media->commodity_id === $commodity->id, 404);

        $data = $request->validate([
                                       'filename' => ['required', 'string', 'max:255'],
                                   ]);

        $disk = Storage::disk($media->disk ?: $this->diskName());

        $oldPath = $media->path;
        $oldExtension = pathinfo($media->filename, PATHINFO_EXTENSION);

        $baseName = pathinfo($data['filename'], PATHINFO_FILENAME);
        $baseName = Str::slug($baseName);

        if (!$baseName) {
            $baseName = 'commodity-media';
        }

        $newFilename = $baseName . '.' . $oldExtension;
        $newPath = "commodities/{$commodity->id}/media/{$newFilename}";

        if ($newPath !== $oldPath && $disk->exists($newPath)) {
            return response()->json([
                                        'message' => 'Файл с таким именем уже существует.',
                                        'errors' => [
                                            'filename' => [
                                                'Файл с таким именем уже существует.',
                                            ],
                                        ],
                                    ], 422);
        }

        DB::transaction(function () use ($disk, $media, $commodity, $oldPath, $newPath, $newFilename) {
            if ($newPath !== $oldPath) {
                $disk->copy($oldPath, $newPath);
                $disk->delete($oldPath);
            }

            $media->update([
                               'path' => $newPath,
                               'filename' => $newFilename,
                           ]);

            if ($media->is_ava || $commodity->ava === $oldPath) {
                $commodity->update([
                                       'ava' => $newPath,
                                   ]);
            }
        });

        return new CommodityMediaResource($media->fresh());
    }

    public function setAva(Commodity $commodity, CommodityMedia $media)
    {
        abort_unless($media->commodity_id === $commodity->id, 404);

        DB::transaction(function () use ($commodity, $media) {
            $this->makeAva($commodity, $media);
        });

        return response()->json([
                                    'commodity' => $commodity->fresh(['media', 'avaMedia']),
                                    'media' => new CommodityMediaResource($media->fresh()),
                                ]);
    }

    private function makeAva(Commodity $commodity, CommodityMedia $media): void
    {
        CommodityMedia::where('commodity_id', $commodity->id)->update([
                                                                          'is_ava' => false,
                                                                      ]);

        $media->update([
                           'is_ava' => true,
                       ]);

        $commodity->update([
                               'ava' => $media->path,
                           ]);
    }
}
