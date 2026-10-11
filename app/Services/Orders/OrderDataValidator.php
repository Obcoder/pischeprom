<?php

namespace App\Services\Orders;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderDataValidator
{
    public function validated(Request $request, ?Order $order = null): array
    {
        if ($request->exists('delivery_date')) {
            // Legacy order routes predate CRM middleware. Protect the new planning field
            // on those routes as well, so a date-only route cannot be bypassed by PUT.
            $user = $request->user() ?? auth('sanctum')->user();
            abort_unless($user, 401, 'Unauthenticated.');
            abort_unless(OrderDeliveryAccess::allowed($user, $order ? 'orders.edit' : 'orders.create'), 403, 'Недостаточно прав для планирования доставки заказов.');
        }

        return $request->validate([
            'number' => [
                'nullable',
                'string',
                'max:40',
                Rule::unique('orders', 'number')->ignore($order?->id),
            ],
            'entity_id' => ['required', 'integer', 'exists:entities,id'],
            'order_status_id' => ['required', 'integer', 'exists:order_statuses,id'],
            'contact_telephone_id' => ['nullable', 'integer', 'exists:telephones,id'],
            'preferred_delivery_time' => ['nullable', 'string', 'max:255'],
            'delivery_date' => OrderDeliveryDateService::dateRules(),
            'delivery_version' => [Rule::requiredIf($order && $request->exists('delivery_date')), 'nullable', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'internal_comment' => ['nullable', 'string', 'max:10000'],
            'currency_code' => ['sometimes', 'required', 'string', 'regex:/^[A-Za-z]{3,8}$/'],
            'submitted_at' => ['nullable', 'date'],
            'building_ids' => ['nullable', 'array'],
            'building_ids.*' => ['integer', 'distinct', 'exists:buildings,id'],
            'building_apartments' => ['sometimes', 'array'],
            'building_apartments.*' => ['nullable', 'integer', 'exists:apartments,id'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.good_id' => ['required', 'integer', 'distinct', 'exists:goods,id'],
            'items.*.id' => ['nullable', 'integer', 'distinct'],
            'items.*.measure_id' => ['nullable', 'integer', 'exists:measures,id'],
            'items.*.measurement' => ['nullable', 'array:measure_id,unit_label,kilograms_per_unit'],
            'items.*.measurement.measure_id' => ['nullable', 'integer'],
            'items.*.measurement.unit_label' => ['nullable', 'string', 'max:255'],
            'items.*.measurement.kilograms_per_unit' => ['nullable', 'numeric', 'gt:0'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001', 'max:999999999', 'decimal:0,3'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'items.*.line_total' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'items.*.currency_code' => ['sometimes', 'required', 'string', 'regex:/^[A-Za-z]{3,8}$/'],
        ]);
    }
}
