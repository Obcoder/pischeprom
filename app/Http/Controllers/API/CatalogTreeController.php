<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CatalogField;
use App\Models\CatalogLevel;
use App\Models\CatalogNode;
use App\Services\Catalog\CatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

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
        ]);
        $level = CatalogLevel::create(['entity_type' => 'custom', 'sort_order' => 0, ...$data]);

        return response()->json(['data' => $level->load('fields')], 201);
    }

    public function updateLevel(Request $request, CatalogLevel $level): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'entity_type' => ['sometimes', Rule::in([$level->entity_type])],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
        ]);
        $level->update($data);

        return response()->json(['data' => $level->fresh()->load('fields')]);
    }

    public function destroyLevel(CatalogLevel $level): JsonResponse
    {
        DB::transaction(function () use ($level): void {
            $level = CatalogLevel::lockForUpdate()->findOrFail($level->id);
            abort_if($level->entity_type !== 'custom', 409, 'Базовый уровень связан с учётом. Его можно переименовать.');
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
            foreach ($level->nodes()->get() as $node) {
                $values = $node->properties ?? [];
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
                $node->update(['properties' => $values]);
            }
        });

        return response()->json(['data' => $field->fresh()]);
    }

    public function destroyField(CatalogField $field): JsonResponse
    {
        DB::transaction(function () use ($field): void {
            CatalogLevel::orderBy('id')->lockForUpdate()->get();
            foreach (CatalogNode::where('level_id', $field->level_id)->get() as $node) {
                $values = $node->properties ?? [];
                unset($values[$field->key]);
                $node->update(['properties' => $values]);
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

        return $request->validate([
            'level_id' => [$required, 'integer', 'exists:catalog_levels,id'],
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:catalog_nodes,id'],
            'name' => [$required, 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'image' => ['sometimes', 'nullable', 'string', 'max:2048', 'regex:~^(https?://|/storage/)~i'],
            'description' => ['sometimes', 'nullable', 'string', 'max:100000'],
            'meta_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'meta_description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'is_published' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:1000000'],
            'properties' => ['sometimes', 'nullable', 'array'],
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
