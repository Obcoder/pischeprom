<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UnitResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name, // Основное поле; замените на реальные поля вашей модели Unit
            'created_at' => $this->created_at->toDateTimeString(), // Форматирование даты
            'updated_at' => $this->updated_at->toDateTimeString(),

            // Отношения (используйте nested ресурсы для сложных структур)
//            'labels' => LabelResource::collection($this->whenLoaded('labels')), // Если есть LabelResource
//            'consumptions' => ConsumptionResource::collection($this->whenLoaded('consumptions')),
//            'products' => ProductResource::collection($this->whenLoaded('products')),
//            'quotations' => QuotationResource::collection($this->whenLoaded('quotations')),
//            'entities' => [
//                'sales' => SaleResource::collection($this->whenLoaded('entities')->sales ?? []), // Адаптируйте под вашу структуру
//            ],
//            'stages' => StageResource::collection($this->whenLoaded('stages')),

            // Nested для quotations (если загружены)
//            'quotations_good' => GoodResource::collection($this->whenLoaded('quotations')->pluck('good')),
//            'quotations_measure' => MeasureResource::collection($this->whenLoaded('quotations')->pluck('measure')),

            // Дополнительные поля: добавьте, если нужно (например, computed свойства)
            // 'custom_field' => $this->someMethod(),
        ];
    }
}
