<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\BuildingType;
use App\Models\City;
use App\Models\Currency;
use App\Models\Email;
use App\Models\Entity;
use App\Models\EntityClassification;
use App\Models\Field;
use App\Models\Good;
use App\Models\Industry;
use App\Models\Label;
use App\Models\Measure;
use App\Models\Product;
use App\Models\Telephone;
use App\Models\Unit;
use App\Models\Uri;
use App\Services\Mail\UnansweredOutgoingMailService;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class UnitController extends Controller
{
    public function show(Unit $unit, UnansweredOutgoingMailService $mailService)
    {
        $unit->load(Unit::DETAIL_RELATIONS);
        $this->attachConsumptionRequestCounts($unit);
        $this->attachMailFollowUp($unit, $mailService);

        return Inertia::render('Ameise/Unit', [
            'unit' => $unit,
            'dictionaries' => [
                'buildingTypes' => BuildingType::orderBy('name')->get(),
                'buildings' => Building::with(['city', 'buildingType'])->orderBy('address')->get(),
                'cities' => City::orderBy('name')->get(['id', 'name']),
                'currencies' => Currency::orderBy('code')->orderBy('name')->get(),
                'emails' => Email::orderBy('address')->get(),
                'entities' => Entity::orderBy('name')->get(),
                'entityClassifications' => EntityClassification::orderBy('name')->get(),
                'fields' => Field::query()
                    ->selectRaw('id, title as name, title')
                    ->orderBy('title')
                    ->get(),
                'goods' => Good::orderBy('name')->limit(100)->get(),
                'industries' => Industry::orderBy('code')->limit(500)->get(),
                'labels' => Label::orderBy('name')->get(['id', 'name']),
                'measures' => Measure::orderBy('name')->get(),
                'products' => Product::orderBy('rus')->get(),
                'telephones' => Telephone::orderBy('number')->get(['id', 'number']),
                'uris' => Uri::orderBy('address')->get(['id', 'address']),
            ],
            'files' => $this->getUnitFiles($unit),
        ]);
    }

    protected function getUnitFiles(Unit $unit): array
    {
        $disk = Storage::disk('yandex');
        $paths = ["units/{$unit->id}", "units/{$unit->name}"];
        $path = collect($paths)->first(fn ($candidate) => $disk->exists($candidate)) ?? $paths[0];
        $files = $disk->files($path);

        return collect($files)->map(function ($file) use ($disk) {
            $baseUrl = rtrim(env('YANDEX_CLOUD_URL'), '/');
            $bucket = env('YANDEX_CLOUD_BUCKET');

            return [
                'name' => basename($file),
                'path' => $file,
                'url' => "{$baseUrl}/{$bucket}/{$file}",
                'size' => $disk->size($file),
                'last_modified' => $disk->lastModified($file),
            ];
        })->values()->all();
    }

    protected function attachMailFollowUp(Unit $unit, UnansweredOutgoingMailService $mailService): void
    {
        $followUps = $mailService->summarizeForUnits([$unit->id]);
        $unit->setAttribute('mail_follow_up', $followUps[$unit->id] ?? null);
    }

    protected function attachConsumptionRequestCounts(Unit $unit): void
    {
        $unit->load([
            'consumptions.product' => fn ($query) => $query->withCount('searchRequests'),
        ]);
    }
}
