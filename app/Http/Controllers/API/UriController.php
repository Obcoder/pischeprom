<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Uri;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UriController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): \Illuminate\Http\JsonResponse
    {
        logger()->info('URI INDEX PARAMS', $request->all());

        $search = $request->input('search');
        $itemsPerPage = (int) $request->input('itemsPerPage', 50);
        $page = (int) $request->input('page', 1);
        $sortBy = $request->input('sortBy', []);
        $sortDesc = $request->input('sortDesc', []);

        $query = Uri::with('units')
            ->search($search);

        // Сортировка
        if (!empty($sortBy)) {
            $direction = ($sortDesc[0] ?? false) ? 'desc' : 'asc';
            $query->orderBy($sortBy[0], $direction);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $paginator = $query->paginate(
            $itemsPerPage,
            ['*'],
            'page',
            $page
        );

        return response()->json([
                                    'items' => $paginator->items(),
                                    'total' => $paginator->total(),
                                ]);
    }


    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $uri = Uri::create($request->all());
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
