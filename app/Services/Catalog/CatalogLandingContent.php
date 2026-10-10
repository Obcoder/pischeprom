<?php

namespace App\Services\Catalog;

use Closure;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CatalogLandingContent
{
    public function schema(): array
    {
        return json_decode(file_get_contents(resource_path('landings/schema.json')), true, 64, JSON_THROW_ON_ERROR);
    }

    /** Content stays structured; Vue renders text escaped, never as raw HTML. */
    public function validate(array $content): array
    {
        if (strlen(json_encode($content, JSON_THROW_ON_ERROR)) > 512000) {
            throw ValidationException::withMessages(['content' => 'Содержимое лендинга превышает 500 КБ.']);
        }
        $schema = $this->schema();
        $rules = [
            'content' => ['required', 'array:template,hero,catalog,blocks,contact,sources'],
            'content.template' => ['required', Rule::in(['editorial', 'overview'])],
            'content.blocks' => ['present', 'array', 'list', 'max:50'],
            'content.blocks.*' => ['array:id,type,title,navTitle,enabled,data'],
            'content.blocks.*.id' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_-]*$/', 'distinct'],
            'content.blocks.*.type' => ['required', Rule::in(array_keys($schema['blockTypes']))],
            'content.blocks.*.title' => ['present', 'nullable', 'string', 'max:1000'],
            'content.blocks.*.navTitle' => ['present', 'nullable', 'string', 'max:255'],
            'content.blocks.*.enabled' => ['required', $this->boolean()],
        ];
        foreach (['hero', 'catalog', 'contact', 'sources'] as $section) {
            $this->objectRules($rules, 'content.'.$section, $schema[$section], true);
        }
        foreach (is_array($content['blocks'] ?? null) ? $content['blocks'] : [] as $index => $block) {
            if (! is_int($index) || ! is_array($block)) {
                continue;
            }
            $fields = is_string($block['type'] ?? null) ? ($schema['blockTypes'][$block['type']]['fields'] ?? null) : null;
            if ($fields !== null) {
                $this->objectRules($rules, 'content.blocks.'.$index.'.data', $fields, true);
            }
        }

        $validated = Validator::make(['content' => $content], $rules)->validate()['content'];
        $anchors = array_fill_keys(['catalog', 'contact', 'sources', 'hero', 'class-main', 'hero-title', 'photo-credits'], true);
        $registerAnchor = function (?string $id, string $path) use (&$anchors): void {
            if (blank($id)) {
                return;
            }
            if (isset($anchors[$id])) {
                throw ValidationException::withMessages([$path => 'Якорь уже используется другим разделом страницы.']);
            }
            $anchors[$id] = true;
        };
        foreach ($validated['blocks'] as $index => $block) {
            $registerAnchor($block['id'], 'content.blocks.'.$index.'.id');
            if ($block['type'] === 'faq') {
                foreach ($block['data']['items'] ?? [] as $itemIndex => $item) {
                    $registerAnchor($item['id'] ?? null, 'content.blocks.'.$index.'.data.items.'.$itemIndex.'.id');
                }
            }
        }
        foreach ($validated['sources']['items'] ?? [] as $index => $source) {
            $registerAnchor($source['id'] ?? null, 'content.sources.items.'.$index.'.id');
        }
        foreach (['hero', 'catalog', 'contact', 'sources'] as $section) {
            $validated[$section] = $this->normalize($validated[$section], $schema[$section]);
        }
        foreach ($validated['blocks'] as &$block) {
            $block['title'] ??= '';
            $block['navTitle'] ??= '';
            $block['data'] = $this->normalize($block['data'], $schema['blockTypes'][$block['type']]['fields']);
        }

        return $validated;
    }

    private function normalize(array $data, array $fields): array
    {
        foreach ($fields as $field) {
            $key = $field['key'];
            if (! array_key_exists($key, $data)) {
                continue;
            }
            $data[$key] = match ($field['type']) {
                'integer' => (int) $data[$key],
                'number' => (float) $data[$key],
                'text', 'textarea', 'url' => $data[$key] ?? '',
                'object' => $this->normalize($data[$key], $field['fields']),
                'list' => array_map(fn ($item) => isset($field['fields'])
                    ? $this->normalize($item, $field['fields'])
                    : ($field['itemType'] === 'integer' ? (int) $item : ($item ?? '')), $data[$key]),
                default => $data[$key],
            };
        }

        return $data;
    }

    private function objectRules(array &$rules, string $path, array $fields, bool $required = false): void
    {
        $keys = implode(',', array_column($fields, 'key'));
        $rules[$path] = [$required ? 'present' : 'sometimes', 'array:'.$keys];
        foreach ($fields as $field) {
            $this->fieldRules($rules, $path.'.'.$field['key'], $field);
        }
    }

    private function fieldRules(array &$rules, string $path, array $field): void
    {
        $type = $field['type'];
        $required = $field['required'] ?? false;
        if ($type === 'object') {
            $this->objectRules($rules, $path, $field['fields'], $required);

            return;
        }
        if ($type === 'list') {
            $rules[$path] = [$required ? 'required' : 'sometimes', 'array', 'list', 'max:100'];
            if (isset($field['fields'])) {
                $this->objectRules($rules, $path.'.*', $field['fields'], true);
            } else {
                // Empty table cells carry meaning and must survive form middleware
                // converting empty strings to null. IDs remain required integers.
                $emptyCell = $field['key'] === 'cells' && $field['itemType'] === 'text';
                $this->fieldRules($rules, $path.'.*', [...$field, 'type' => $field['itemType'], 'required' => ! $emptyCell]);
                if ($field['itemType'] === 'integer') {
                    $rules[$path.'.*'][] = 'distinct';
                }
            }

            return;
        }
        $rules[$path] = [$required ? 'required' : 'sometimes'];
        // Laravel converts blank form strings to null. Normalize them to empty
        // text after validation while preserving strict numbers and booleans.
        if (! $required && in_array($type, ['text', 'textarea', 'url'], true)) {
            $rules[$path][] = 'nullable';
        }
        $rules[$path] = [...$rules[$path], ...match ($type) {
            'boolean' => [$this->boolean()],
            'integer' => ['integer', 'min:'.($field['min'] ?? 1), 'max:'.($field['max'] ?? PHP_INT_MAX)],
            'number' => ['numeric', 'min:'.($field['min'] ?? -1000000000), 'max:'.($field['max'] ?? 1000000000)],
            'select' => [Rule::in(array_column($field['options'], 'value'))],
            'url' => ['string', 'max:2048', $this->url()],
            'textarea' => ['string', 'max:20000'],
            default => ['string', 'max:5000'],
        }];
        if (($field['key'] ?? null) === 'id') {
            $rules[$path][] = 'max:64';
            $rules[$path][] = 'regex:/^[a-z][a-z0-9_-]*$/';
        }
    }

    private function boolean(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_bool($value)) {
                $fail('Значение должно быть логическим.');
            }
        };
    }

    private function url(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value)) {
                return;
            }
            if ($value === '') {
                return;
            }
            $safe = ! preg_match('/[\x00-\x20\x7f\\\\]/', $value)
                && (preg_match('~^/(?!/)~', $value)
                    || preg_match('/^#[a-zA-Z][a-zA-Z0-9_-]*$/', $value)
                    || (preg_match('~^https?://~i', $value) && filter_var($value, FILTER_VALIDATE_URL)));
            if (! $safe) {
                $fail('Укажите адрес http(s), путь от корня сайта или якорь раздела.');
            }
        };
    }
}
