<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Unit\StoreUnitEmailRequest;
use App\Models\Email;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UnitEmailController extends Controller
{
    public function options(Request $request, Unit $unit): JsonResponse
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:254'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:25'],
        ]);

        $search = trim((string) ($data['search'] ?? ''));
        $normalizedSearch = Str::lower($search);
        $limit = (int) ($data['limit'] ?? 25);

        $attachedIds = $unit->emails()
            ->pluck('emails.id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $emails = Email::query()
            ->select(['id', 'address', 'name'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('address', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%");
                });
            })
            ->when(
                $normalizedSearch !== '',
                fn ($query) => $query->orderByRaw(
                    'CASE WHEN address = ? THEN 0 ELSE 1 END',
                    [$normalizedSearch],
                ),
            )
            ->orderBy('address')
            ->limit($limit)
            ->get()
            ->map(fn (Email $email): array => [
                'id' => $email->id,
                'address' => $email->address,
                'name' => $email->name,
                'attached_to_unit' => in_array((int) $email->id, $attachedIds, true),
            ])
            ->values();

        return response()->json(['data' => $emails]);
    }

    public function store(StoreUnitEmailRequest $request, Unit $unit): JsonResponse
    {
        $data = $request->validated();

        $result = DB::transaction(function () use ($data, $unit): array {
            $created = false;

            if (isset($data['email_id'])) {
                $email = Email::query()
                    ->lockForUpdate()
                    ->findOrFail($data['email_id']);
            } else {
                $address = $data['address'];
                $email = Email::query()
                    ->where('address', $address)
                    ->lockForUpdate()
                    ->first();

                if (! $email) {
                    $email = Email::onlyTrashed()
                        ->where('address', $address)
                        ->lockForUpdate()
                        ->first();
                }

                if ($email) {
                    if ($email->trashed()) {
                        $email->restore();
                    }

                    $updates = [];

                    if (! $email->is_active) {
                        $updates['is_active'] = true;
                    }

                    if (! $email->name && ! empty($data['name'])) {
                        $updates['name'] = $data['name'];
                    }

                    if ($updates !== []) {
                        $email->update($updates);
                    }
                } else {
                    $email = Email::query()->create([
                        'address' => $address,
                        'name' => $data['name'] ?? null,
                        'source' => 'unit_manual',
                        'is_active' => true,
                    ]);
                    $created = true;
                }
            }

            $attached = ! $unit->emails()
                ->whereKey($email->getKey())
                ->exists();

            if ($attached) {
                $unit->emails()->attach($email->getKey());
            }

            return compact('email', 'created', 'attached');
        });

        /** @var Email $email */
        $email = $result['email'];

        return response()->json([
            'data' => [
                'id' => $email->id,
                'address' => $email->address,
                'name' => $email->name,
                'is_active' => (bool) $email->is_active,
            ],
            'created' => $result['created'],
            'attached' => $result['attached'],
        ], $result['created'] ? 201 : 200);
    }
}
