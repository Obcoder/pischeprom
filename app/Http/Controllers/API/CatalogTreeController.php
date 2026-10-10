<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CatalogField;
use App\Models\CatalogLevel;
use App\Models\CatalogNode;
use App\Services\Catalog\CatalogService;
use App\Services\Goods\GoodMeasurement;
use App\Services\Goods\GoodTradeCodes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CatalogTreeController extends Controller
{
    public function __construct(private readonly CatalogService $catalog) {}

    public function index(): JsonResponse
    {
        return response()->json($this->catalog->snapshot());
    }

    public function storeLevel(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'entity_type' => ['sometimes', 'in:custom'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
            'display_mode' => ['sometimes', Rule::in(['tabs', 'tree', 'list'])],
            'is_domain' => ['sometimes', 'boolean'],
        ]);
        $level = CatalogLevel::create([
            'entity_type' => 'custom', 'sort_order' => 0, 'is_domain' => false,
            'display_mode' => ($data['is_domain'] ?? false) ? 'tabs' : 'tree', ...$data,
        ]);

        return response()->json(['data' => $level->load('fields')], 201);
    }

    public function updateLevel(Request $request, CatalogLevel $level): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'entity_type' => ['sometimes', Rule::in([$level->entity_type])],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
            'display_mode' => ['sometimes', Rule::in(['tabs', 'tree', 'list'])],
            'is_domain' => ['sometimes', 'boolean'],
        ]);
        DB::transaction(function () use ($level, $data): void {
            CatalogLevel::orderBy('id')->lockForUpdate()->get();
            $level = CatalogLevel::findOrFail($level->id);
            if (($data['is_domain'] ?? false) && ! $level->is_domain) {
                if ($level->nodes()->whereNotNull('parent_id')->exists()) {
                    throw ValidationException::withMessages(['is_domain' => 'Сначала перенесите элементы этого уровня в корень классификации.']);
                }
                $data['display_mode'] ??= 'tabs';
            }
            if (array_key_exists('is_domain', $data) && ! $data['is_domain']
                && \App\Models\CatalogSiteDomain::query()->whereIn('catalog_node_id', $level->nodes()->select('id'))->exists()) {
                throw ValidationException::withMessages(['is_domain' => 'Сначала удалите адреса сайтов у элементов этого уровня.']);
            }
            $level->update($data);
        });

        return response()->json(['data' => $level->fresh()->load('fields')]);
    }

    public function destroyLevel(CatalogLevel $level): JsonResponse
    {
        DB::transaction(function () use ($level): void {
            $level = CatalogLevel::lockForUpdate()->findOrFail($level->id);
            abort_if($level->nodes()->exists(), 409, 'Сначала перенесите или удалите элементы этого уровня.');
            $level->delete();
        });

        return response()->json(null, 204);
    }

    public function storeField(Request $request): JsonResponse
    {
        $data = $this->fieldData($request);
        $field = DB::transaction(function () use ($data): CatalogField {
            CatalogLevel::orderBy('id')->lockForUpdate()->get();

            return CatalogField::create(['required' => false, 'is_public' => false, 'sort_order' => 0, 'options' => [], ...$data]);
        });

        return response()->json(['data' => $field], 201);
    }

    public function updateField(Request $request, CatalogField $field): JsonResponse
    {
        $data = $this->fieldData($request, $field);
        DB::transaction(function () use ($field, $data): void {
            CatalogLevel::orderBy('id')->lockForUpdate()->get();
            $oldKey = $field->key;
            $field->update($data);
            $level = $field->level()->with('fields')->firstOrFail();
            foreach (CatalogNode::where('level_id', $level->id)->orWhereNotNull('properties_by_level')->get() as $node) {
                $active = $node->level_id === $level->id;
                $archive = $node->properties_by_level ?? [];
                if (! $active && ! array_key_exists($level->id, $archive)) {
                    continue;
                }
                $values = $active ? ($node->properties ?? []) : $archive[$level->id];
                if ($oldKey !== $field->key && array_key_exists($oldKey, $values)) {
                    $values[$field->key] = $values[$oldKey];
                    unset($values[$oldKey]);
                }
                // Changing a definition must preserve existing data; newly required fields
                // are completed when each entry is next edited.
                if (isset($values[$field->key])) {
                    $singleFieldLevel = clone $level;
                    $singleFieldLevel->setRelation('fields', collect([$field]));
                    $validated = $this->catalog->validateProperties($singleFieldLevel, [$field->key => $values[$field->key]]);
                    $values[$field->key] = $validated[$field->key];
                }
                if ($active) {
                    $node->properties = $values;
                }
                if (array_key_exists($level->id, $archive)) {
                    $archive[$level->id] = $values;
                    $node->properties_by_level = $archive;
                }
                $node->save();
            }
        });

        return response()->json(['data' => $field->fresh()]);
    }

    public function destroyField(CatalogField $field): JsonResponse
    {
        DB::transaction(function () use ($field): void {
            CatalogLevel::orderBy('id')->lockForUpdate()->get();
            foreach (CatalogNode::where('level_id', $field->level_id)->orWhereNotNull('properties_by_level')->get() as $node) {
                $values = $node->properties ?? [];
                if ($node->level_id === $field->level_id) {
                    unset($values[$field->key]);
                    $node->properties = $values;
                }
                $archive = $node->properties_by_level ?? [];
                if (array_key_exists($field->level_id, $archive)) {
                    unset($archive[$field->level_id][$field->key]);
                    $node->properties_by_level = $archive;
                }
                $node->save();
            }
            $field->delete();
        });

        return response()->json(null, 204);
    }

    public function storeNode(Request $request): JsonResponse
    {
        $node = $this->catalog->saveNode($this->nodeData($request));

        return response()->json(['data' => $this->catalog->nodePayload($node)], 201);
    }

    public function overview(CatalogNode $node): JsonResponse
    {
        return response()->json(['data' => $this->catalog->goodOverview($node)]);
    }

    public function updateNode(Request $request, CatalogNode $node): JsonResponse
    {
        $node = $this->catalog->saveNode($this->nodeData($request, $node), $node);

        return response()->json(['data' => $this->catalog->nodePayload($node)]);
    }

    public function destroyNode(CatalogNode $node): JsonResponse
    {
        try {
            $this->catalog->destroyNode($node);
        } catch (\Illuminate\Database\QueryException $exception) {
            $sqlState = (string) $exception->getCode();
            $driverCode = (int) ($exception->errorInfo[1] ?? 0);
            $foreignKeyViolation = $sqlState === '23503'
                || ($sqlState === '23000' && $driverCode === 1451)
                || ($sqlState === '23000' && $driverCode === 19 && str_contains($exception->getMessage(), 'FOREIGN KEY constraint failed'));
            if (! $foreignKeyViolation) {
                throw $exception;
            }
            abort(409, 'Сущность используется в связанных записях и не может быть удалена.');
        }

        return response()->json(null, 204);
    }

    public function uploadImage(Request $request, CatalogNode $node): JsonResponse
    {
        $request->validate(['image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:5120']]);
        $path = $request->file('image')->store('catalog-images', 'public');
        try {
            $node = $this->catalog->saveNode(['image' => Storage::disk('public')->url($path)], $node);
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($path);
            throw $exception;
        }

        return response()->json(['data' => $this->catalog->nodePayload($node)]);
    }

    private function nodeData(Request $request, ?CatalogNode $node = null): array
    {
        $required = $node ? 'sometimes' : 'required';
        if (is_array($request->input('good'))) {
            $request->merge(['good' => [...$request->input('good'), ...GoodTradeCodes::normalize($request->input('good'))]]);
        }
        $goodRules = [
            'incoming_code' => ['sometimes', 'nullable', 'string', 'max:255'],
            'denominator' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:1000000000'],
            'country_id' => ['sometimes', 'nullable', 'integer', 'exists:countries,id'],
            'vat_rate_id' => ['sometimes', 'nullable', 'integer', 'exists:vat_rates,id'],
            'products' => ['sometimes', 'array', 'max:1000'],
            'products.*' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'fields' => ['sometimes', 'array', 'max:1000'],
            'fields.*' => ['required', 'integer', 'distinct', 'exists:fields,id'],
            'avatar_source_url' => ['sometimes', 'nullable', 'string', 'max:2048', 'regex:~^(https?://|/storage/)~i'],
            'avatar_thumb_source_url' => ['sometimes', 'nullable', 'string', 'max:2048', 'regex:~^(https?://|/storage/)~i'],
            'remove_ava' => ['sometimes', 'boolean'],
            ...GoodTradeCodes::rules(),
            ...GoodMeasurement::rules(),
        ];
        $nested = ['good' => ['sometimes', 'array:'.implode(',', array_filter(array_keys($goodRules), fn (string $key): bool => ! str_contains($key, '.')))]];
        foreach ($goodRules as $key => $rules) {
            $nested['good.'.$key] = $rules;
        }

        return $request->validate([
            'level_id' => ['sometimes', 'nullable', 'integer', 'exists:catalog_levels,id'],
            'entity_type' => ['sometimes', 'nullable', Rule::in(['category', 'product', 'good', 'custom'])],
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:catalog_nodes,id'],
            'name' => [$required, 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'image' => ['sometimes', 'nullable', 'string', 'max:2048', 'regex:~^(https?://|/storage/)~i'],
            'description' => ['sometimes', 'nullable', 'string', 'max:100000'],
            'h1' => ['sometimes', 'nullable', 'string', 'max:255'],
            'meta_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'meta_description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'is_published' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
            'properties' => ['sometimes', 'nullable', 'array'],
            'domain_hosts' => ['sometimes', 'array', 'max:20'],
            'domain_hosts.*' => ['required', 'string', 'max:253'],
            ...$nested,
        ]);
    }

    private function fieldData(Request $request, ?CatalogField $field = null): array
    {
        $required = $field ? 'sometimes' : 'required';
        $levelId = $field?->level_id ?? $request->input('level_id');
        $data = $request->validate([
            'level_id' => [$required, 'integer', 'exists:catalog_levels,id', ...($field ? [Rule::in([$field->level_id])] : [])],
            'key' => [$required, 'string', 'max:80', 'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('catalog_fields')->where('level_id', $levelId)->ignore($field?->id)],
            'label' => [$required, 'string', 'max:255'],
            'type' => [$required, Rule::in(['text', 'textarea', 'number', 'boolean', 'date', 'url', 'select'])],
            'required' => ['sometimes', 'boolean'],
            'is_public' => ['sometimes', 'boolean'],
            'options' => ['sometimes', 'nullable', 'array', 'max:100'],
            'options.*' => ['required', 'string', 'max:255', 'distinct'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
        ]);
        if (($data['type'] ?? $field?->type) === 'select' && empty($data['options'] ?? $field?->options)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['options' => 'Добавьте хотя бы один вариант.']);
        }

        return $data;
    }
}
