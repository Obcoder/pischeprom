<?php

namespace App\Services\Checks;

use App\Models\Check;
use App\Models\CheckService;
use App\Models\Service;

class CheckServiceItemService
{
    public function create(
        Check $check,
        array $data,
        bool $withRelations = true
    ): CheckService {
        $service = Service::findOrFail($data['service_id']);

        $item = CheckService::create([
            'check_id' => $check->id,
            'service_id' => $service->id,
            'quantity' => $data['quantity'] ?? 1,
            'measure_id' => $data['measure_id'] ?? null,
            'expense_article_id' => $data['expense_article_id'] ?? $service->expense_article_id,
            'price' => $data['price'] ?? 0,
        ]);

        return $withRelations ? $item->fresh($this->relations()) : $item;
    }

    public function update(CheckService $item, array $data): CheckService
    {
        if (array_key_exists('service_id', $data) && ! array_key_exists('expense_article_id', $data)) {
            $service = Service::findOrFail($data['service_id']);
            $data['expense_article_id'] = $service->expense_article_id;
        }

        $item->update($data);

        return $item->fresh($this->relations());
    }

    public function delete(CheckService $item): void
    {
        $item->delete();
    }

    private function relations(): array
    {
        return [
            'service.expenseArticle',
            'service.project',
            'expenseArticle',
            'measure',
        ];
    }
}
