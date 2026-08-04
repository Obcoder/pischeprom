<?php

namespace App\Http\Controllers\API\AiPriceLists;

use App\Domain\AiPriceLists\Enums\ItemDecisionStatus;
use App\Domain\AiPriceLists\Services\PriceListReviewService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AiPriceLists\BulkPriceListDefaultsRequest;
use App\Http\Requests\AiPriceLists\DecidePriceListItemRequest;
use App\Http\Requests\AiPriceLists\UpdatePriceListItemRequest;
use App\Models\PriceListImport;
use App\Models\PriceListImportItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PriceListItemController extends Controller
{
    public function index(Request $request, PriceListImport $priceListImport): JsonResponse
    {
        Gate::authorize('view', $priceListImport);
        $perPage = min(100, max(10, (int) $request->integer('per_page', 50)));
        $items = $priceListImport->items()
            ->with(['good:id,name,slug,is_published', 'candidates.good:id,name,slug,is_published', 'reviewer:id,name', 'supplierPrice:id,price_list_import_item_id,price,currency_code'])
            ->when($request->filled('match_class'), fn (Builder $query) => $query->where('match_class', $request->string('match_class')->toString()))
            ->when($request->filled('decision_status'), fn (Builder $query) => $query->where('decision_status', $request->string('decision_status')->toString()))
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(fn (Builder $nested) => $nested->where('raw_name', 'like', "%{$search}%")
                    ->orWhere('supplier_sku', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%"));
            })
            ->orderBy('position')
            ->paginate($perPage);

        return response()->json($items);
    }

    public function update(UpdatePriceListItemRequest $request, PriceListImport $priceListImport, PriceListImportItem $priceListItem, PriceListReviewService $review): JsonResponse
    {
        $this->assertBelongs($priceListImport, $priceListItem);

        return response()->json(['data' => $review->updateItem($priceListItem, $request->validated(), $request->user())]);
    }

    public function decide(DecidePriceListItemRequest $request, PriceListImport $priceListImport, PriceListImportItem $priceListItem, PriceListReviewService $review): JsonResponse
    {
        $this->assertBelongs($priceListImport, $priceListItem);
        $decision = ItemDecisionStatus::from($request->validated('decision'));

        return response()->json(['data' => $review->decide(
            $priceListItem,
            $decision,
            $request->validated('good_id'),
            (bool) $request->boolean('save_alias'),
            $request->user(),
        )]);
    }

    public function bulkConfirmExact(Request $request, PriceListImport $priceListImport, PriceListReviewService $review): JsonResponse
    {
        Gate::authorize('review', $priceListImport);
        $confirmed = $review->bulkConfirmExact($priceListImport, $request->user());

        return response()->json(['confirmed' => $confirmed]);
    }

    public function bulkDefaults(BulkPriceListDefaultsRequest $request, PriceListImport $priceListImport, PriceListReviewService $review): JsonResponse
    {
        $data = $request->validated();
        $result = $review->applyDefaults(
            $priceListImport,
            $data,
            (bool) $request->boolean('preview', true),
            $request->user(),
        );

        return response()->json(['data' => $result]);
    }

    private function assertBelongs(PriceListImport $import, PriceListImportItem $item): void
    {
        abort_unless($item->price_list_import_id === $import->id, 404);
    }
}
