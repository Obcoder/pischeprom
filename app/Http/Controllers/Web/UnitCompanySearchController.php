<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Unit\SearchCompaniesRequest;
use App\Services\Entities\DaDataCompanySearchService;
use Illuminate\Http\JsonResponse;

class UnitCompanySearchController extends Controller
{
    public function __invoke(SearchCompaniesRequest $request, DaDataCompanySearchService $search): JsonResponse
    {
        return response()->json($search->search($request->validated()));
    }
}
