<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\Unit\StoreUnitEmailRequest;
use App\Models\Email;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class UnitEmailController extends Controller
{
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
