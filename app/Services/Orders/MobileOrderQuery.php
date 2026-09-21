<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\OrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MobileOrderQuery
{
    public function validated(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'filter' => ['nullable', Rule::in(['all', 'today', 'awaiting', 'ready', 'shipped'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
    }

    /** The list and delivery map always use identical order selection. */
    public function build(array $data): Builder
    {
        $search = trim($data['search'] ?? '');
        $query = Order::query()->when($search !== '', fn (Builder $query) => $query
            ->where(function (Builder $query) use ($search): void {
                $query->where('number', 'like', '%'.$search.'%')
                    ->orWhereHas('entity', fn (Builder $entity) => $entity->where('name', 'like', '%'.$search.'%'));
            }));

        if (in_array($data['filter'] ?? 'all', ['awaiting', 'ready'], true)) {
            $query->whereNull('closed_at')->whereHas('status', fn (Builder $status) => $status
                ->where('code', OrderStatus::OPEN)->where('is_closed', false));
        }

        match ($data['filter'] ?? 'all') {
            'today' => $query->whereDate('submitted_at', now()->toDateString()),
            'awaiting' => $query->whereNull('shipped_sale_id')->whereNull('prepared_at'),
            'ready' => $query->whereNull('shipped_sale_id')->whereNotNull('prepared_at'),
            'shipped' => $query->whereNotNull('shipped_sale_id'),
            default => null,
        };

        return $query->orderByDesc('submitted_at')->orderByDesc('id');
    }
}
