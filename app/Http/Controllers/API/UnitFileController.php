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
                                            'file' => ['required', 'file', 'max:20480'], // 20 MB
                                        ]);

        $disk = Storage::disk('yandex');
        $folder = "units/{$unit->name}";
        $file = $validated['file'];

        $filename = time() . '_' . Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $extension = $file->getClientOriginalExtension();
        $fullName = $extension ? "{$filename}.{$extension}" : $filename;

        $path = $disk->putFileAs($folder, $file, $fullName, ['visibility' => 'public']);

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
}
