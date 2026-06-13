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
    public function index(Request $request, Unit $unit): JsonResponse
    {
        $disk = Storage::disk('yandex');
        $folder = $this->safeRelativeFolder((string) $request->query('folder', ''));
        $basePath = $this->existingRootPath($unit, $disk);
        $path = $folder ? "{$basePath}/{$folder}" : $basePath;

        $folders = collect($disk->directories($path))
            ->map(fn ($directory) => $this->folderPayload($directory))
            ->values();

        $files = collect($disk->files($path))
            ->filter(fn ($file) => !Str::endsWith($file, ['placeholder.txt', '.keep']))
            ->map(fn ($file) => $this->filePayload($disk, $file))
            ->values();

        return response()->json([
            'root' => $basePath,
            'folder' => $folder,
            'folders' => $folders,
            'files' => $files,
        ]);
    }

    public function store(Request $request, Unit $unit): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:20480'],
            'folder' => ['nullable', 'string', 'max:255'],
        ]);

        $disk = Storage::disk('yandex');
        $folder = $this->safeRelativeFolder($validated['folder'] ?? '');
        $basePath = $this->rootPath($unit);
        $targetFolder = $folder ? "{$basePath}/{$folder}" : $basePath;
        $file = $validated['file'];

        $safeName = $this->sanitizeFileName($file->getClientOriginalName());
        $path = $disk->putFileAs($targetFolder, $file, $safeName);

        return response()->json([
            'message' => 'File uploaded',
            'file' => $this->filePayload($disk, $path),
        ], 201);
    }

    public function storeFolder(Request $request, Unit $unit): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'parent' => ['nullable', 'string', 'max:255'],
        ]);

        $disk = Storage::disk('yandex');
        $parent = $this->safeRelativeFolder($data['parent'] ?? '');
        $name = $this->sanitizeFileName($data['name']);
        $root = $this->rootPath($unit);
        $folder = trim($parent ? "{$parent}/{$name}" : $name, '/');
        $path = "{$root}/{$folder}";

        if ($name === '' || in_array($name, ['placeholder.txt', '.keep'], true)) {
            return response()->json(['message' => 'Invalid folder name'], 422);
        }

        if ($this->pathHasObjects($disk, $path)) {
            return response()->json(['message' => 'Folder already exists'], 422);
        }

        $disk->put("{$path}/.keep", 'created: ' . now()->toIso8601String());

        return response()->json([
            'message' => 'Folder created',
            'folder' => $this->folderPayload($path),
        ], 201);
    }

    public function destroy(Request $request, Unit $unit): JsonResponse
    {
        $validated = $request->validate([
            'path' => ['required', 'string'],
            'type' => ['nullable', 'in:file,folder'],
        ]);

        $disk = Storage::disk('yandex');
        $path = $validated['path'];
        $this->abortUnlessUnitPath($unit, $path);

        if (($validated['type'] ?? 'file') === 'folder') {
            $disk->deleteDirectory($path);
        } elseif ($disk->exists($path)) {
            $disk->delete($path);
        }

        return response()->json([
            'message' => 'Deleted',
        ]);
    }

    public function rename(Request $request, Unit $unit): JsonResponse
    {
        $validated = $request->validate([
            'path' => ['required', 'string'],
            'new_name' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'in:file,folder'],
        ]);

        $disk = Storage::disk('yandex');
        $oldPath = $validated['path'];
        $newName = $this->sanitizeFileName($validated['new_name'] ?? $validated['name'] ?? '');
        $type = $validated['type'] ?? 'file';

        $this->abortUnlessUnitPath($unit, $oldPath);

        if ($newName === '' || $newName === 'placeholder.txt' || $newName === '.keep') {
            return response()->json(['message' => 'Invalid name'], 422);
        }

        $parent = Str::beforeLast($oldPath, '/');
        $newPath = "{$parent}/{$newName}";

        if ($oldPath === $newPath) {
            return response()->json(['message' => 'Name is the same']);
        }

        if ($type === 'folder') {
            $this->moveDirectory($disk, $oldPath, $newPath);

            return response()->json([
                'message' => 'Folder renamed',
                'folder' => $this->folderPayload($newPath),
            ]);
        }

        if (! $disk->exists($oldPath)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        if ($disk->exists($newPath)) {
            return response()->json(['message' => 'A file with this name already exists'], 422);
        }

        $disk->copy($oldPath, $newPath);
        $disk->delete($oldPath);

        return response()->json([
            'message' => 'File renamed',
            'file' => $this->filePayload($disk, $newPath),
        ]);
    }

    public function move(Request $request, Unit $unit): JsonResponse
    {
        $data = $request->validate([
            'path' => ['required', 'string'],
            'target_folder' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'in:file,folder'],
        ]);

        $disk = Storage::disk('yandex');
        $path = $data['path'];
        $targetFolder = $this->safeRelativeFolder($data['target_folder'] ?? '');
        $targetBase = $targetFolder ? $this->rootPath($unit) . '/' . $targetFolder : $this->rootPath($unit);
        $targetPath = $targetBase . '/' . basename($path);

        $this->abortUnlessUnitPath($unit, $path);
        $this->abortUnlessUnitPath($unit, $targetBase);

        if (($data['type'] ?? 'file') === 'folder') {
            $this->moveDirectory($disk, $path, $targetPath);
        } else {
            $disk->copy($path, $targetPath);
            $disk->delete($path);
        }

        return response()->json(['message' => 'Moved']);
    }

    protected function rootPath(Unit $unit): string
    {
        return "units/{$unit->id}";
    }

    protected function legacyRootPath(Unit $unit): string
    {
        return "units/{$unit->name}";
    }

    protected function existingRootPath(Unit $unit, $disk): string
    {
        $root = $this->rootPath($unit);
        $legacy = $this->legacyRootPath($unit);

        if ($this->pathHasObjects($disk, $root) || ! $this->pathHasObjects($disk, $legacy)) {
            return $root;
        }

        return $legacy;
    }

    protected function pathHasObjects($disk, string $path): bool
    {
        return $disk->exists($path)
            || (method_exists($disk, 'directoryExists') && $disk->directoryExists($path))
            || count($disk->files($path)) > 0
            || count($disk->directories($path)) > 0;
    }

    protected function abortUnlessUnitPath(Unit $unit, string $path): void
    {
        $allowed = [
            $this->rootPath($unit) . '/',
            $this->rootPath($unit),
            $this->legacyRootPath($unit) . '/',
            $this->legacyRootPath($unit),
        ];

        abort_unless(
            collect($allowed)->contains(fn ($prefix) => $path === $prefix || Str::startsWith($path, $prefix)),
            403,
            'Invalid path'
        );
    }

    protected function safeRelativeFolder(string $folder): string
    {
        $folder = trim(str_replace('\\', '/', $folder), '/');

        abort_if(Str::contains($folder, ['..', '//']), 422, 'Invalid folder');

        return $folder;
    }

    protected function folderPayload(string $path): array
    {
        return [
            'name' => basename($path),
            'path' => $path,
            'type' => 'folder',
        ];
    }

    protected function filePayload($disk, string $path): array
    {
        return [
            'name' => basename($path),
            'path' => $path,
            'url' => $disk->url($path),
            'size' => $disk->size($path),
            'last_modified' => $disk->lastModified($path),
            'type' => 'file',
            'extension' => pathinfo($path, PATHINFO_EXTENSION),
        ];
    }

    protected function moveDirectory($disk, string $oldPath, string $newPath): void
    {
        foreach ($disk->allFiles($oldPath) as $file) {
            $relative = Str::after($file, rtrim($oldPath, '/') . '/');
            $disk->copy($file, rtrim($newPath, '/') . '/' . $relative);
        }

        $disk->deleteDirectory($oldPath);
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
