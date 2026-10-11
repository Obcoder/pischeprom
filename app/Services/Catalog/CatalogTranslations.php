<?php

namespace App\Services\Catalog;

use App\Models\CatalogNode;
use App\Models\Product;

/** Names belong to the catalog placement and survive changes of level or entity type. */
class CatalogTranslations
{
    public const PRODUCT_COLUMNS = [
        'ru' => 'rus', 'en' => 'eng', 'zh' => 'zh', 'es' => 'es', 'ar' => 'ar',
        'hi' => 'hi', 'ur' => 'ur', 'de' => 'de', 'fr' => 'fr', 'pt' => 'po',
        'it' => 'it', 'nl' => 'nl', 'tr' => 'tu', 'fa' => 'fa', 'vi' => 'vi',
        'ja' => 'ja', 'ko' => 'ko', 'he' => 'he', 'id' => 'idn',
    ];

    public function rules(): array
    {
        $rules = ['translations' => ['present', 'array:'.implode(',', array_keys(self::PRODUCT_COLUMNS))]];
        foreach (self::PRODUCT_COLUMNS as $locale => $column) {
            $rules['translations.'.$locale] = ['sometimes', $locale === 'ru' ? 'required' : 'nullable', 'string', 'max:255'];
        }

        return $rules;
    }

    public function payload(CatalogNode $node, string $name, ?Product $product = null): array
    {
        $translations = array_fill_keys(array_keys(self::PRODUCT_COLUMNS), null);
        foreach ($node->translations as $translation) {
            if (array_key_exists($translation->locale, $translations)) {
                $translations[$translation->locale] = $translation->name;
            }
        }
        if ($product) {
            // Legacy editors, AI tools and bulk imports still write Product columns.
            // Read those current values until the node changes its backing entity.
            foreach (self::PRODUCT_COLUMNS as $locale => $column) {
                $translations[$locale] = $this->normalize($product->{$column});
            }
        }
        $translations['ru'] = $name;

        return $translations;
    }

    public function syncProduct(CatalogNode $node, Product $product): void
    {
        $values = [];
        foreach (self::PRODUCT_COLUMNS as $locale => $column) {
            if ($locale !== 'ru') {
                $values[$locale] = $product->{$column};
            }
        }
        $this->saveVariants($node, $values);
    }

    /** Apply a partial patch; omitted names remain unchanged, empty names are removed. */
    public function saveVariants(CatalogNode $node, array $values): void
    {
        $existing = $node->translations->keyBy('locale');
        foreach ($values as $locale => $value) {
            if ($locale === 'ru') {
                continue;
            }
            $name = $this->normalize($value);
            $translation = $existing->get($locale);
            if ($name === null) {
                $translation?->delete();
            } elseif (! $translation) {
                $node->translations()->create(['locale' => $locale, 'name' => $name]);
            } elseif ($translation->name !== $name) {
                $translation->update(['name' => $name]);
            }
        }
        $node->unsetRelation('translations');
    }

    public function productAttributes(CatalogNode $node): array
    {
        $attributes = [];
        foreach ($node->translations as $translation) {
            $column = self::PRODUCT_COLUMNS[$translation->locale] ?? null;
            if ($column && $translation->locale !== 'ru') {
                $attributes[$column] = $translation->name;
            }
        }

        return $attributes;
    }

    public function normalize(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
