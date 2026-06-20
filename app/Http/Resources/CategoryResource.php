<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'local_code' => $this->local_code,
            'field_id' => $this->field_id,
            'field' => $this->whenLoaded('field', function () {
                if (!$this->field) {
                    return null;
                }

                return [
                    'id' => $this->field->id,
                    'title' => $this->field->title,
                    'name' => $this->field->name,
                ];
            }),
            'image' => $this->image,
            'image_alt' => $this->image_alt,
            'is_published' => (bool) $this->is_published,
            'is_featured' => (bool) $this->is_featured,
            'sort_order' => $this->sort_order,
            'published_at' => $this->published_at,
            'h1' => $this->h1,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'seo_text' => $this->seo_text,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'keywords' => $this->keywords ?? [],
            'robots' => $this->robots,
            'canonical_url' => $this->canonical_url,
            'og_title' => $this->og_title,
            'og_description' => $this->og_description,
            'og_image' => $this->og_image,
            'public_url' => $this->slug
                ? route('category.show', ['category' => $this->slug])
                : route('category.show', ['category' => $this->id]),
            'products_count' => $this->whenCounted('products'),
            'goods_count' => $this->whenCounted('goods'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'products' => $this->whenLoaded('products', function () {
                return $this->products->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'rus' => $product->rus,
                        'name' => $product->name ?? null,
                        'image' => $product->image ?? null,
                        'goods_count' => $product->goods_count ?? null,
                    ];
                });
            }),

            'goods' => $this->whenLoaded('goods', function () {
                return $this->goods->map(function ($good) {
                    return [
                        'id' => $good->id,
                        'name' => $good->name,
                        'slug' => $good->slug,
                        'ava_image' => $good->ava_image,
                        'ava_thumb' => $good->ava_thumb,
                        'description' => $good->description,
                    ];
                });
            }),
        ];
    }
}
