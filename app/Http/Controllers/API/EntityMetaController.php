<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\Chat;
use App\Models\City;
use App\Models\Country;
use App\Models\Email;
use App\Models\EntityClassification;
use App\Models\Telephone;
use App\Models\Unit;

class EntityMetaController extends Controller
{
    public function index()
    {
        return response()->json([
                                    'classifications' => EntityClassification::query()
                                        ->select('id', 'name')
                                        ->orderBy('name')
                                        ->get(),

                                    'countries' => Country::query()
                                        ->select('id', 'name')
                                        ->orderBy('name')
                                        ->get(),

                                    'cities' => City::query()
                                        ->select('id', 'name')
                                        ->orderBy('population', 'desc')
                                        ->get(),

                                    'buildings' => Building::query()
                                        ->select('id', 'address')
                                        ->orderBy('address')
                                        ->get(),

                                    'emails' => Email::query()
                                        ->select('id', 'address')
                                        ->orderBy('address')
                                        ->get()
                                        ->map(fn ($item) => [
                                            'id' => $item->id,
                                            'address' => $item->address,
                                        ])
                                        ->values(),

                                    'telephones' => Telephone::query()
                                        ->get()
                                        ->map(fn ($item) => [
                                            'id' => $item->id,
                                            'number' => $item->number ?? $item->telephone ?? $item->phone,
                                        ])
                                        ->sortBy('number')
                                        ->values(),

                                    'units' => Unit::query()
                                        ->select('id', 'name')
                                        ->orderBy('name')
                                        ->get(),

                                    'chats' => Chat::query()
                                        ->select('id', 'numbers')
                                        ->orderBy('numbers')
                                        ->get(),
                                ]);
    }
}
