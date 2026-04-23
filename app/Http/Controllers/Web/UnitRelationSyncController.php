<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Field;
use App\Models\Label;
use App\Models\Telephone;
use App\Models\Unit;
use Illuminate\Http\Request;

class UnitRelationSyncController extends Controller
{
    public function syncLabels(Request $request, Unit $unit)
    {
        $request->validate([
                               'labels' => ['nullable', 'array'],
                           ]);

        $ids = $this->resolveIds($request->input('labels', []), Label::class, 'name');

        $unit->labels()->sync($ids);

        return back()->with('success', 'Labels updated.');
    }

    public function syncFields(Request $request, Unit $unit)
    {
        $request->validate([
                               'fields' => ['nullable', 'array'],
                           ]);

        $ids = $this->resolveIds($request->input('fields', []), Field::class, 'name');

        $unit->fields()->sync($ids);

        return back()->with('success', 'Fields updated.');
    }

    public function syncTelephones(Request $request, Unit $unit)
    {
        $request->validate([
                               'telephones' => ['nullable', 'array'],
                           ]);

        $ids = $this->resolveIds($request->input('telephones', []), Telephone::class, 'number', true);

        $unit->telephones()->sync($ids);

        return back()->with('success', 'Telephones updated.');
    }

    public function syncCities(Request $request, Unit $unit)
    {
        $request->validate([
                               'cities' => ['nullable', 'array'],
                           ]);

        $ids = $this->resolveIds($request->input('cities', []), City::class, 'name');

        $unit->cities()->sync($ids);

        return back()->with('success', 'Cities updated.');
    }

    protected function resolveIds(array $items, string $modelClass, string $column, bool $normalizePhone = false): array
    {
        return collect($items)
            ->map(function ($item) use ($modelClass, $column, $normalizePhone) {
                if (is_array($item) && !empty($item['id'])) {
                    return (int) $item['id'];
                }

                $value = is_array($item)
                    ? ($item[$column] ?? null)
                    : $item;

                $value = trim((string) $value);

                if ($value === '') {
                    return null;
                }

                if ($normalizePhone) {
                    $value = preg_replace('/\s+/', '', $value);
                }

                return $modelClass::firstOrCreate([
                                                      $column => $value,
                                                  ])->id;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
