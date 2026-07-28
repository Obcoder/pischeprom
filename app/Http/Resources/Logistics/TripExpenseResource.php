<?php

namespace App\Http\Resources\Logistics;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TripExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'trip_id' => $this->trip_id,
            'check_id' => $this->check_id,
            'check' => $this->whenLoaded('check', fn () => $this->check ? [
                'id' => $this->check->id,
                'date' => $this->check->date?->toDateString(),
                'amount' => (float) $this->check->amount,
                'entity' => $this->check->entity ? [
                    'id' => $this->check->entity->id,
                    'name' => $this->check->entity->name,
                ] : null,
            ] : null),
            'expense_category_id' => $this->expense_category_id,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'code' => $this->category->code,
                'name' => $this->category->name,
            ]),
            'allocated_amount' => (float) $this->allocated_amount,
            'currency_code' => $this->currency_code,
            'occurred_at' => $this->occurred_at?->toISOString(),
            'quantity' => $this->quantity !== null ? (float) $this->quantity : null,
            'unit' => $this->unit,
            'unit_price' => $this->unit_price !== null ? (float) $this->unit_price : null,
            'notes' => $this->notes,
            'metadata' => $this->metadata,
            'has_check' => $this->check_id !== null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
