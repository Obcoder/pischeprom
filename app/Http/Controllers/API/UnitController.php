<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\UnitResource;
use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UnitController extends Controller
{
    /**
     * Display a listing of the resource.
     */
//    public function index(Request $request)
//    {
////        $like = $request->search;
////        $limit = $request->limit;
//        $activeStages = $request->input('activeStages', true); // По умолчанию true — подгружаем только активные стадии
//
////        $query = Unit::query()
////            ->where('name', 'like', "%{$like}%")
////            ->with([
////                       'labels',
////                       'consumptions',
////                       'products',
////                       'quotations',
////                       'entities.sales',
////                       'stages' => function ($query) use ($activeStages) {
////                           if ($activeStages) {
////                               $query->wherePivot('isActive', true); // Фильтруем только активные стадии
////                           }
////                           // Если activeStages=false, подгружаем все стадии
////                       }
////                   ]);
////
////        return $query->orderByDesc('created_at')
////            ->limit($limit)
////            ->get();
//
//        return Unit::query()
//            ->search($request->search)
//            ->when(
//                $request->filled('good_id'),
//                fn ($q) => $q->forGood((int) $request->good_id)
//            )
//            ->with([
//                       'labels',
//                       'consumptions',
//                       'products',
//                       'quotations',
//                       'entities.sales',
//                       'stages' => function ($query) use ($activeStages) {
//                           if ($activeStages) {
//                               $query->wherePivot('isActive', true); // Фильтруем только активные стадии
//                           }
//                           // Если activeStages=false, подгружаем все стадии
//                       },
//                       'quotations.good',
//                       'quotations.measure',
//                   ])
//            ->latest()
//            ->limitIfPresent($request->integer('limit'))
//            ->get();
//    }

    public function index(Request $request)
    {
        $request->validate([
                               'search' => 'nullable|string|max:255',
                               'good_id' => 'nullable|integer|exists:goods,id',
//                                   'activeStages' => 'nullable|boolean',
//                                   'limit' => 'nullable|integer|min:1|max:10000',
                           ]);

//            $activeStages = $request->boolean('activeStages', true);
//        $limit = $request->integer('limit'); // Null — все

        return Unit::query()
            ->search($request->search)
            ->when($request->filled('good_id'), fn ($q) => $q->forGood((int) $request->good_id))
            ->with([
                       'labels:id,name', // Начните с этого; добавьте другие по одному
                       //'consumptions:id,unit_id,amount',
                       //'products:id,name',
                       //'quotations:id,good_id,measure_id,price',
                       //'entities.sales:id,entity_id,sale_date',

//                           'stages' => function ($query) use ($activeStages) {
//                               $query->select('stages.id', 'stages.name');
//                               if ($activeStages) {
//                                   $query->wherePivot('isActive', true);
//                               }
//                           },

                       //'quotations.good:id,name',
                       //'quotations.measure:id,name',
                   ])
            ->latest()
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
    public function store(Request $request)
    {
        $unit = Unit::create($request->all());

        $unit->buildings()->sync($request->input('buildings', []));
        $unit->emails()->sync($request->input('emails', []));
        $unit->fields()->sync($request->input('fields', []));
        $unit->uris()->sync($request->input('uris', []));
        $unit->labels()->sync($request->input('labels', []));

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
