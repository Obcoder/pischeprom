<?php

namespace App\Http\Controllers\Banking;

use App\Domain\Banking\Services\BankAuditLogger;
use App\Http\Controllers\Controller;
use App\Models\BankAuditEvent;
use App\Models\BankSyncError;
use App\Models\BankSyncRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BankLogController extends Controller
{
    public function syncRuns(Request $request): JsonResponse
    {
        Gate::authorize('bank.view');

        return response()->json(
            BankSyncRun::query()
                ->with('account:id,masked_number,name')
                ->latest('id')
                ->paginate(min(100, max(1, (int) $request->integer('per_page', 25))))
        );
    }

    public function errors(Request $request): JsonResponse
    {
        Gate::authorize('bank.view');

        return response()->json(
            BankSyncError::query()
                ->with([
                    'syncRun:id,bank_connection_id,bank_account_id,correlation_id',
                    'syncRun.account:id,masked_number,name',
                    'resolvedBy:id,name',
                ])
                ->latest('id')
                ->paginate(min(100, max(1, (int) $request->integer('per_page', 25))))
        );
    }

    public function resolve(
        Request $request,
        BankSyncError $error,
        BankAuditLogger $audit,
    ): JsonResponse {
        Gate::authorize('bank.manage_connection');
        $request->validate(['resolution_comment' => ['required', 'string', 'min:3', 'max:2000']]);
        $error->forceFill([
            'resolved_at' => now(),
            'resolved_by' => $request->user()->id,
        ])->save();
        $audit->record('bank.sync_error.resolved', $error, [
            'resolution_comment' => $request->string('resolution_comment')->toString(),
        ], $request->user());

        return response()->json(['data' => $error->fresh('resolvedBy:id,name')]);
    }

    public function audit(Request $request): JsonResponse
    {
        Gate::authorize('bank.view_audit');

        return response()->json(
            BankAuditEvent::query()
                ->with('user:id,name')
                ->latest('id')
                ->paginate(min(100, max(1, (int) $request->integer('per_page', 25))))
        );
    }
}
