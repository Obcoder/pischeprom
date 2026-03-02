<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Good;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class GoodController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $request->input('search');
        $published = $request->has('is_published')
            ? filter_var($request->input('is_published'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            : null;

        $perPage = (int) $request->input('per_page', 100);
        $perPage = max(1, min($perPage, 500));

        $goods = Good::query()
            ->with(['products.category']) // важно для группировки
            ->search($search)
            ->published($published)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json($goods);
    }

    public function indexPublished()
    {
        return Good::query()
            ->published(true)
            ->inRandomOrder()
            ->get();
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
//    public function store(Request $request)
//    {
//        $request->validate(
//            [
//                'file' => 'image|mimes:jpg,jpeg,png|max:2048',
//            ]
//        );
//
//        $good = Good::create($request->all());
//        $good->products()->attach($request->input('products'));
//        Storage::disk('yandex')->put("goods/{$good->id}/ . $good->name .json", json_encode($good));
//
//        $file = $request->file('ava_image');
//        $bucket = config('filesystems.disks.yandex.bucket');
//        $filename = 'avatar-'. $file->getSize(). '.' . $file->getClientOriginalExtension(); // avatar.jpg/png
//        $path = "goods/{$good->id}/{$filename}";
//        // Сохраняем файл в S3
//        Storage::disk('yandex')->put($path, file_get_contents($file));
//        /// Принудительно формируем корректный URL
//        $url = "https://storage.yandexcloud.net/{$bucket}/{$path}";
//        // Сохраняем в БД
//        $good->update(['ava_image' => $url]);
//    }

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

        // JSON паспорт
        Storage::disk('yandex')->put(
            "goods/{$good->id}/good.json",
            $good->toJson(JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
        );

        // Если есть аватар
        if ($request->hasFile('ava_image')) {

            $file = $request->file('ava_image');
            $bucket = config('filesystems.disks.yandex.bucket');

            $filename = 'avatar-' . time() . '.' . $file->getClientOriginalExtension();
            $path = "goods/{$good->id}/{$filename}";

            Storage::disk('yandex')->put($path, file_get_contents($file->getRealPath()));

            $url = "https://storage.yandexcloud.net/{$bucket}/{$path}";

            $good->update(['ava_image' => $url]);
        }

        return response()->json(
            $good->fresh()->load('products'),
            201
        );
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id, $slug = null)
    {
        $good = Good::with(['prices', 'quotations', 'sales'])
            ->findOrFail($id);
        // Проверка slug для SEO (опционально)
        $expectedSlug = \Str::slug($good->name);
        if ($slug && $slug !== $expectedSlug) {
            return redirect()->route('goods.show', ['id' => $id, 'slug' => $expectedSlug], 301);
        }

        return $good;
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Good $good)
    {
        // Можно добавить удаление папки из S3:
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
}
