<?php

namespace App\Http\Controllers\API\Logistics;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Entity;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferenceController extends Controller
{
    public function __invoke(Request $request, string $type): JsonResponse
    {
        $search = trim((string) $request->input('search'));
        $limit = max(1, min((int) $request->input('limit', 50), 100));

        $data = match ($type) {
            'cities' => City::query()
                ->select(['id', 'name', 'region_id'])
                ->with('region:id,name')
                ->when($search !== '', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
                ->orderBy('name')
                ->limit($limit)
                ->get()
                ->map(fn (City $city) => [
                    'id' => $city->id,
                    'name' => $city->name,
                    'label' => trim($city->name.($city->region ? ', '.$city->region->name : '')),
                ]),
            'entities' => Entity::query()
                ->select(['id', 'name'])
                ->when($search !== '', fn (Builder $query) => $query->where('name', 'like', "%{$search}%"))
                ->orderBy('name')
                ->limit($limit)
                ->get(),
            'users' => User::query()
                ->select(['id', 'name', 'email'])
                ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                }))
                ->orderBy('name')
                ->limit($limit)
                ->get(),
            default => abort(404),
        };

        return response()->json(['data' => $data]);
    }
}
