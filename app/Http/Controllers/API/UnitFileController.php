<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UnitFileController extends Controller
{
    public function index(Unit $unit): JsonResponse
    {
        $path = "units/{$unit->name}";
        $disk = Storage::disk('yandex');

        $files = collect($disk->files($path))
            ->filter(fn ($file) => !Str::endsWith($file, 'placeholder.txt'))
            ->map(function ($file) use ($disk) {
                return [
                    'name' => basename($file),
                    'path' => $file,
                    'url' => $disk->url($file),
                    'size' => $disk->size($file),
                    'last_modified' => $disk->lastModified($file),
                ];
            })
            ->values();

        return response()->json($files);
    }

    public function store(Request $request, Unit $unit): JsonResponse
    {
        $validated = $request->validate([
                                            'file' => ['required', 'file', 'max:20480'],
                                        ]);

        $disk = Storage::disk('yandex');
        $folder = "units/{$unit->name}";
        $file = $validated['file'];

        $originalName = $file->getClientOriginalName();
        $safeName = $this->sanitizeFileName($originalName);

        $path = $disk->putFileAs($folder, $file, $safeName);

        return response()->json([
                                    'message' => 'File uploaded',
                                    'file' => [
                                        'name' => basename($path),
                                        'path' => $path,
                                        'url' => $disk->url($path),
                                        'size' => $disk->size($path),
                                        'last_modified' => $disk->lastModified($path),
                                    ],
                                ], 201);
    }

    public function destroy(Request $request, Unit $unit): JsonResponse
    {
        $validated = $request->validate([
                                            'path' => ['required', 'string'],
                                        ]);

        $disk = Storage::disk('yandex');
        $path = $validated['path'];

        $expectedPrefix = "units/{$unit->name}/";

        abort_unless(Str::startsWith($path, $expectedPrefix), 403, 'Invalid file path');

        if ($disk->exists($path)) {
            $disk->delete($path);
        }

        return response()->json([
                                    'message' => 'File deleted',
                                ]);
    }

    public function rename(Request $request, Unit $unit): JsonResponse
    {
        $validated = $request->validate([
                                            'path' => ['required', 'string'],
                                            'new_name' => ['required', 'string', 'max:255'],
                                        ]);

        $disk = Storage::disk('yandex');
        $oldPath = $validated['path'];
        $newName = $this->sanitizeFileName($validated['new_name']);

        $expectedPrefix = "units/{$unit->name}/";
        abort_unless(Str::startsWith($oldPath, $expectedPrefix), 403, 'Invalid file path');

        if (! $disk->exists($oldPath)) {
            return response()->json([
                                        'message' => 'File not found',
                                    ], 404);
        }

        if ($newName === 'placeholder.txt') {
            return response()->json([
                                        'message' => 'Invalid file name',
                                    ], 422);
        }

        $newPath = $expectedPrefix . $newName;

        if ($oldPath === $newPath) {
            return response()->json([
                                        'message' => 'File name is the same',
                                    ]);
        }

        if ($disk->exists($newPath)) {
            return response()->json([
                                        'message' => 'A file with this name already exists',
                                    ], 422);
        }

        $copied = $disk->copy($oldPath, $newPath);

        if (! $copied) {
            return response()->json([
                                        'message' => 'Failed to rename file',
                                    ], 500);
        }

        $disk->delete($oldPath);

        return response()->json([
                                    'message' => 'File renamed',
                                    'file' => [
                                        'name' => basename($newPath),
                                        'path' => $newPath,
                                        'url' => $disk->url($newPath),
                                        'size' => $disk->size($newPath),
                                        'last_modified' => $disk->lastModified($newPath),
                                    ],
                                ]);
    }

    protected function sanitizeFileName(string $name): string
    {
        $name = trim($name);
        $name = str_replace(['\\', '/'], '-', $name);
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name);
        $name = preg_replace('/\s+/', ' ', $name);

        return $name;
    }
}
