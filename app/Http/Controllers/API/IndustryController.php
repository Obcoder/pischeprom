<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreIndustryRequest;
use App\Http\Requests\UpdateIndustryRequest;
use App\Models\Industry;
use Illuminate\Http\Request;

class IndustryController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->string('search')->toString();
        $perPage = $request->integer('per_page', 30);

        $industries = Industry::query()
            ->when($search, fn ($q) =>
            $q->where('title', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
            )
            ->orderBy('code')
            ->paginate($perPage)
            ->withQueryString();

        return response()->json($industries);
    }

    public function store(StoreIndustryRequest $request)
    {
        $industry = Industry::create($request->validated());

        return response()->json($industry, 201);
    }

    public function show(Industry $industry)
    {
        return response()->json($industry);
    }

    public function update(UpdateIndustryRequest $request, Industry $industry)
    {
        $industry->update($request->validated());

        return response()->json($industry->fresh());
    }

    public function destroy(Industry $industry)
    {
        $industry->delete();

        return response()->json(null, 204);
    }
}
