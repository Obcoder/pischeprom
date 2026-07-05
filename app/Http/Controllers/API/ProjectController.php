<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $query = Project::query()
            ->when($request->filled('active'), function ($query) use ($request) {
                $query->where('is_active', filter_var($request->input('active'), FILTER_VALIDATE_BOOLEAN));
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim($request->string('search')->toString());

                $query->where(function ($query) use ($search) {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->orderBy('name');

        return response()->json($query->get());
    }

    public function store(Request $request)
    {
        $project = Project::create($this->validated($request));

        return response()->json($project, 201);
    }

    public function show(Project $project)
    {
        return response()->json($project);
    }

    public function update(Request $request, Project $project)
    {
        $project->update($this->validated($request, $project));

        return response()->json($project->fresh());
    }

    public function destroy(Project $project)
    {
        $project->delete();

        return response()->json(null, 204);
    }

    private function validated(Request $request, ?Project $project = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique('projects', 'code')->ignore($project),
            ],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);
    }
}
