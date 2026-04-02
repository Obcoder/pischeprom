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
                                        ->orderBy('name')
                                        ->get(),

                                    'buildings' => Building::query()
                                        ->select('id', 'name')
                                        ->orderBy('name')
                                        ->get(),

                                    'emails' => Email::query()
                                        ->select('id', 'email')
                                        ->orderBy('email')
                                        ->get(),

                                    'telephones' => Telephone::query()
                                        ->select('id', 'number')
                                        ->orderBy('number')
                                        ->get(),

                                    'units' => Unit::query()
                                        ->select('id', 'name')
                                        ->orderBy('name')
                                        ->get(),

                                    'chats' => Chat::query()
                                        ->select('id', 'name')
                                        ->orderBy('name')
                                        ->get(),
                                ]);
    }
}
