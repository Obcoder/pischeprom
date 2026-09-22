<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use App\Models\UnitWebsiteResearch;
use App\Services\Mail\MailWorkspaceAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnitWebsiteResearchController extends Controller
{
    public function index(Request $request, Unit $unit, MailWorkspaceAccess $access): JsonResponse
    {
        $access->authorize($request->user());
        $page = UnitWebsiteResearch::query()->where('unit_id', $unit->id)
            ->orderByDesc('saved_at')->orderByDesc('id')->paginate(10);

        return response()->json([
            'data' => $page->items(),
            'links' => ['next' => $page->nextPageUrl(), 'prev' => $page->previousPageUrl()],
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ], 200, ['Cache-Control' => 'private, no-store']);
    }
}
