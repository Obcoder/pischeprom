<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class UnitController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $like = $request->search;
        $limit = $request->limit;
        $units = Unit::where('name', 'like', "%{$like}%")
            ->with('labels')
            ->with('consumptions')
            ->with('products')
            ->with('entities.sales')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        return $units;
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
        $unit = Unit::create($request->all());
        $unit->uris()->attach($request->input('uris'));
        $unit->labels()->attach($request->input('labels'));
        $unit->buildings()->attach($request->input('buildings'));

        // Создаем "папку" в S3 (на самом деле это просто пустой файл, так как в S3 нет папок)
        Storage::disk('yandex')->put("units/{$unit->name}/placeholder.txt", $unit);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return Unit::findOrFail($id);
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

    public function getUnitFiles($name): array
    {
        $path = "units/{$name}";

        if (!Storage::disk('yandex')->exists($path)) {
            return ['папки нет! ☹️: '. $path]; // Если папки нет, вернуть пустой список
        }

        $files = Storage::disk('yandex')->files($path); // Получить список файлов

        // Формируем URL для каждого файла
        return array_map(function ($file) {
            $baseUrl = env('YANDEX_CLOUD_URL'); // Убедитесь, что это правильно настроено в .env

            // Кодируем путь файла для корректного формирования URL
            $encodedFilePath = urlencode($file);

            // Формируем правильный URL для файла
            $url = "{$baseUrl}/" . env('YANDEX_CLOUD_BUCKET') . "/{$file}";

            return [
                'name' => basename($file),
                'url' => $url,
            ];
        }, $files);
    }
}
