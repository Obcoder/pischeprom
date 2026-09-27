<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\OrderStatus;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class OrderStatusController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => OrderStatus::query()
                ->withCount('orders')
                ->ordered()
                ->get()
                ->map(fn (OrderStatus $status) => $this->serialize($status)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['sort_order'] ??= min(65535, (int) OrderStatus::query()->max('sort_order') + 10);
        $data['is_closed'] ??= false;

        $status = OrderStatus::query()->create($data)->loadCount('orders');

        return response()->json(['data' => $this->serialize($status)], 201);
    }

    public function update(Request $request, OrderStatus $orderStatus): JsonResponse
    {
        $data = $this->validated($request, $orderStatus);

        $status = DB::transaction(function () use ($orderStatus, $data): OrderStatus {
            $status = OrderStatus::query()->whereKey($orderStatus->id)->lockForUpdate()->firstOrFail();
            $errors = [];

            if ($status->isSystem() && isset($data['code']) && $data['code'] !== $status->code) {
                $errors['code'] = 'Код системного статуса нельзя изменить.';
            }

            if (array_key_exists('is_closed', $data) && (bool) $data['is_closed'] !== $status->is_closed) {
                if ($status->isSystem()) {
                    $errors['is_closed'] = 'Назначение системного статуса нельзя изменить.';
                } elseif ($status->orders()->exists()) {
                    $errors['is_closed'] = 'Сначала переведите заказы в другой статус, чтобы изменить признак закрытия.';
                }
            }

            if ($errors !== []) {
                throw ValidationException::withMessages($errors);
            }

            $status->update($data);

            return $status->loadCount('orders');
        });

        return response()->json(['data' => $this->serialize($status)]);
    }

    public function destroy(OrderStatus $orderStatus): JsonResponse
    {
        try {
            DB::transaction(function () use ($orderStatus): void {
                $status = OrderStatus::query()->whereKey($orderStatus->id)->lockForUpdate()->firstOrFail();

                if ($status->isSystem()) {
                    throw ValidationException::withMessages(['status' => 'Системный статус нельзя удалить.']);
                }

                if ($status->orders()->exists()) {
                    $this->statusInUse();
                }

                $status->delete();
            });
        } catch (QueryException $exception) {
            // The foreign key also protects orders assigned while deletion was starting.
            if ($orderStatus->orders()->exists()) {
                $this->statusInUse();
            }

            throw $exception;
        }

        return response()->json(null, 204);
    }

    private function validated(Request $request, ?OrderStatus $status = null): array
    {
        return $request->validate([
            'code' => [
                $status ? 'sometimes' : 'required',
                'required',
                'string',
                'max:32',
                'regex:/^[a-z][a-z0-9_-]*$/',
                Rule::unique('order_statuses', 'code')->ignore($status?->id),
            ],
            'name' => [$status ? 'sometimes' : 'required', 'required', 'string', 'max:64'],
            'color' => ['nullable', 'string', 'regex:/^#(?:[a-fA-F0-9]{3}|[a-fA-F0-9]{6})$/'],
            'sort_order' => ['sometimes', 'required', 'integer', 'min:0', 'max:65535'],
            'is_closed' => ['sometimes', 'required', 'boolean'],
        ], [
            'code.regex' => 'Код должен начинаться с латинской буквы и содержать только строчные латинские буквы, цифры, дефис или подчёркивание.',
            'code.unique' => 'Статус с таким кодом уже существует.',
            'color.regex' => 'Укажите цвет в формате #RGB или #RRGGBB.',
        ]);
    }

    private function serialize(OrderStatus $status): array
    {
        return [
            ...$status->only(['id', 'code', 'name', 'color', 'sort_order', 'is_closed']),
            'orders_count' => (int) $status->orders_count,
            'is_system' => $status->isSystem(),
        ];
    }

    private function statusInUse(): never
    {
        throw ValidationException::withMessages([
            'status' => 'Статус используется в заказах. Сначала переведите их в другой статус.',
        ]);
    }
}
