<?php

namespace App\Http\Controllers\Banking;

use App\Domain\Banking\Contracts\BankProviderInterface;
use App\Domain\Banking\Exceptions\BankingException;
use App\Domain\Banking\Services\SberHealthService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Banking\SberOAuthCallbackRequest;
use App\Jobs\Banking\SyncSberAccountsJob;
use App\Models\Entity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Throwable;

class SberConnectionController extends Controller
{
    public function authorizeRedirect(Request $request, BankProviderInterface $provider): RedirectResponse
    {
        Gate::authorize('bank.manage_connection');
        $validated = $request->validate([
            'owner_entity_id' => ['required', 'integer', 'exists:entities,id'],
        ]);
        $owner = Entity::query()->findOrFail($validated['owner_entity_id']);

        return redirect()->away($provider->getAuthorizationUrl($request->user(), $owner));
    }

    public function callback(
        SberOAuthCallbackRequest $request,
        BankProviderInterface $provider,
    ): RedirectResponse {
        try {
            $connection = $provider->exchangeAuthorizationCode(
                code: (string) $request->input('code', ''),
                state: (string) $request->input('state'),
                error: $request->input('error'),
                errorDescription: $request->input('error_description'),
            );
            SyncSberAccountsJob::dispatch($connection->id, true);

            return redirect()->route('admin.bank.index', ['connection' => 'connected']);
        } catch (Throwable $exception) {
            Log::channel('banking')->warning('Sber OAuth callback failed.', [
                'category' => $exception instanceof BankingException ? $exception->category : 'unexpected',
                'exception' => $exception::class,
            ]);

            return redirect()->route('admin.bank.index', ['connection' => 'error']);
        }
    }

    public function health(SberHealthService $health): JsonResponse
    {
        Gate::authorize('bank.manage_connection');

        return response()->json(['data' => $health->inspect()]);
    }

    public function owners(Request $request): JsonResponse
    {
        Gate::authorize('bank.manage_connection');
        $search = trim((string) $request->query('search', ''));

        return response()->json([
            'data' => Entity::query()
                ->when($search !== '', function ($query) use ($search): void {
                    $escaped = addcslashes($search, '%_\\');
                    $query->where(fn ($nested) => $nested
                        ->where('name', 'like', "%{$escaped}%")
                        ->orWhere('full_name', 'like', "%{$escaped}%")
                        ->orWhere('INN', $search));
                })
                ->orderBy('name')
                ->limit(100)
                ->get(['id', 'name', 'full_name', 'INN', 'KPP']),
        ]);
    }
}
