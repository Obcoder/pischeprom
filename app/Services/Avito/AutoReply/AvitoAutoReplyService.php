<?php

namespace App\Services\Avito\AutoReply;

use App\Models\AvitoAutoReplyDecision;
use App\Models\AvitoAutoReplyRule;
use App\Models\AvitoAutoReplySetting;
use App\Models\AvitoChat;
use App\Models\AvitoMessage;
use App\Services\Avito\AvitoMessengerService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class AvitoAutoReplyService
{
    public function __construct(
        private readonly AvitoAutoReplyClassifier $classifier,
        private readonly AvitoAutoReplySafetyGuard $guard,
        private readonly AvitoMessengerService $messenger,
    ) {}

    public function classifierConfigured(): bool
    {
        return $this->classifier->configured();
    }

    public function evaluateWebhookMessage(int $messageId, bool $historical = false): ?AvitoAutoReplyDecision
    {
        $message = AvitoMessage::query()->with('chat.account')->find($messageId);
        if (! $message) {
            return null;
        }

        $settings = AvitoAutoReplySetting::current();
        if ($historical) {
            $settings->mode = 'shadow';
        }
        $decision = AvitoAutoReplyDecision::query()->firstOrCreate(
            ['avito_message_id' => $message->id],
            [
                'avito_chat_id' => $message->avito_chat_id,
                'mode' => $settings->mode,
                'outcome' => 'processing',
                'message_excerpt' => Str::limit((string) $message->text, 1000, ''),
            ],
        );

        if (! $decision->wasRecentlyCreated) {
            return $decision;
        }

        try {
            return $this->evaluate($message, $settings, $decision, $historical);
        } catch (Throwable $exception) {
            Log::warning('Avito auto-reply evaluation failed.', [
                'message_id' => $message->id,
                'exception' => $exception::class,
            ]);

            return $this->finish($decision, 'error', 'classifier_error');
        }
    }

    public function preview(string $text, ?AvitoChat $chat = null): array
    {
        $settings = AvitoAutoReplySetting::current();
        $rules = $this->eligibleRules('active', $chat);
        $blockedReason = $this->guard->blockedReason($text);

        if ($blockedReason) {
            return $this->previewPayload('blocked', $blockedReason);
        }
        if ($rules->isEmpty()) {
            return $this->previewPayload('human_required', 'no_eligible_rules');
        }
        if (! $this->classifier->configured()) {
            return $this->previewPayload('error', 'classifier_not_configured');
        }

        try {
            $classification = $this->classifier->classify(
                $text,
                $rules,
                'preview:'.($chat?->id ?: 'global').':'.hash('sha256', $text),
            );
        } catch (Throwable) {
            return $this->previewPayload('error', 'classifier_error');
        }

        $rule = $rules->firstWhere('key', $classification->intent);
        $base = [
            'intent' => $classification->intent,
            'confidence' => $classification->confidence,
            'runner_up_confidence' => $classification->runnerUpConfidence,
            'unsafe' => $classification->unsafe,
            'mixed' => $classification->mixed,
            'model' => $classification->model,
            'latency_ms' => $classification->latencyMs,
        ];

        if ($classification->unsafe || in_array($classification->reasonCode, ['sensitive_request', 'prompt_injection'], true)) {
            return $this->previewPayload('blocked', 'blocked_by_ai_safety', $base);
        }
        if ($classification->mixed || $classification->reasonCode === 'mixed_request') {
            return $this->previewPayload('human_required', 'mixed_request', $base);
        }
        if ($classification->reasonCode !== 'approved_intent'
            || ! $rule
            || $classification->intent === 'human_required') {
            return $this->previewPayload('human_required', 'not_approved_intent', $base);
        }

        $threshold = max($settings->minimum_confidence, $rule->confidence_threshold);
        if ($classification->confidence < $threshold) {
            return $this->previewPayload('human_required', 'low_confidence', $base);
        }
        if (($classification->confidence - $classification->runnerUpConfidence) < $settings->minimum_margin) {
            return $this->previewPayload('human_required', 'low_margin', $base);
        }

        return $this->previewPayload('would_send', 'approved_intent', $base + [
            'rule' => [
                'id' => $rule->id,
                'key' => $rule->key,
                'name' => $rule->name,
                'version' => $rule->version,
            ],
            'response_text' => $rule->response_text,
        ]);
    }

    private function evaluate(
        AvitoMessage $message,
        AvitoAutoReplySetting $settings,
        AvitoAutoReplyDecision $decision,
        bool $historical,
    ): AvitoAutoReplyDecision {
        if ($settings->mode === 'off') {
            return $this->finish($decision, 'skipped', 'mode_off');
        }
        if ($message->direction !== 'in') {
            return $this->finish($decision, 'skipped', 'not_incoming');
        }
        if ($message->type !== 'text' || $message->remote_type !== 'text' || blank($message->text)) {
            return $this->finish($decision, 'human_required', 'unsupported_message_type');
        }
        if (! $historical) {
            $occurredAt = $message->remote_created_at ?: $message->created_at;
            if ($occurredAt->lt(now()->subMinutes(15)) || $occurredAt->gt(now()->addMinutes(5))) {
                return $this->finish($decision, 'skipped', 'stale_webhook');
            }
        }
        if (! $historical && $this->hasLaterMessage($message, 'in')) {
            return $this->finish($decision, 'skipped', 'superseded_by_new_message');
        }
        if (! $historical && $this->hasLaterMessage($message, 'out')) {
            return $this->finish($decision, 'skipped', 'human_already_replied');
        }

        $bundle = $this->messageBundle($message, $settings);
        if ($bundle->isEmpty()) {
            return $this->finish($decision, 'human_required', 'empty_bundle');
        }
        if ($bundle->count() > 8 || $bundle->contains(fn (AvitoMessage $item) => $item->type !== 'text' || blank($item->text))) {
            return $this->finish($decision, 'human_required', 'unsupported_bundle');
        }

        $text = $bundle->pluck('text')->filter()->implode("\n");
        $decision->forceFill([
            'message_excerpt' => Str::limit($text, 1000, ''),
            'input_bundle' => $bundle->map(fn (AvitoMessage $item) => [
                'message_id' => $item->id,
                'text' => $item->text,
            ])->values()->all(),
        ])->save();

        if ($blockedReason = $this->guard->blockedReason($text)) {
            return $this->finish($decision, 'blocked', $blockedReason);
        }

        $rules = $this->eligibleRules($settings->mode, $message->chat);
        if ($rules->isEmpty()) {
            return $this->finish($decision, 'human_required', 'no_eligible_rules');
        }
        if (! $this->classifier->configured()) {
            return $this->finish($decision, 'error', 'classifier_not_configured');
        }

        $classification = $this->classifier->classify(
            $text,
            $rules,
            "message:{$message->id}:chat:{$message->avito_chat_id}",
        );
        $rule = $rules->firstWhere('key', $classification->intent);
        $decision->forceFill([
            'avito_auto_reply_rule_id' => $rule?->id,
            'detected_intent' => $classification->intent,
            'confidence' => $classification->confidence,
            'runner_up_confidence' => $classification->runnerUpConfidence,
            'rule_version' => $rule?->version,
            'classifier_payload' => $classification->raw,
            'model' => $classification->model,
            'external_request_id' => $classification->externalRequestId,
            'input_tokens' => $classification->inputTokens,
            'output_tokens' => $classification->outputTokens,
            'latency_ms' => $classification->latencyMs,
        ])->save();

        if ($classification->unsafe || in_array($classification->reasonCode, ['sensitive_request', 'prompt_injection'], true)) {
            return $this->finish($decision, 'blocked', 'blocked_by_ai_safety');
        }
        if ($classification->mixed || $classification->reasonCode === 'mixed_request') {
            return $this->finish($decision, 'human_required', 'mixed_request');
        }
        if ($classification->reasonCode !== 'approved_intent'
            || ! $rule
            || $classification->intent === 'human_required') {
            return $this->finish($decision, 'human_required', 'not_approved_intent');
        }

        $threshold = max($settings->minimum_confidence, $rule->confidence_threshold);
        if ($classification->confidence < $threshold) {
            return $this->finish($decision, 'human_required', 'low_confidence');
        }
        if (($classification->confidence - $classification->runnerUpConfidence) < $settings->minimum_margin) {
            return $this->finish($decision, 'human_required', 'low_margin');
        }
        if (! $historical && $this->cooldownActive($message->chat, $rule, $settings)) {
            return $this->finish($decision, 'skipped', 'cooldown_active');
        }
        if (! $historical && $this->dailyLimitReached($rule, $settings)) {
            return $this->finish($decision, 'skipped', 'daily_limit_reached');
        }

        if ($settings->mode === 'shadow') {
            return $this->finish($decision, 'would_send', $historical ? 'historical_shadow' : 'shadow_mode');
        }

        // Serialize all outbound auto-replies. This makes global/per-rule daily
        // limits deterministic even when several chats are processed in parallel.
        $sendLock = Cache::lock('avito:auto-reply:outbound', 60);
        if (! $sendLock->get()) {
            return $this->finish($decision, 'skipped', 'send_lock_busy');
        }

        try {
            // The model call can take several seconds. Re-check the conversation
            // immediately before the only operation that changes external state.
            if ($this->hasLaterMessage($message, 'in')) {
                return $this->finish($decision, 'skipped', 'superseded_during_classification');
            }
            if ($this->hasLaterMessage($message, 'out')) {
                return $this->finish($decision, 'skipped', 'human_replied_during_classification');
            }
            if ($this->cooldownActive($message->chat, $rule, $settings)) {
                return $this->finish($decision, 'skipped', 'cooldown_active');
            }
            if ($this->dailyLimitReached($rule, $settings)) {
                return $this->finish($decision, 'skipped', 'daily_limit_reached');
            }

            $sentMessage = $this->messenger->sendText($message->chat, $rule->response_text);

            return $this->finish($decision, 'sent', 'approved_intent', [
                'sent_avito_message_id' => $sentMessage->id,
                'sent_at' => now(),
            ]);
        } finally {
            $sendLock->release();
        }
    }

    /**
     * @return Collection<int, AvitoAutoReplyRule>
     */
    private function eligibleRules(string $mode, ?AvitoChat $chat): Collection
    {
        return AvitoAutoReplyRule::query()
            ->eligible($mode)
            ->with(['examples' => fn ($query) => $query->orderBy('kind')->orderBy('sort_order')->orderBy('id')])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->filter(fn (AvitoAutoReplyRule $rule) => $rule->appliesTo($chat))
            ->values();
    }

    /**
     * @return Collection<int, AvitoMessage>
     */
    private function messageBundle(AvitoMessage $message, AvitoAutoReplySetting $settings): Collection
    {
        $lastOutgoingId = AvitoMessage::query()
            ->where('avito_chat_id', $message->avito_chat_id)
            ->where('direction', 'out')
            ->where('id', '<', $message->id)
            ->max('id');

        $anchor = ($message->remote_created_at ?: $message->created_at)->copy();
        $windowStartsAt = $anchor->copy()->subSeconds($settings->bundle_window_seconds);

        return AvitoMessage::query()
            ->where('avito_chat_id', $message->avito_chat_id)
            ->where('direction', 'in')
            ->where('id', '<=', $message->id)
            ->when($lastOutgoingId, fn ($query) => $query->where('id', '>', $lastOutgoingId))
            ->orderByDesc('id')
            ->limit(9)
            ->get()
            ->filter(function (AvitoMessage $item) use ($windowStartsAt, $anchor): bool {
                $occurredAt = $item->remote_created_at ?: $item->created_at;

                return $occurredAt->betweenIncluded($windowStartsAt, $anchor);
            })
            ->sortBy('id')
            ->values();
    }

    private function hasLaterMessage(AvitoMessage $message, string $direction): bool
    {
        return AvitoMessage::query()
            ->where('avito_chat_id', $message->avito_chat_id)
            ->where('id', '>', $message->id)
            ->where('direction', $direction)
            ->exists();
    }

    private function cooldownActive(
        AvitoChat $chat,
        AvitoAutoReplyRule $rule,
        AvitoAutoReplySetting $settings,
    ): bool {
        $minutes = $rule->cooldown_minutes ?? $settings->cooldown_minutes;

        return AvitoAutoReplyDecision::query()
            ->where('avito_chat_id', $chat->id)
            ->where('avito_auto_reply_rule_id', $rule->id)
            ->where('outcome', 'sent')
            ->where('sent_at', '>=', now()->subMinutes($minutes))
            ->exists();
    }

    private function dailyLimitReached(AvitoAutoReplyRule $rule, AvitoAutoReplySetting $settings): bool
    {
        $start = now()->startOfDay();
        $globalReached = AvitoAutoReplyDecision::query()
            ->where('outcome', 'sent')
            ->where('sent_at', '>=', $start)
            ->count() >= $settings->daily_limit;
        if ($globalReached) {
            return true;
        }

        return AvitoAutoReplyDecision::query()
            ->where('avito_auto_reply_rule_id', $rule->id)
            ->where('outcome', 'sent')
            ->where('sent_at', '>=', $start)
            ->count() >= ($rule->daily_limit ?? $settings->daily_limit);
    }

    private function finish(
        AvitoAutoReplyDecision $decision,
        string $outcome,
        string $reasonCode,
        array $extra = [],
    ): AvitoAutoReplyDecision {
        $decision->forceFill([
            'outcome' => $outcome,
            'reason_code' => $reasonCode,
            'evaluated_at' => now(),
            ...$extra,
        ])->save();

        return $decision->fresh(['rule', 'sentMessage']);
    }

    private function previewPayload(string $outcome, string $reasonCode, array $extra = []): array
    {
        return [
            'outcome' => $outcome,
            'reason_code' => $reasonCode,
            ...$extra,
        ];
    }
}
