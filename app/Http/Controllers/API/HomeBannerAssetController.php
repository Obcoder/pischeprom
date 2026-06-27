<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HomeBannerAssetController extends Controller
{
    private const ROOT = 'banners';

    private const KEEP_FILE = '.keep';

    public function index(Request $request): JsonResponse
    {
        $disk = Storage::disk('yandex');
        $folder = $this->safeRelativeFolder((string) $request->query('folder', ''));
        $path = $this->absoluteFolder($folder);

        $folders = collect($disk->directories($path))
            ->map(fn (string $directory) => $this->folderPayload($directory))
            ->sortBy(fn (array $folder) => mb_strtolower($folder['name']))
            ->values();

        $files = collect($disk->files($path))
            ->reject(fn (string $file) => Str::endsWith($file, '/' . self::KEEP_FILE))
            ->map(fn (string $file) => $this->filePayload($disk, $file))
            ->sortBy(fn (array $file) => mb_strtolower($file['name']))
            ->values();

        return response()->json([
            'root' => self::ROOT,
            'folder' => $folder,
            'path' => $path,
            'folders' => $folders,
            'files' => $files,
        ]);
    }

    public function upload(Request $request): JsonResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,gif,svg', 'max:25600'],
            'folder' => ['nullable', 'string', 'max:255'],
        ]);

        $disk = Storage::disk('yandex');
        $folder = $this->safeRelativeFolder($data['folder'] ?? '');
        $targetFolder = $this->absoluteFolder($folder);
        /** @var UploadedFile $file */
        $file = $data['file'];
        $safeName = $this->uniqueFileName($disk, $targetFolder, $file->getClientOriginalName());
        $path = $disk->putFileAs($targetFolder, $file, $safeName, ['visibility' => 'public']);

        return response()->json([
            'message' => 'Asset uploaded',
            'file' => $this->filePayload($disk, $path),
        ], 201);
    }

    public function storeFolder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'parent' => ['nullable', 'string', 'max:255'],
        ]);

        $disk = Storage::disk('yandex');
        $parent = $this->safeRelativeFolder($data['parent'] ?? '');
        $name = $this->sanitizeName($data['name']);
        $folder = trim($parent ? "{$parent}/{$name}" : $name, '/');
        $path = $this->absoluteFolder($folder);

        if (! $this->isAllowedName($name)) {
            return response()->json(['message' => 'Invalid folder name'], 422);
        }

        if ($this->pathHasObjects($disk, $path)) {
            return response()->json(['message' => 'Folder already exists'], 422);
        }

        $disk->put("{$path}/" . self::KEEP_FILE, 'created: ' . now()->toIso8601String(), ['visibility' => 'public']);

        return response()->json([
            'message' => 'Folder created',
            'folder' => $this->folderPayload($path),
        ], 201);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'path' => ['required', 'string'],
            'type' => ['nullable', 'in:file,folder'],
        ]);

        $disk = Storage::disk('yandex');
        $path = $this->safeStoragePath($data['path']);
        $type = $data['type'] ?? 'file';

        if ($type === 'folder') {
            if ($path === self::ROOT) {
                return response()->json(['message' => 'Root folder cannot be deleted'], 422);
            }

            $disk->deleteDirectory($path);
        } elseif ($disk->exists($path)) {
            $disk->delete($path);
        }

        return response()->json(['message' => 'Asset deleted']);
    }

    public function rename(Request $request): JsonResponse
    {
        $data = $request->validate([
            'path' => ['required', 'string'],
            'new_name' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'in:file,folder'],
        ]);

        $disk = Storage::disk('yandex');
        $oldPath = $this->safeStoragePath($data['path']);
        $newName = $this->sanitizeName($data['new_name'] ?? $data['name'] ?? '');
        $type = $data['type'] ?? 'file';

        if ($oldPath === self::ROOT) {
            return response()->json(['message' => 'Root folder cannot be renamed'], 422);
        }

        if (! $this->isAllowedName($newName)) {
            return response()->json(['message' => 'Invalid name'], 422);
        }

        $parent = Str::beforeLast($oldPath, '/');
        $newPath = $this->safeStoragePath("{$parent}/{$newName}");

        if ($oldPath === $newPath) {
            return response()->json(['message' => 'Name is the same']);
        }

        if ($type === 'folder') {
            if (! $this->pathHasObjects($disk, $oldPath)) {
                return response()->json(['message' => 'Folder not found'], 404);
            }

            if ($this->pathHasObjects($disk, $newPath)) {
                return response()->json(['message' => 'Folder already exists'], 422);
            }

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
            return response()->json(['message' => 'File already exists'], 422);
        }

        $disk->copy($oldPath, $newPath);
        $disk->delete($oldPath);

        return response()->json([
            'message' => 'File renamed',
            'file' => $this->filePayload($disk, $newPath),
        ]);
    }

    public function move(Request $request): JsonResponse
    {
        $data = $request->validate([
            'path' => ['required', 'string'],
            'target_folder' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'in:file,folder'],
        ]);

        $disk = Storage::disk('yandex');
        $path = $this->safeStoragePath($data['path']);
        $targetFolder = $this->safeRelativeFolder($data['target_folder'] ?? '');
        $targetBase = $this->absoluteFolder($targetFolder);
        $type = $data['type'] ?? 'file';
        $targetPath = $this->safeStoragePath($targetBase . '/' . basename($path));

        if ($path === self::ROOT) {
            return response()->json(['message' => 'Root folder cannot be moved'], 422);
        }

        if ($path === $targetPath) {
            return response()->json(['message' => 'Asset is already in this folder']);
        }

        if ($type === 'folder') {
            if (Str::startsWith($targetBase . '/', rtrim($path, '/') . '/')) {
                return response()->json(['message' => 'Folder cannot be moved into itself'], 422);
            }

            if (! $this->pathHasObjects($disk, $path)) {
                return response()->json(['message' => 'Folder not found'], 404);
            }

            if ($this->pathHasObjects($disk, $targetPath)) {
                return response()->json(['message' => 'Target folder already exists'], 422);
            }

            $this->moveDirectory($disk, $path, $targetPath);

            return response()->json([
                'message' => 'Folder moved',
                'folder' => $this->folderPayload($targetPath),
            ]);
        }

        if (! $disk->exists($path)) {
            return response()->json(['message' => 'File not found'], 404);
        }

        if ($disk->exists($targetPath)) {
            return response()->json(['message' => 'Target file already exists'], 422);
        }

        $disk->copy($path, $targetPath);
        $disk->delete($path);

        return response()->json([
            'message' => 'File moved',
            'file' => $this->filePayload($disk, $targetPath),
        ]);
    }

    private function absoluteFolder(string $folder): string
    {
        $folder = $this->safeRelativeFolder($folder);

        return $folder ? self::ROOT . '/' . $folder : self::ROOT;
    }

    private function safeRelativeFolder(string $folder): string
    {
        $folder = trim(str_replace('\\', '/', $folder), '/');

        if ($folder === self::ROOT) {
            return '';
        }

        if (Str::startsWith($folder, self::ROOT . '/')) {
            $folder = Str::after($folder, self::ROOT . '/');
        }

        abort_if(Str::contains($folder, ['..', '//']), 422, 'Invalid folder');

        return trim($folder, '/');
    }

    private function safeStoragePath(string $path): string
    {
        $path = trim(str_replace('\\', '/', $path), '/');

        abort_if(Str::contains($path, ['..', '//']), 422, 'Invalid path');
        abort_unless($path === self::ROOT || Str::startsWith($path, self::ROOT . '/'), 403, 'Invalid path');

        return $path;
    }

    private function folderPayload(string $path): array
    {
        $relativePath = ltrim(Str::after($path, self::ROOT), '/');

        return [
            'name' => basename($path),
            'path' => $path,
            'relative_path' => $relativePath,
            'parent' => Str::contains($relativePath, '/') ? Str::beforeLast($relativePath, '/') : '',
            'type' => 'folder',
        ];
    }

    private function filePayload($disk, string $path): array
    {
        $relativePath = ltrim(Str::after($path, self::ROOT), '/');
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return [
            'name' => basename($path),
            'path' => $path,
            'relative_path' => $relativePath,
            'folder' => Str::contains($relativePath, '/') ? Str::beforeLast($relativePath, '/') : '',
            'url' => $this->publicUrl($path),
            'size' => $this->safeSize($disk, $path),
            'last_modified' => $this->safeLastModified($disk, $path),
            'type' => 'file',
            'extension' => $extension,
            'is_image' => in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'], true),
        ];
    }

    private function pathHasObjects($disk, string $path): bool
    {
        return $disk->exists($path)
            || (method_exists($disk, 'directoryExists') && $disk->directoryExists($path))
            || count($disk->allFiles($path)) > 0
            || count($disk->directories($path)) > 0;
    }

    private function moveDirectory($disk, string $oldPath, string $newPath): void
    {
        foreach ($disk->allFiles($oldPath) as $file) {
            $relative = Str::after($file, rtrim($oldPath, '/') . '/');
            $disk->copy($file, rtrim($newPath, '/') . '/' . $relative);
        }

        $disk->deleteDirectory($oldPath);
    }

    private function uniqueFileName($disk, string $folder, string $originalName): string
    {
        $safeName = $this->sanitizeName($originalName);

        if (! $this->isAllowedName($safeName)) {
            $safeName = 'banner-' . now()->format('YmdHis') . '.jpg';
        }

        $base = pathinfo($safeName, PATHINFO_FILENAME) ?: 'banner';
        $extension = pathinfo($safeName, PATHINFO_EXTENSION);
        $candidate = $safeName;
        $counter = 1;

        while ($disk->exists(rtrim($folder, '/') . '/' . $candidate)) {
            $suffix = now()->format('YmdHis') . ($counter > 1 ? "-{$counter}" : '');
            $candidate = $extension ? "{$base}-{$suffix}.{$extension}" : "{$base}-{$suffix}";
            $counter++;
        }

        return $candidate;
    }

    private function sanitizeName(string $name): string
    {
        $name = trim($name);
        $name = str_replace(['\\', '/'], '-', $name);
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name);
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $base = pathinfo($name, PATHINFO_FILENAME);
        $slug = Str::slug($base);

        if ($slug === '') {
            $slug = trim((string) preg_replace('/[^\pL\pN._-]+/u', '-', $base), '.-_');
        }

        if ($slug === '') {
            $slug = 'asset';
        }

        $extension = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '', $extension));

        return $extension ? "{$slug}.{$extension}" : $slug;
    }

    private function isAllowedName(string $name): bool
    {
        return $name !== ''
            && $name !== '.'
            && $name !== '..'
            && $name !== self::KEEP_FILE
            && ! Str::contains($name, ['/']);
    }

    private function publicUrl(string $path): string
    {
        $baseUrl = config('filesystems.disks.yandex.url');

        if ($baseUrl) {
            return rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
        }

        return Storage::disk('yandex')->url($path);
    }

    private function safeSize($disk, string $path): ?int
    {
        try {
            return $disk->size($path);
        } catch (\Throwable) {
            return null;
        }
    }

    private function safeLastModified($disk, string $path): ?int
    {
        try {
            return $disk->lastModified($path);
        } catch (\Throwable) {
            return null;
        }
    }
}
