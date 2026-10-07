<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Component;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ComponentController extends Controller
{
    public function index()
    {
        return Component::query()
            ->withCount('products')
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    public function store(Request $request)
    {
        $component = Component::create($this->validated($request));

        return response()->json($component->loadCount('products'), 201);
    }

    public function show(Component $component)
    {
        return $component->load([
            'products' => fn ($query) => $query->without(['category', 'manufacturers'])
                ->select(['products.id', 'products.rus', 'products.eng'])
                ->orderBy('products.rus'),
        ])->loadCount('products');
    }

    public function update(Request $request, Component $component)
    {
        $component->update($this->validated($request));

        return $this->show($component->fresh());
    }

    public function destroy(Component $component)
    {
        DB::transaction(function () use ($component): void {
            $component->products()->detach();
            $component->delete();
        });

        return response()->noContent();
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ], [
            'name.required' => 'Укажите название компонента.',
            'name.max' => 'Название должно содержать не более 255 символов.',
        ]);
    }
}
