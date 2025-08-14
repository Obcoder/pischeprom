<?php

namespace App\Http\Controllers;

use App\Models\Action;
use App\Models\Entity;
use App\Models\Good;
use App\Models\Uri;
use Illuminate\Http\Request;
use Inertia\Inertia;

class Verwalter extends Controller
{
    public function index()
    {
        $entities = Entity::query()
            ->whereIn('id', function ($query) {
                $query->select('entity_id')
                    ->from('sales');
            })
            ->distinct()
            ->get();

        $data = [
            'title' => 'Verwalter',
            'entities' => $entities,
        ];

        return Inertia::render('Ameise/Verwalter', $data);
    }
}
