<?php

namespace App\Http\Controllers\Banking;

use App\Domain\Banking\Services\ManualBankReconciliationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Banking\BankCommentRequest;
use App\Http\Requests\Banking\ManualAllocationRequest;
use App\Http\Requests\Banking\RejectSuggestionRequest;
use App\Http\Requests\Banking\ReverseAllocationRequest;
use App\Models\BankMatchSuggestion;
use App\Models\BankTransaction;
use App\Models\BankTransactionAllocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class BankReconciliationController extends Controller
{
    public function allocate(
        ManualAllocationRequest $request,
        BankTransaction $transaction,
        ManualBankReconciliationService $service,
    ): JsonResponse {
        Gate::authorize('reconcile', $transaction);
        $result = $service->allocate(
            $transaction,
            $request->validated('allocations'),
            $request->user(),
            $request->validated('comment'),
        );

        return response()->json([
            'data' => $result,
            'message' => 'Payment allocation was saved.',
        ], 201);
    }

    public function reverse(
        ReverseAllocationRequest $request,
        BankTransaction $transaction,
        BankTransactionAllocation $allocation,
        ManualBankReconciliationService $service,
    ): JsonResponse {
        Gate::authorize('reconcile', $transaction);

        if ((int) $allocation->bank_transaction_id !== (int) $transaction->id) {
            abort(404);
        }

        return response()->json([
            'data' => $service->reverse(
                $allocation,
                $request->user(),
                $request->validated('reason'),
            ),
            'message' => 'Allocation was reversed.',
        ]);
    }

    public function rejectSuggestion(
        RejectSuggestionRequest $request,
        BankTransaction $transaction,
        BankMatchSuggestion $suggestion,
        ManualBankReconciliationService $service,
    ): JsonResponse {
        Gate::authorize('reconcile', $transaction);

        if ((int) $suggestion->bank_transaction_id !== (int) $transaction->id) {
            abort(404);
        }

        return response()->json([
            'data' => $service->rejectSuggestion(
                $transaction,
                $suggestion,
                $request->user(),
                $request->validated('comment'),
            ),
        ]);
    }

    public function markNotRequired(
        BankCommentRequest $request,
        BankTransaction $transaction,
        ManualBankReconciliationService $service,
    ): JsonResponse {
        Gate::authorize('reconcile', $transaction);

        return response()->json([
            'data' => $service->markNotRequired(
                $transaction,
                $request->user(),
                $request->validated('comment'),
            ),
        ]);
    }
}
