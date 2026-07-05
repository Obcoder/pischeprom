<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\ServiceResource;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $services = Service::query()
            ->with(['expenseArticle', 'project'])
            ->search($request->input('search'))
            ->when($request->has('is_active'), function ($query) use ($request) {
                $query->where('is_active', filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN));
            })
            ->orderBy('name')
            ->get();

        return response()->json(ServiceResource::collection($services)->resolve($request));
    }

    public function store(Request $request)
    {
        $service = Service::create($this->validated($request));

        return response()->json(
            new ServiceResource($service->load(['expenseArticle', 'project'])),
            201
        );
    }

    public function show(Service $service)
    {
        return new ServiceResource($service->load(['expenseArticle', 'project']));
    }

    public function update(Request $request, Service $service)
    {
        $service->update($this->validated($request, $service));

        return new ServiceResource($service->fresh(['expenseArticle', 'project']));
    }

    public function destroy(Service $service)
    {
        $service->delete();

        return response()->json(null, 204);
    }

    private function validated(Request $request, ?Service $service = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('services', 'code')->ignore($service?->id),
            ],
            'expense_article_id' => ['nullable', 'exists:expense_articles,id'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ]);
    }
}
