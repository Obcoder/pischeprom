<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\Email;
use App\Models\Entity;
use App\Models\EntityClassification;
use App\Models\Good;
use App\Models\Label;
use App\Models\Measure;
use App\Models\Product;
use App\Models\Telephone;
use App\Models\Unit;
use App\Models\Uri;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class UnitController extends Controller
{
    public function show(Unit $unit)
    {
        $unit->load([
                        'fields',
                        'labels',
                        'telephones',
                        'uris',
                        'entities.telephones',
                        'entities.sales',
                        'buildings.city',
                        'consumptions.product',
                        'consumptions.measure',
                        'manufactures',
                        'emails.sendings',
                        'stages',
                        'quotations.good',
                        'quotations.measure',
                    ]);

        return Inertia::render('Ameise/Unit', [
            'unit' => $unit,
            'dictionaries' => [
                'buildings' => Building::with('city')->orderBy('address')->get(),
                'emails' => Email::orderBy('address')->get(),
                'entities' => Entity::orderBy('name')->get(),
                'entityClassifications' => EntityClassification::orderBy('name')->get(),
                'goods' => Good::orderBy('name')->limit(100)->get(),
                'labels' => Label::orderBy('name')->get(),
                'measures' => Measure::orderBy('name')->get(),
                'products' => Product::orderBy('rus')->get(),
                'telephones' => Telephone::orderBy('number')->get(),
                'uris' => Uri::orderBy('address')->get(),
            ],
            'files' => $this->getUnitFiles($unit->name),
        ]);
    }

    protected function getUnitFiles(string $name): array
    {
        $path = "units/{$name}";
        $files = Storage::disk('yandex')->files($path);

        return collect($files)->map(function ($file) {
            $baseUrl = rtrim(env('YANDEX_CLOUD_URL'), '/');
            $bucket = env('YANDEX_CLOUD_BUCKET');

            return [
                'name' => basename($file),
                'url' => "{$baseUrl}/{$bucket}/{$file}",
            ];
        })->values()->all();
    }
}
