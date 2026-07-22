<?php

namespace App\Http\Controllers;

use App\Http\Resources\LeadResource;
use App\Models\Lead;
use Inertia\Inertia;
use Inertia\Response;

class Verwalter extends Controller
{
    public function index(): Response
    {
        $activeLeads = Lead::query()
            ->with(['telephone', 'entity', 'unit'])
            ->withCount('phoneCalls')
            ->open()
            ->orderByDesc('last_activity_at')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('Ameise/Verwalter', [
            'activeLeads' => LeadResource::collection($activeLeads)->resolve(),
        ]);
    }
}
