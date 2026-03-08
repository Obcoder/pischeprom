<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Good;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\ImageManager;

class GoodController extends Controller
{
    public function index(Request $request)
    {
        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('per_page', 25);
        $search = trim((string) $request->input('search', ''));
        $isPublished = $request->input('is_published');
        $sortBy = (string) $request->input('sort_by', 'name');
        $sortDesc = filter_var($request->input('sort_desc', false), FILTER_VALIDATE_BOOLEAN);
        $allowedSorts = [
            'name',
            'is_published',
            'created_at'
        ];
        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'name';
        }

        $query = Good::query()
            ->with([
                       'products.category',
                   ]);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('denominator', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (!is_null($isPublished) && $isPublished !== '') {
            $query->where('is_published', filter_var($isPublished, FILTER_VALIDATE_BOOLEAN));
        }

        $query->orderBy($sortBy, $sortDesc ? 'desc' : 'asc');

        $paginator = $query->paginate(
            perPage: $perPage,
            columns: ['*'],
            pageName: 'page',
            page: $page
        );

        return response()->json([
                                    'data' => $paginator->items(),
                                    'total' => $paginator->total(),
                                    'page' => $paginator->currentPage(),
                                    'per_page' => $paginator->perPage(),
                                    'last_page' => $paginator->lastPage(),
                                ]);
    }

    public function indexPublished()
    {
        return Good::query()
            ->published(true)
            ->inRandomOrder()
            ->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
                                            'name' => ['required','string','max:255'],
                                            'denominator' => ['nullable','numeric'],
                                            'description' => ['nullable','string'],
                                            'is_published' => ['nullable','boolean'],
                                            'ava_image' => ['nullable','image','mimes:jpg,jpeg,png,webp','max:4096'],
                                            'products' => ['nullable','array'],
                                            'products.*' => ['integer','exists:products,id'],
                                        ]);

        $good = Good::create([
                                 'name' => $validated['name'],
                                 'denominator' => $validated['denominator'] ?? null,
                                 'description' => $validated['description'] ?? null,
                                 'is_published' => $validated['is_published'] ?? true,
                             ]);

        if (!empty($validated['products'])) {
            $good->products()->sync($validated['products']);
        }

        if ($request->hasFile('ava_image')) {
            $this->storeAvatarFiles($good, $request->file('ava_image'));
        }

        Storage::disk('yandex')->put(
            "goods/{$good->id}/good.json",
            $good->fresh()->toJson(JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );

        return response()->json(
            $good->fresh()->load('products'),
            201
        );
    }

    public function show(string $id, $slug = null)
    {
        $good = Good::with([
                               'prices.currency',
                               'quotations.unit',
                               'quotations.measure',
                               'sales'
                           ])->findOrFail($id);

        $expectedSlug = \Str::slug($good->name);

        if ($slug && $slug !== $expectedSlug) {
            return redirect()->route('goods.show', ['id' => $id, 'slug' => $expectedSlug], 301);
        }

        return $good;
    }

    public function update(Request $request, Good $good)
    {
        $validated = $request->validate([
                                            'name' => ['sometimes','required','string','max:255'],
                                            'denominator' => ['nullable','numeric'],
                                            'description' => ['nullable','string'],
                                            'is_published' => ['nullable','boolean'],
                                            'ava_image' => ['nullable','image','mimes:jpg,jpeg,png,webp','max:4096'],
                                            'products' => ['nullable','array'],
                                            'products.*' => ['integer','exists:products,id'],
                                            'remove_ava' => ['nullable','boolean'],
                                        ]);

        $dataToUpdate = collect($validated)
            ->except(['ava_image', 'products', 'remove_ava'])
            ->toArray();

        $good->update($dataToUpdate);

        if (array_key_exists('products', $validated)) {
            $good->products()->sync($validated['products'] ?? []);
        }

        if (!empty($validated['remove_ava'])) {
            $this->deleteAvatarFiles($good);
            $good->update([
                              'ava_image' => null,
                              'ava_thumb' => null,
                          ]);
        }

        if ($request->hasFile('ava_image')) {
            $this->deleteAvatarFiles($good);
            $this->storeAvatarFiles($good, $request->file('ava_image'));
        }

        Storage::disk('yandex')->put(
            "goods/{$good->id}/good.json",
            $good->fresh()->toJson(JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );

        return response()->json($good->fresh()->load('products'));
    }

    public function destroy(Good $good)
    {
        Storage::disk('yandex')->deleteDirectory("goods/{$good->id}");
        $good->delete();

        return response()->json(null, 204);
    }

    public function togglePublish(Request $request, Good $good)
    {
        $validated = $request->validate([
                                            'is_published' => ['required','boolean'],
                                        ]);

        $good->update([
                          'is_published' => $validated['is_published'],
                      ]);

        return response()->json([
                                    'id' => $good->id,
                                    'is_published' => $good->is_published,
                                ]);
    }

    private function storeAvatarFiles(Good $good, $file): void
    {
        $disk = Storage::disk('yandex');
        $bucket = config('filesystems.disks.yandex.bucket');

        $ext = strtolower($file->getClientOriginalExtension());
        $baseName = 'avatar-' . time() . '-' . Str::random(6);

        $originalPath = "goods/{$good->id}/{$baseName}.{$ext}";
        $thumbPath = "goods/{$good->id}/{$baseName}_thumb.jpg";

        // оригинал
        $disk->put($originalPath, file_get_contents($file->getRealPath()));

        // thumb
        $manager = new ImageManager(new Driver());
        $image = $manager->read($file->getRealPath());

        $thumb = clone $image;
        $thumb->cover(160, 160);
        $encoded = $thumb->encode(new JpegEncoder(78));

        $disk->put($thumbPath, (string) $encoded, [
            'ContentType' => 'image/jpeg',
        ]);

        $good->update([
                          'ava_image' => "https://storage.yandexcloud.net/{$bucket}/{$originalPath}",
                          'ava_thumb' => "https://storage.yandexcloud.net/{$bucket}/{$thumbPath}",
                      ]);
    }

    private function deleteAvatarFiles(Good $good): void
    {
        $disk = Storage::disk('yandex');
        $bucket = config('filesystems.disks.yandex.bucket');

        foreach (['ava_image', 'ava_thumb'] as $field) {
            if (!$good->{$field}) {
                continue;
            }

            $prefix = "https://storage.yandexcloud.net/{$bucket}/";
            $path = str_replace($prefix, '', $good->{$field});

            if ($path) {
                $disk->delete($path);
            }
        }
    }
}
