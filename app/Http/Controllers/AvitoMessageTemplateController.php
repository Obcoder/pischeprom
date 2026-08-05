<?php

namespace App\Http\Controllers;

use App\Models\AvitoChat;
use App\Models\AvitoMessage;
use App\Models\AvitoMessageTemplate;
use App\Services\Avito\AvitoMessageTemplateService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AvitoMessageTemplateController extends Controller
{
    public function index(Request $request, AvitoMessageTemplateService $templates): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'category' => ['nullable', Rule::in(array_keys(AvitoMessageTemplate::CATEGORIES))],
            'active' => ['nullable', 'boolean'],
            'favorite' => ['nullable', 'boolean'],
        ]);
        $search = trim((string) ($validated['search'] ?? ''));
        $items = AvitoMessageTemplate::query()
            ->when($search !== '', function (Builder $query) use ($search): void {
                $needle = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
                $query->where(function (Builder $nested) use ($needle): void {
                    $nested->where('name', 'like', $needle)
                        ->orWhere('body', 'like', $needle);
                });
            })
            ->when(filled($validated['category'] ?? null), fn (Builder $query) => $query->where('category', $validated['category']))
            ->when(array_key_exists('active', $validated), fn (Builder $query) => $query->where('is_active', $validated['active']))
            ->when(array_key_exists('favorite', $validated), fn (Builder $query) => $query->where('is_favorite', $validated['favorite']))
            ->orderByDesc('is_favorite')
            ->orderBy('sort_order')
            ->orderByDesc('usage_count')
            ->orderBy('name')
            ->limit(500)
            ->get();

        return response()->json([
            'items' => $items->map(fn (AvitoMessageTemplate $template) => $templates->templatePayload($template))->values(),
            'meta' => $this->meta($templates),
        ]);
    }

    public function store(Request $request, AvitoMessageTemplateService $templates): JsonResponse
    {
        $validated = $request->validate($this->templateRules());
        $template = AvitoMessageTemplate::query()->create([
            ...$validated,
            'category' => $validated['category'] ?? 'general',
            'is_active' => $validated['is_active'] ?? true,
            'is_favorite' => $validated['is_favorite'] ?? false,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return response()->json([
            'message' => 'Шаблон сообщения создан.',
            'template' => $templates->templatePayload($template),
        ], 201);
    }

    public function update(
        Request $request,
        AvitoMessageTemplate $template,
        AvitoMessageTemplateService $templates,
    ): JsonResponse {
        $validated = $request->validate($this->templateRules(true));
        $template->fill($validated)->save();

        return response()->json([
            'message' => 'Шаблон сообщения обновлён.',
            'template' => $templates->templatePayload($template->fresh()),
        ]);
    }

    public function destroy(AvitoMessageTemplate $template): JsonResponse
    {
        $template->delete();

        return response()->json(['message' => 'Шаблон сообщения удалён.']);
    }

    public function preview(
        Request $request,
        AvitoChat $chat,
        AvitoMessageTemplate $template,
        AvitoMessageTemplateService $templates,
    ): JsonResponse {
        return response()->json([
            'template' => $templates->templatePayload($template),
            'preview' => $templates->render($template, $chat, $request->validate($this->contextRules())),
        ]);
    }

    public function send(
        Request $request,
        AvitoChat $chat,
        AvitoMessageTemplate $template,
        AvitoMessageTemplateService $templates,
    ): JsonResponse {
        $result = $templates->send($template, $chat, $request->validate($this->contextRules()));

        return response()->json([
            'message' => 'Шаблон отправлен в чат Avito.',
            'item' => $this->messagePayload($result['message']),
            'preview' => $result['preview'],
            'template' => $templates->templatePayload($template->fresh()),
        ], 201);
    }

    private function templateRules(bool $sometimes = false): array
    {
        $presence = $sometimes ? 'sometimes' : 'required';

        return [
            'name' => [$presence, 'string', 'max:160'],
            'category' => [$sometimes ? 'sometimes' : 'nullable', Rule::in(array_keys(AvitoMessageTemplate::CATEGORIES))],
            'body' => [$presence, 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
            'is_favorite' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:100000'],
        ];
    }

    private function contextRules(): array
    {
        return [
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'good_id' => ['nullable', 'integer', 'exists:goods,id'],
            'telephone_id' => ['nullable', 'integer', 'exists:telephones,id'],
            'building_id' => ['nullable', 'integer', 'exists:buildings,id'],
        ];
    }

    private function meta(AvitoMessageTemplateService $templates): array
    {
        return [
            'categories' => collect(AvitoMessageTemplate::CATEGORIES)
                ->map(fn (string $label, string $value) => compact('value', 'label'))
                ->values(),
            'variables' => $templates->variables(),
            'message_limit' => AvitoMessageTemplateService::MESSAGE_LIMIT,
        ];
    }

    private function messagePayload(AvitoMessage $message): array
    {
        return [
            'id' => $message->id,
            'external_message_id' => $message->external_message_id,
            'author_id' => $message->author_id,
            'direction' => $message->direction,
            'type' => $message->type,
            'remote_type' => $message->remote_type,
            'text' => $message->text,
            'is_read' => $message->is_read,
            'remote_created_at' => $message->remote_created_at,
            'attachments' => [],
            'contact_candidates' => [],
        ];
    }
}
