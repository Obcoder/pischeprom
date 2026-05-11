<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Good;
use App\Models\GoodMediaFolder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class GoodMediaFolderController extends Controller
{
    public function index(Good $good): JsonResponse
    {
        return response()->json(
            $good->mediaFolders()
                ->with('parent')
                ->orderBy('is_archive')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
        );
    }

    public function store(Request $request, Good $good): JsonResponse
    {
        $validated = $request->validate([
                                            'parent_id' => [
                                                'nullable',
                                                'integer',
                                                'exists:good_media_folders,id',
                                            ],
                                            'name' => [
                                                'required',
                                                'string',
                                                'max:255',
                                            ],
                                            'sort_order' => [
                                                'nullable',
                                                'integer',
                                                'min:0',
                                            ],
                                            'is_archive' => [
                                                'nullable',
                                                'boolean',
                                            ],
                                        ]);

        $parent = null;

        if (!empty($validated['parent_id'])) {
            $parent = GoodMediaFolder::query()
                ->where('good_id', $good->id)
                ->findOrFail($validated['parent_id']);
        }

        $slug = Str::slug($validated['name']) ?: Str::random(8);

        $basePath = $parent
            ? trim($parent->path, '/')
            : "goods/{$good->id}/media";

        $path = "{$basePath}/{$slug}";

        $folder = GoodMediaFolder::create([
                                              'good_id' => $good->id,
                                              'parent_id' => $parent?->id,
                                              'name' => $validated['name'],
                                              'slug' => $slug,
                                              'path' => $path,
                                              'sort_order' => $validated['sort_order'] ?? 100,
                                              'is_archive' => $validated['is_archive'] ?? false,
                                          ]);

        /*
         * В S3/Yandex Object Storage нет настоящих папок.
         * Создаём .keep-файл, чтобы префикс был виден как папка.
         */
        Storage::disk('yandex')->put("{$path}/.keep", '');

        return response()->json(
            $folder->fresh('parent'),
            201
        );
    }

    public function update(Request $request, Good $good, GoodMediaFolder $folder): JsonResponse
    {
        abort_unless($folder->good_id === $good->id, 404);

        $validated = $request->validate([
                                            'name' => [
                                                'sometimes',
                                                'required',
                                                'string',
                                                'max:255',
                                            ],
                                            'sort_order' => [
                                                'nullable',
                                                'integer',
                                                'min:0',
                                            ],
                                            'is_archive' => [
                                                'nullable',
                                                'boolean',
                                            ],
                                        ]);

        $folder->update($validated);

        return response()->json(
            $folder->fresh('parent')
        );
    }

    public function destroy(Good $good, GoodMediaFolder $folder): JsonResponse
    {
        abort_unless($folder->good_id === $good->id, 404);

        if ($folder->media()->exists() || $folder->children()->exists()) {
            return response()->json([
                                        'message' => 'Нельзя удалить папку: в ней есть файлы или вложенные папки.',
                                    ], 422);
        }

        Storage::disk('yandex')->delete("{$folder->path}/.keep");

        $folder->delete();

        return response()->json(null, 204);
    }
}
