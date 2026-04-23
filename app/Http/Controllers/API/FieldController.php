<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Field;
use Illuminate\Http\Request;

class FieldController extends Controller
{
    public function index()
    {
        return Field::query()
            ->selectRaw('id, title as name, title')
            ->orderBy('title')
            ->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
                                       'title' => ['required', 'string', 'max:255'],
                                   ]);

        return Field::create($data);
    }

    public function show(Field $field)
    {
        return $field;
    }

    public function update(Request $request, Field $field)
    {
        $data = $request->validate([
                                       'title' => ['required', 'string', 'max:255'],
                                   ]);

        $field->update($data);

        return $field->fresh();
    }

    public function destroy(Field $field)
    {
        $field->delete();

        return response()->noContent();
    }
}
