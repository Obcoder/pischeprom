<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use App\Models\Uri;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UnitUriController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
                                       'unit_id' => ['required', 'exists:units,id'],
                                       'uri_id'  => ['nullable', 'exists:uris,id'],
                                       'address' => ['nullable', 'string', 'max:255'],
                                   ]);

        if (empty($data['uri_id']) && blank($data['address'])) {
            throw ValidationException::withMessages([
                                                        'address' => 'Укажите URI или выберите существующий.',
                                                    ]);
        }

        $unit = Unit::findOrFail($data['unit_id']);

        $uri = !empty($data['uri_id'])
            ? Uri::findOrFail($data['uri_id'])
            : Uri::firstOrCreate([
                                     'address' => trim($data['address']),
                                 ]);

        $unit->uris()->syncWithoutDetaching([$uri->id]);

        return back()->with('success', 'URI успешно привязан.');
    }

    public function destroy(Unit $unit, Uri $uri)
    {
        $unit->uris()->detach($uri->id);

        return back()->with('success', 'URI отвязан.');
    }
}
