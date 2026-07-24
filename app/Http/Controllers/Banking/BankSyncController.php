<?php

namespace App\Http\Controllers\Banking;

use App\Domain\Banking\Exceptions\BankConfigurationException;
use App\Domain\Banking\Services\BankAuditLogger;
use App\Http\Controllers\Controller;
use App\Http\Requests\Banking\BankSyncRequest;
use App\Jobs\Banking\SyncSberAccountsJob;
use App\Jobs\Banking\SyncSberStatementsJob;
use App\Models\BankConnection;
use App\Models\BankSyncRun;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

class BankSyncController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('bank.view');
        $runs = BankSyncRun::query()
            ->with(['account:id,masked_number,name', 'errors:id,bank_sync_run_id,category,safe_message,requires_intervention'])
            ->latest('id')
            ->paginate(min(100, max(1, (int) $request->integer('per_page', 25))));

        return response()->json($runs);
    }

    public function store(BankSyncRequest $request, BankAuditLogger $audit): JsonResponse
    {
        if (! (bool) config('banking.enabled') || ! (bool) config('banking.sber.enabled')) {
            throw new BankConfigurationException('Sber API is disabled.');
        }

        $data = $request->validated();
        $connection = BankConnection::query()->findOrFail($data['connection_id']);
        Gate::authorize('sync', $connection);
        $dispatchLock = Cache::store((string) config('banking.lock_store', 'redis'))
            ->lock("banking:manual-dispatch:{$connection->id}", 60);

        if (! $dispatchLock->get()) {
            return response()->json([
                'message' => 'Synchronization was already requested recently.',
            ], 409);
        }

        $mode = $data['mode'];

        if ($mode === 'initial' && $connection->accounts()->doesntExist()) {
            SyncSberAccountsJob::dispatch($connection->id, true);
        } else {
            SyncSberStatementsJob::dispatch(
                $connection->id,
                $mode,
                $data['from'] ?? null,
                $data['to'] ?? null,
            );
        }

        $audit->record('bank.sync.queued_manually', $connection, [
            'mode' => $mode,
            'from' => $data['from'] ?? null,
            'to' => $data['to'] ?? null,
        ], $request->user());

        return response()->json([
            'status' => 'queued',
            'message' => 'Synchronization was queued.',
        ], 202);
    }
}
