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
        $goods = Good::where('name', 'like', '%' . $request->search . '%')
            ->orderBy('created_at', 'desc')
            ->get();
        return $goods;
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
    public function store(Request $request)
    {
        $request->validate(
            [
                'file' => 'image|mimes:jpg,jpeg,png|max:2048',
            ]
        );

        $good = Good::create($request->all());
        $good->products()->attach($request->input('products'));
        Storage::disk('yandex')->put("goods/{$good->id}/ . $good->name .json", json_encode($good));

        $file = $request->file('ava_image');
        $bucket = config('filesystems.disks.yandex.bucket');
        $filename = 'avatar-'. $file->getSize(). '.' . $file->getClientOriginalExtension(); // avatar.jpg/png
        $path = "goods/{$good->id}/{$filename}";
        // Сохраняем файл в S3
        Storage::disk('yandex')->put($path, file_get_contents($file));
        /// Принудительно формируем корректный URL
        $url = "https://storage.yandexcloud.net/{$bucket}/{$path}";
        // Сохраняем в БД
        $good->update(['ava_image' => $url]);

        return redirect()->route('Ameise.goods');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id, $slug = null)
    {
        $good = Good::with('prices')->findOrFail($id);
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
    public function destroy(string $id)
    {
        //
    }
}
