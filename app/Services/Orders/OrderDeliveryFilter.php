<?php

namespace App\Services\Orders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderDeliveryFilter
{
    public static function rules(Request $request): array
    {
        if (in_array($request->input('delivery_unscheduled'), ['true', 'false'], true)) {
            $request->merge(['delivery_unscheduled' => $request->input('delivery_unscheduled') === 'true']);
        }

        return [
            'delivery_date' => [
                ...OrderDeliveryDateService::dateRules(),
                Rule::prohibitedIf($request->boolean('delivery_unscheduled')),
            ],
            'delivery_unscheduled' => ['sometimes', 'boolean'],
        ];
    }

    public static function apply(Builder $query, array $data): Builder
    {
        if (filled($data['delivery_date'] ?? null)) {
            $query->where('delivery_date', $data['delivery_date']);
        } elseif (filter_var($data['delivery_unscheduled'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $query->whereNull('delivery_date');
        }

        return $query;
    }
}
