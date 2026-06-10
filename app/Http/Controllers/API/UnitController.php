<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use App\Services\Mail\UnansweredOutgoingMailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UnitController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
                               'search' => 'nullable|string|max:255',
                               'good_id' => 'nullable|integer|exists:goods,id',
                           ]);

        return Unit::query()
            ->search($request->search)
            ->when(
                $request->filled('good_id'),
                fn ($q) => $q->forGood((int) $request->good_id)
            )
            ->with([
                       'buildings.city',
                       'labels',
                       'fields',
                       'cities',
                   ])
            ->latest()
            ->get();
    }

    public function store(Request $request)
    {
        $unit = Unit::create($request->all());

        $unit->buildings()->sync($request->input('buildings', []));
        $unit->emails()->sync($request->input('emails', []));
        $unit->fields()->sync($request->input('fields', []));
        $unit->uris()->sync($request->input('uris', []));
        $unit->labels()->sync($request->input('labels', []));
        $unit->cities()->sync($request->input('cities', []));
        $unit->telephones()->sync($request->input('telephones', []));

        Storage::disk('yandex')->put("units/{$unit->name}/placeholder.txt", '');
    }

    public function show(Unit $unit, UnansweredOutgoingMailService $mailService)
    {
        $unit->load(Unit::DETAIL_RELATIONS);
        $this->attachMailFollowUp($unit, $mailService);

        return response()->json([
                                    'data' => $unit,
                                ]);
    }

    public function create()
    {
        //
    }

    public function edit(string $id)
    {
        //
    }

    public function update(Request $request, string $id)
    {
        //
    }

    public function destroy(string $id)
    {
        //
    }

    public function getUnitFiles($name): array
    {
        $path = "units/{$name}";

        if (!Storage::disk('yandex')->exists($path)) {
            return [];
        }

        $files = Storage::disk('yandex')->files($path);

        return array_map(function ($file) {
            $baseUrl = rtrim(env('YANDEX_CLOUD_URL'), '/');
            $bucket = env('YANDEX_CLOUD_BUCKET');

            return [
                'name' => basename($file),
                'path' => $file,
                'url' => "{$baseUrl}/{$bucket}/{$file}",
            ];
        }, $files);
    }

    protected function attachMailFollowUp(Unit $unit, UnansweredOutgoingMailService $mailService): void
    {
        $followUps = $mailService->summarizeForUnits([$unit->id]);
        $unit->setAttribute('mail_follow_up', $followUps[$unit->id] ?? null);
    }
}
