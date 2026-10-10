<?php

namespace App\Services\Catalog;

use App\Models\CatalogNode;

class CatalogLandingTemplates
{
    public function forNode(CatalogNode $node, ?array $payload = null): array
    {
        $schema = app(CatalogLandingContent::class)->schema();
        $content = ['template' => 'overview', 'blocks' => []];
        foreach (['hero', 'catalog', 'contact', 'sources'] as $section) {
            $content[$section] = $this->defaults($schema[$section]);
        }
        $content['hero'] = [
            ...$content['hero'], 'title' => $payload['name'] ?? $node->name,
            'description' => strip_tags($payload['description'] ?? $node->description ?? ''),
            'image' => $payload['image'] ?? $node->image ?? '',
            'imageAlt' => $payload['name'] ?? $node->name,
            'action' => 'Смотреть ассортимент', 'actionUrl' => '#catalog',
        ];
        $content['catalog'] = [...$content['catalog'], 'enabled' => true, 'title' => 'Ассортимент', 'mode' => 'branch'];
        $content['contact'] = [...$content['contact'], 'enabled' => false];
        $content['sources'] = [...$content['sources'], 'enabled' => false, 'title' => 'Источники'];
        $editorial = [...$content, 'template' => 'editorial'];
        $editorial['blocks'] = [[
            'id' => 'about', 'type' => 'text', 'title' => 'О разделе', 'navTitle' => 'О разделе', 'enabled' => true,
            'data' => [...$this->defaults($schema['blockTypes']['text']['fields']), 'paragraphs' => []],
        ]];

        return [
            ['key' => 'overview', 'label' => 'Обзор раздела с ассортиментом', 'content' => $content],
            ['key' => 'editorial', 'label' => 'Каталог с гидом', 'content' => $editorial],
        ];
    }

    public function named(string $key): ?array
    {
        if (! preg_match('/^[a-z][a-z0-9_-]*$/', $key)) {
            return null;
        }
        $path = resource_path('landings/'.$key.'.json');
        if (! is_file($path) || $key === 'schema') {
            return null;
        }

        return json_decode(file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
    }

    /** Read-only compatibility for nodes imported after the one-time migration. */
    public function legacyForNode(CatalogNode $node): ?array
    {
        if ($node->entity_type !== 'product') {
            return null;
        }
        $settings = config('product-pages.pages.'.$node->entity_id);
        if (! is_array($settings) || (int) ($settings['catalog_node_id'] ?? 0) !== $node->id
            || ! is_string($settings['guide'] ?? null)) {
            return null;
        }
        $content = $this->named($settings['guide']);
        if (! $content) {
            return null;
        }
        $content['catalog']['source_product_ids'] = array_values($settings['source_product_ids'] ?? []);
        $content['catalog']['inline_good_ids'] = array_values($settings['inline_good_ids'] ?? []);
        $seo = [];
        foreach (['meta_title' => 'title', 'meta_description' => 'description'] as $field => $setting) {
            if (blank($node->{$field}) && filled($settings[$setting] ?? null)) {
                $seo[$field] = $settings[$setting];
            }
        }

        return ['content' => $content, 'seo' => $seo];
    }

    private function defaults(array $fields): array
    {
        $values = [];
        foreach ($fields as $field) {
            $values[$field['key']] = $field['default'] ?? match ($field['type']) {
                'list' => [],
                'object' => $this->defaults($field['fields']),
                'boolean' => false,
                'integer', 'number' => 0,
                'select' => $field['options'][0]['value'] ?? '',
                default => '',
            };
        }

        return $values;
    }
}
