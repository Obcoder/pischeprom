<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Mail\PreviewMailOfferRequest;
use App\Services\Mail\AuthorizedMailDispatchService;
use App\Services\Mail\MailOfferCatalog;
use App\Services\Mail\MailOfferRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MailOfferController extends Controller
{
    public function goods(Request $request, AuthorizedMailDispatchService $dispatch, MailOfferCatalog $catalog): JsonResponse
    {
        $dispatch->authorize($request->user());
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $goods = $catalog->search((string) ($data['search'] ?? ''), (int) ($data['per_page'] ?? 20));

        return response()->json([
            'data' => $goods->items(),
            'meta' => [
                'current_page' => $goods->currentPage(),
                'last_page' => $goods->lastPage(),
                'per_page' => $goods->perPage(),
                'total' => $goods->total(),
            ],
        ]);
    }

    public function preview(PreviewMailOfferRequest $request, MailOfferCatalog $catalog, MailOfferRenderer $renderer): JsonResponse
    {
        $data = $request->validated();

        return response()->json($renderer->render(
            (string) ($data['body'] ?? ''),
            $catalog->resolve($data['offer']['items'] ?? []),
            $data['offer']['logistics'] ?? null,
            (string) ($data['quoted_body'] ?? ''),
        ));
    }
}
