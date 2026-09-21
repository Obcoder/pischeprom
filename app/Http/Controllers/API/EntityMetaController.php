<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\Chat;
use App\Models\City;
use App\Models\Country;
use App\Models\Email;
use App\Models\EntityClassification;
use App\Models\Region;
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
                                        ->with('region:id,name,country_id', 'region.country:id,name')
                                        ->select('id', 'name', 'region_id')
                                        ->orderBy('population', 'desc')
                                        ->get(),

                                    'regions' => Region::query()
                                        ->with('country:id,name')
                                        ->select('id', 'name', 'country_id')
                                        ->orderBy('name')
                                        ->get(),

                                    'buildings' => Building::query()
                                        ->with([
                                            'city:id,name,region_id',
                                            'city.region:id,name,country_id',
                                            'city.region.country:id,name',
                                            'buildingType:id,name',
                                        ])
                                        ->select('id', 'city_id', 'building_type_id', 'address', 'postcode')
                                        ->orderBy('address')
                                        ->get()
                                        ->map(fn ($item) => [
                                            'id' => $item->id,
                                            'city_id' => $item->city_id,
                                            'address' => $item->address,
                                            'postcode' => $item->postcode,
                                            'building_type' => [
                                                'id' => $item->buildingType?->id,
                                                'name' => $item->buildingType?->name,
                                            ],
                                            'city' => [
                                                'id' => $item->city?->id,
                                                'name' => $item->city?->name,
                                                'region' => [
                                                    'id' => $item->city?->region?->id,
                                                    'name' => $item->city?->region?->name,
                                                    'country' => [
                                                        'id' => $item->city?->region?->country?->id,
                                                        'name' => $item->city?->region?->country?->name,
                                                    ],
                                                ],
                                            ],
                                        ])
                                        ->values(),

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
