<?php

namespace App\Http\Controllers;

use App\Jobs\Avito\ProcessAvitoAutoReplyJob;
use App\Models\AvitoAutoReplyDecision;
use App\Models\AvitoAutoReplyRule;
use App\Models\AvitoAutoReplySetting;
use App\Models\AvitoChat;
use App\Models\AvitoMessage;
use App\Models\AvitoMessengerAccount;
use App\Services\Avito\AutoReply\AvitoAutoReplyService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AvitoAutoReplyController extends Controller
{
    public function index(Request $request, AvitoAutoReplyService $service): JsonResponse
    {
        $validated = $request->validate([
            'chat_id' => ['nullable', 'integer', 'exists:avito_chats,id'],
            'outcome' => ['nullable', 'string', Rule::in(array_keys($this->outcomeLabels()))],
            'rule_id' => ['nullable', 'integer', 'exists:avito_auto_reply_rules,id'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);
        $chat = isset($validated['chat_id'])
            ? AvitoChat::query()->findOrFail($validated['chat_id'])
            : null;
        $settings = AvitoAutoReplySetting::current();
        $rules = AvitoAutoReplyRule::query()
            ->with(['examples' => fn ($query) => $query->orderBy('kind')->orderBy('sort_order')->orderBy('id')])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        $decisions = AvitoAutoReplyDecision::query()
            ->with([
                'rule:id,key,name',
                'chat:id,peer_name,title,external_chat_id,avito_messenger_account_id',
                'chat.account:id,name,external_user_id',
                'sentMessage:id,text,external_message_id',
            ])
            ->when($chat, fn (Builder $query) => $query->where('avito_chat_id', $chat->id))
            ->when(filled($validated['outcome'] ?? null), fn (Builder $query) => $query->where('outcome', $validated['outcome']))
            ->when(filled($validated['rule_id'] ?? null), fn (Builder $query) => $query->where('avito_auto_reply_rule_id', $validated['rule_id']))
            ->latest('id')
            ->paginate((int) ($validated['per_page'] ?? 50));

        return response()->json([
            'settings' => $this->settingsPayload($settings),
            'rules' => $rules->map(fn (AvitoAutoReplyRule $rule) => $this->rulePayload($rule, $chat))->values(),
            'decisions' => [
                'data' => $decisions->getCollection()->map(fn (AvitoAutoReplyDecision $decision) => $this->decisionPayload($decision))->values(),
                'current_page' => $decisions->currentPage(),
                'last_page' => $decisions->lastPage(),
                'per_page' => $decisions->perPage(),
                'total' => $decisions->total(),
            ],
            'stats' => [
                'rules' => $rules->count(),
                'active_rules' => $rules->where('is_active', true)->where('is_approved', true)->count(),
                'sent_today' => AvitoAutoReplyDecision::query()->where('outcome', 'sent')->where('sent_at', '>=', now()->startOfDay())->count(),
                'would_send' => AvitoAutoReplyDecision::query()->where('outcome', 'would_send')->count(),
                'blocked' => AvitoAutoReplyDecision::query()->where('outcome', 'blocked')->count(),
                'human_required' => AvitoAutoReplyDecision::query()->where('outcome', 'human_required')->count(),
            ],
            'meta' => [
                'classifier_configured' => $service->classifierConfigured(),
                'modes' => $this->modeOptions(),
                'outcomes' => collect($this->outcomeLabels())->map(fn (string $label, string $value) => compact('value', 'label'))->values(),
                'reasons' => $this->reasonLabels(),
                'accounts' => AvitoMessengerAccount::query()
                    ->orderBy('name')
                    ->get(['id', 'name', 'external_user_id'])
                    ->map(fn (AvitoMessengerAccount $account) => [
                        'id' => $account->id,
                        'name' => $account->name ?: "Avito {$account->external_user_id}",
                        'external_user_id' => $account->external_user_id,
                    ]),
            ],
        ]);
    }

    public function updateSettings(Request $request, AvitoAutoReplyService $service): JsonResponse
    {
        $validated = $request->validate([
            'mode' => ['sometimes', Rule::in(AvitoAutoReplySetting::MODES)],
            'debounce_seconds' => ['sometimes', 'integer', 'min:5', 'max:120'],
            'bundle_window_seconds' => ['sometimes', 'integer', 'min:15', 'max:600'],
            'cooldown_minutes' => ['sometimes', 'integer', 'min:1', 'max:43200'],
            'daily_limit' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'minimum_confidence' => ['sometimes', 'numeric', 'min:0.8', 'max:1'],
            'minimum_margin' => ['sometimes', 'numeric', 'min:0.01', 'max:1'],
        ]);

        if (in_array($validated['mode'] ?? null, ['pilot', 'active'], true)) {
            if (! $service->classifierConfigured()) {
                throw ValidationException::withMessages([
                    'mode' => 'Нельзя включить отправку: Yandex AI Studio не настроен.',
                ]);
            }
            $eligible = AvitoAutoReplyRule::query()
                ->eligible($validated['mode'])
                ->exists();
            if (! $eligible) {
                throw ValidationException::withMessages([
                    'mode' => 'Сначала утвердите и включите хотя бы один подходящий сценарий.',
                ]);
            }
        }

        $settings = AvitoAutoReplySetting::current();
        $settings->fill($validated)->save();

        return response()->json([
            'message' => 'Настройки автоответов сохранены.',
            'settings' => $this->settingsPayload($settings->fresh()),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->ruleRules());
        $rule = DB::transaction(function () use ($validated): AvitoAutoReplyRule {
            $examples = $this->extractExamples($validated);
            $key = $this->uniqueKey($validated['key'] ?? $validated['name']);
            $rule = AvitoAutoReplyRule::query()->create([
                ...$validated,
                'key' => $key,
                'is_active' => $validated['is_active'] ?? false,
                'is_approved' => $validated['is_approved'] ?? false,
                'is_pilot' => $validated['is_pilot'] ?? false,
                'confidence_threshold' => $validated['confidence_threshold'] ?? 0.97,
                'sort_order' => $validated['sort_order'] ?? 0,
                'approved_at' => ! empty($validated['is_approved']) ? now() : null,
            ]);
            $this->replaceExamples($rule, $examples);

            return $rule;
        });

        return response()->json([
            'message' => 'Сценарий автоответа создан.',
            'rule' => $this->rulePayload($rule->fresh()->load('examples')),
        ], 201);
    }

    public function update(Request $request, AvitoAutoReplyRule $rule): JsonResponse
    {
        $validated = $request->validate($this->ruleRules(true, $rule));
        DB::transaction(function () use ($validated, $rule): void {
            $hasExamples = array_key_exists('positive_examples', $validated)
                || array_key_exists('negative_examples', $validated);
            $examples = $hasExamples ? $this->extractExamples($validated) : null;
            $wasApproved = $rule->is_approved;
            $nextApproved = array_key_exists('is_approved', $validated)
                ? (bool) $validated['is_approved']
                : $wasApproved;

            if (array_key_exists('key', $validated)) {
                $validated['key'] = Str::slug($validated['key'], '_');
            }
            $rule->fill([
                ...$validated,
                'version' => $rule->version + 1,
                'approved_at' => $nextApproved
                    ? ($wasApproved ? $rule->approved_at : now())
                    : null,
            ])->save();
            if ($examples !== null) {
                $this->replaceExamples($rule, $examples);
            }
        });

        return response()->json([
            'message' => 'Сценарий автоответа обновлён. Новая версия применяется только к следующим сообщениям.',
            'rule' => $this->rulePayload($rule->fresh()->load('examples')),
        ]);
    }

    public function destroy(AvitoAutoReplyRule $rule): JsonResponse
    {
        $rule->delete();

        return response()->json(['message' => 'Сценарий автоответа удалён. Журнал решений сохранён.']);
    }

    public function testPhrase(Request $request, AvitoAutoReplyService $service): JsonResponse
    {
        $validated = $request->validate([
            'text' => ['required', 'string', 'max:2000'],
            'chat_id' => ['nullable', 'integer', 'exists:avito_chats,id'],
        ]);
        $chat = isset($validated['chat_id'])
            ? AvitoChat::query()->findOrFail($validated['chat_id'])
            : null;

        return response()->json([
            'result' => $service->preview(trim($validated['text']), $chat),
            'message' => 'Проверка завершена без отправки в Avito.',
        ]);
    }

    public function analyzeArchive(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
            'chat_id' => ['nullable', 'integer', 'exists:avito_chats,id'],
        ]);
        $ids = AvitoMessage::query()
            ->where('direction', 'in')
            ->where('type', 'text')
            ->where('remote_type', 'text')
            ->whereNotNull('text')
            ->whereDoesntHave('autoReplyDecisions')
            ->when(isset($validated['chat_id']), fn (Builder $query) => $query->where('avito_chat_id', $validated['chat_id']))
            ->latest('id')
            ->limit((int) ($validated['limit'] ?? 50))
            ->pluck('id');

        foreach ($ids as $messageId) {
            ProcessAvitoAutoReplyJob::dispatch((int) $messageId, true);
        }

        return response()->json([
            'message' => "В безопасный анализ архива поставлено сообщений: {$ids->count()}.",
            'queued' => $ids->count(),
        ], 202);
    }

    private function ruleRules(bool $sometimes = false, ?AvitoAutoReplyRule $rule = null): array
    {
        $required = $sometimes ? 'sometimes' : 'required';

        return [
            'key' => [
                'sometimes', 'string', 'max:80', 'regex:/^[a-zA-Z0-9_-]+$/',
                Rule::unique('avito_auto_reply_rules', 'key')->ignore($rule?->id),
            ],
            'name' => [$required, 'string', 'max:160'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'response_text' => [$required, 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
            'is_approved' => ['sometimes', 'boolean'],
            'is_pilot' => ['sometimes', 'boolean'],
            'confidence_threshold' => ['sometimes', 'numeric', 'min:0.8', 'max:1'],
            'cooldown_minutes' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:43200'],
            'daily_limit' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:1000'],
            'account_ids' => ['sometimes', 'nullable', 'array', 'max:100'],
            'account_ids.*' => ['integer', 'exists:avito_messenger_accounts,id'],
            'context_ids' => ['sometimes', 'nullable', 'array', 'max:500'],
            'context_ids.*' => ['string', 'max:100'],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:100000'],
            'positive_examples' => [$required, 'array', 'min:1', 'max:50'],
            'positive_examples.*' => ['required', 'string', 'max:500'],
            'negative_examples' => ['sometimes', 'array', 'max:50'],
            'negative_examples.*' => ['required', 'string', 'max:500'],
        ];
    }

    private function extractExamples(array &$validated): array
    {
        $examples = [
            'positive' => array_values(array_unique(array_filter(array_map('trim', $validated['positive_examples'] ?? [])))),
            'negative' => array_values(array_unique(array_filter(array_map('trim', $validated['negative_examples'] ?? [])))),
        ];
        unset($validated['positive_examples'], $validated['negative_examples']);

        return $examples;
    }

    private function replaceExamples(AvitoAutoReplyRule $rule, array $examples): void
    {
        $rule->examples()->delete();
        foreach ($examples as $kind => $items) {
            foreach ($items as $index => $text) {
                $rule->examples()->create([
                    'kind' => $kind,
                    'text' => $text,
                    'sort_order' => ($index + 1) * 10,
                ]);
            }
        }
    }

    private function uniqueKey(string $value): string
    {
        $base = Str::limit(Str::slug($value, '_'), 70, '') ?: 'auto_reply';
        $key = $base;
        $suffix = 2;
        while (AvitoAutoReplyRule::query()->withTrashed()->where('key', $key)->exists()) {
            $key = "{$base}_{$suffix}";
            $suffix++;
        }

        return $key;
    }

    private function settingsPayload(AvitoAutoReplySetting $settings): array
    {
        return $settings->only([
            'id', 'mode', 'debounce_seconds', 'bundle_window_seconds', 'cooldown_minutes',
            'daily_limit', 'minimum_confidence', 'minimum_margin', 'updated_at',
        ]);
    }

    private function rulePayload(AvitoAutoReplyRule $rule, ?AvitoChat $chat = null): array
    {
        return [
            ...$rule->only([
                'id', 'key', 'name', 'description', 'response_text', 'is_active', 'is_approved',
                'is_pilot', 'confidence_threshold', 'cooldown_minutes', 'daily_limit', 'account_ids',
                'context_ids', 'version', 'sort_order', 'approved_at', 'created_at', 'updated_at',
            ]),
            'positive_examples' => $rule->examples->where('kind', 'positive')->pluck('text')->values(),
            'negative_examples' => $rule->examples->where('kind', 'negative')->pluck('text')->values(),
            'applies_to_chat' => $rule->appliesTo($chat),
        ];
    }

    private function decisionPayload(AvitoAutoReplyDecision $decision): array
    {
        return [
            ...$decision->only([
                'id', 'avito_message_id', 'avito_chat_id', 'mode', 'outcome', 'reason_code',
                'detected_intent', 'confidence', 'runner_up_confidence', 'rule_version', 'message_excerpt',
                'model', 'external_request_id', 'input_tokens', 'output_tokens', 'latency_ms',
                'evaluated_at', 'sent_at', 'created_at',
            ]),
            'outcome_label' => $this->outcomeLabels()[$decision->outcome] ?? $decision->outcome,
            'reason_label' => $this->reasonLabels()[$decision->reason_code] ?? $decision->reason_code,
            'rule' => $decision->rule?->only(['id', 'key', 'name']),
            'chat' => $decision->chat ? [
                'id' => $decision->chat->id,
                'name' => $decision->chat->peer_name ?: $decision->chat->title,
                'external_chat_id' => $decision->chat->external_chat_id,
                'account' => $decision->chat->account?->only(['id', 'name', 'external_user_id']),
            ] : null,
            'sent_message' => $decision->sentMessage?->only(['id', 'text', 'external_message_id']),
        ];
    }

    private function modeOptions(): array
    {
        return [
            ['value' => 'off', 'label' => 'Выключено', 'description' => 'Сообщения не анализируются и не отправляются.'],
            ['value' => 'shadow', 'label' => 'Наблюдение', 'description' => 'AI классифицирует и ведёт журнал, но ничего не отправляет.'],
            ['value' => 'pilot', 'label' => 'Пилот', 'description' => 'Отвечают только сценарии, отмеченные для пилота.'],
            ['value' => 'active', 'label' => 'Активно', 'description' => 'Отвечают все активные утверждённые сценарии.'],
        ];
    }

    private function outcomeLabels(): array
    {
        return [
            'processing' => 'Обрабатывается',
            'sent' => 'Отправлено',
            'would_send' => 'Был бы отправлен',
            'human_required' => 'Нужен человек',
            'blocked' => 'Заблокировано',
            'skipped' => 'Пропущено',
            'error' => 'Ошибка без ответа',
        ];
    }

    private function reasonLabels(): array
    {
        return [
            'approved_intent' => 'Утверждённый сценарий',
            'shadow_mode' => 'Режим наблюдения — отправка отключена',
            'historical_shadow' => 'Безопасный анализ архивного сообщения',
            'mode_off' => 'Автоответы выключены',
            'not_incoming' => 'Не входящее сообщение',
            'unsupported_message_type' => 'Формат требует человека',
            'stale_webhook' => 'Слишком старое событие',
            'superseded_by_new_message' => 'Есть более новое сообщение клиента',
            'human_already_replied' => 'Человек уже ответил',
            'empty_bundle' => 'Нет текста для анализа',
            'unsupported_bundle' => 'В серии есть вложение или слишком много сообщений',
            'invalid_message' => 'Некорректный текст',
            'blocked_prompt_injection' => 'Попытка повлиять на инструкции AI',
            'blocked_sensitive_request' => 'Запрос внутренних или конфиденциальных данных',
            'blocked_restricted_topic' => 'Наличие, цена или время доставки требуют человека',
            'blocked_encoded_instruction' => 'Подозрительная закодированная инструкция',
            'blocked_by_ai_safety' => 'AI обнаружил опасный запрос',
            'mixed_request' => 'В сообщении несколько тем',
            'not_approved_intent' => 'Нет утверждённого сценария',
            'low_confidence' => 'Недостаточная уверенность',
            'low_margin' => 'Слишком близкие варианты',
            'no_eligible_rules' => 'Нет подходящих активных сценариев',
            'classifier_not_configured' => 'Yandex AI Studio не настроен',
            'classifier_error' => 'AI недоступен или вернул ошибку',
            'cooldown_active' => 'Для сценария действует пауза',
            'daily_limit_reached' => 'Достигнут дневной лимит',
            'send_lock_busy' => 'Другой автоответ уже отправляется',
            'superseded_during_classification' => 'Во время анализа пришло новое сообщение',
            'human_replied_during_classification' => 'Во время анализа ответил человек',
        ];
    }
}
