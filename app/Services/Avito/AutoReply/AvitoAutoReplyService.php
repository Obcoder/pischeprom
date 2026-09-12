<?php

namespace App\Services\Avito\AutoReply;

use App\Models\AvitoAutoReplyDecision;
use App\Models\AvitoAutoReplyRule;
use App\Models\AvitoAutoReplySetting;
use App\Models\AvitoChat;
use App\Models\AvitoMessage;
use App\Services\Avito\AvitoMessengerService;
use Illuminate\Database\Eloquent\Builder;
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
        $lock = Cache::lock("avito:auto-reply:message:{$messageId}", 180);
        if (! $lock->get()) {
            return AvitoAutoReplyDecision::where('avito_message_id', $messageId)->first();
        }
        try {
            return $this->evaluateLockedMessage($messageId, $historical);
        } finally {
            $lock->release();
        }
    }

    private function evaluateLockedMessage(int $messageId, bool $historical): ?AvitoAutoReplyDecision
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

        $retryable = ($decision->outcome === 'error' && $decision->reason_code === 'classifier_error')
            || ($decision->outcome === 'skipped' && $decision->reason_code === 'send_lock_busy')
            || ($decision->outcome === 'processing' && $decision->updated_at->lt(now()->subSeconds(150)));
        if (! $decision->wasRecentlyCreated && (! $retryable || $decision->reason_code === 'historical_shadow')) {
            return $decision;
        }
        // An archival decision must never be promoted into a live send by retry.
        if (! $decision->wasRecentlyCreated && $decision->mode === 'shadow') {
            $settings->mode = 'shadow';
        }
        $decision->forceFill(['outcome' => 'processing', 'mode' => $settings->mode])->save();

        try {
            return $this->evaluate($message, $settings, $decision, $historical);
        } catch (AvitoAutoReplyCancelled $exception) {
            return $this->finish($decision, 'skipped', $exception->reasonCode);
        } catch (Throwable $exception) {
            Log::warning('Avito auto-reply evaluation failed.', [
                'message_id' => $message->id,
                'exception' => $exception::class,
            ]);

            return $this->finish($decision, 'error', in_array($decision->outcome, ['sending', 'sent'], true) ? 'send_error' : 'classifier_error');
        }
    }

    public function preview(string $text, ?AvitoChat $chat = null): array
    {
        $settings = AvitoAutoReplySetting::current();
        $rules = $this->eligibleRules($settings->mode === 'pilot' ? 'pilot' : 'active', $chat);
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
                $settings->response_mode === 'assistant',
            );
        } catch (Throwable) {
            return $this->previewPayload('error', 'classifier_error');
        }

        $base = [
            'intent' => $classification->intent,
            'confidence' => $classification->confidence,
            'runner_up_confidence' => $classification->runnerUpConfidence,
            'unsafe' => $classification->unsafe,
            'mixed' => $classification->mixed,
            'model' => $classification->model,
            'latency_ms' => $classification->latencyMs,
        ];

        $resolved = $this->resolveResponse($classification, $rules, $settings);
        if ($resolved['outcome'] !== 'would_send') {
            return $this->previewPayload($resolved['outcome'], $resolved['reason_code'], $base);
        }
        $rule = $rules->firstWhere('key', $classification->intent);

        return $this->previewPayload('would_send', 'approved_intent', $base + [
            'rule' => [
                'id' => $rule->id,
                'key' => $rule->key,
                'name' => $rule->name,
                'version' => $rule->version,
            ],
            'response_text' => $resolved['response_text'],
            'matched_rule_keys' => $resolved['rules']->pluck('key')->all(),
        ]);
    }

    private function evaluate(
        AvitoMessage $message,
        AvitoAutoReplySetting $settings,
        AvitoAutoReplyDecision $decision,
        bool $historical,
    ): AvitoAutoReplyDecision {
        if ($settings->emergency_stopped_at) {
            return $this->finish($decision, 'skipped', 'emergency_stopped');
        }
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
            $settings->response_mode === 'assistant',
        );
        if (AvitoAutoReplySetting::current()->emergency_stopped_at) {
            return $this->finish($decision, 'skipped', 'emergency_stopped');
        }
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

        $resolved = $this->resolveResponse($classification, $rules, $settings);
        if ($resolved['outcome'] !== 'would_send') {
            return $this->finish($decision, $resolved['outcome'], $resolved['reason_code']);
        }
        $matchedRules = $resolved['rules'];
        $responseText = $resolved['response_text'];
        $decision->forceFill([
            'response_text' => $responseText,
            'matched_rule_keys' => $matchedRules->pluck('key')->all(),
        ])->save();
        if (! $historical && $matchedRules->contains(fn ($matched) => $this->cooldownActive($message->chat, $matched, $settings))) {
            return $this->finish($decision, 'skipped', 'cooldown_active');
        }
        if (! $historical && $matchedRules->contains(fn ($matched) => $this->dailyLimitReached($matched, $settings))) {
            return $this->finish($decision, 'skipped', 'daily_limit_reached');
        }

        if ($settings->mode === 'shadow') {
            return $this->finish($decision, 'would_send', $historical ? 'historical_shadow' : 'shadow_mode');
        }

        // Serialize all outbound auto-replies. This makes global/per-rule daily
        // limits deterministic even when several chats are processed in parallel.
        $sendLock = Cache::lock('avito:auto-reply:outbound', 180);
        if (! $sendLock->get()) {
            return $this->finish($decision, 'skipped', 'send_lock_busy');
        }

        try {
            if ($blocked = $this->outboundBlockReason($message, $settings, $matchedRules, $bundle)) {
                return $this->finish($decision, 'skipped', $blocked);
            }

            // Persist before touching Avito: a timeout after remote acceptance
            // must never turn a queue retry into a duplicate customer message.
            $blocked = AvitoAutoReplySetting::withLockedCurrent(function (AvitoAutoReplySetting $current) use ($settings, $decision): ?string {
                if ($current->emergency_stopped_at) {
                    return 'emergency_stopped';
                }
                if ($current->getAttributes() !== $settings->getAttributes()) {
                    return 'settings_changed';
                }

                $decision->forceFill(['outcome' => 'sending'])->save();

                return null;
            });
            if ($blocked) {
                return $this->finish($decision, 'skipped', $blocked);
            }
            // Release the settings row before any network I/O so the stop button
            // remains immediate even if Avito takes seconds to accept a request.
            if (AvitoAutoReplySetting::current()->emergency_stopped_at) {
                return $this->finish($decision, 'skipped', 'emergency_stopped');
            }
            $sentMessage = $this->messenger->sendText($message->chat, $responseText, function () use ($message, $settings, $matchedRules, $bundle): void {
                // Token refresh may have waited on Avito after our earlier
                // checks. Revoke the send before the actual messages request.
                if ($blocked = $this->outboundBlockReason($message, $settings, $matchedRules, $bundle)) {
                    throw new AvitoAutoReplyCancelled($blocked);
                }
                if (AvitoAutoReplySetting::current()->emergency_stopped_at) {
                    throw new AvitoAutoReplyCancelled('emergency_stopped');
                }
            });

            return $this->finish($decision, 'sent', 'approved_intent', [
                'sent_avito_message_id' => $sentMessage->id,
                'sent_at' => now(),
            ]);
        } finally {
            $sendLock->release();
        }
    }

    /** The same guards apply after generation and after a possible token refresh. */
    private function outboundBlockReason(
        AvitoMessage $message,
        AvitoAutoReplySetting $settings,
        Collection $matchedRules,
        Collection $bundle,
    ): ?string {
        $currentSettings = AvitoAutoReplySetting::current();
        if ($currentSettings->emergency_stopped_at) {
            return 'emergency_stopped';
        }
        if ($currentSettings->getAttributes() !== $settings->getAttributes()) {
            return 'settings_changed';
        }
        $currentRules = $this->eligibleRules($settings->mode, $message->chat);
        foreach ($matchedRules as $matched) {
            $currentRule = $currentRules->firstWhere('id', $matched->id);
            if (! $currentRule || $currentRule->getAttributes() !== $matched->getAttributes()) {
                return 'rule_changed';
            }
        }
        if ($this->hasLaterMessage($message, 'in')) {
            return 'superseded_during_classification';
        }
        if ($this->hasLaterMessage($message, 'out')) {
            return 'human_replied_during_classification';
        }
        $currentMessage = AvitoMessage::find($message->id);
        if (! $currentMessage || $this->messageSnapshot(collect([$currentMessage])) !== $this->messageSnapshot(collect([$message]))
            || $this->messageSnapshot($this->messageBundle($currentMessage, $settings)) !== $this->messageSnapshot($bundle)) {
            return 'message_changed';
        }
        if ($matchedRules->contains(fn ($matched) => $this->cooldownActive($message->chat, $matched, $settings))) {
            return 'cooldown_active';
        }
        if ($matchedRules->contains(fn ($matched) => $this->dailyLimitReached($matched, $settings))) {
            return 'daily_limit_reached';
        }

        return null;
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
            ->filter(fn (AvitoAutoReplyRule $rule) => $this->guard->responseBlockedReason($rule->response_text, collect([$rule])) === null)
            ->values();
    }

    /**
     * @return Collection<int, AvitoMessage>
     */
    private function messageBundle(AvitoMessage $message, AvitoAutoReplySetting $settings): Collection
    {
        $lastOutgoing = AvitoMessage::query()
            ->where('avito_chat_id', $message->avito_chat_id)
            ->where('direction', 'out')
            ->where(fn (Builder $query) => $this->beforeOrEqual($query, $message))
            ->orderByRaw('COALESCE(remote_created_at, created_at) DESC')
            ->orderByDesc('id')
            ->first();

        $anchor = ($message->remote_created_at ?: $message->created_at)->copy();
        $windowStartsAt = $anchor->copy()->subSeconds($settings->bundle_window_seconds);

        return AvitoMessage::query()
            ->where('avito_chat_id', $message->avito_chat_id)
            ->where('direction', 'in')
            ->where(fn (Builder $query) => $this->beforeOrEqual($query, $message))
            ->when($lastOutgoing, fn (Builder $query) => $query->where(fn (Builder $query) => $this->after($query, $lastOutgoing)))
            ->whereRaw('COALESCE(remote_created_at, created_at) >= ?', [$windowStartsAt])
            ->orderByRaw('COALESCE(remote_created_at, created_at) DESC')
            ->orderByDesc('id')
            ->limit(9)
            ->get()
            ->filter(function (AvitoMessage $item) use ($windowStartsAt, $anchor): bool {
                $occurredAt = $item->remote_created_at ?: $item->created_at;

                return $occurredAt->betweenIncluded($windowStartsAt, $anchor);
            })
            ->reverse()
            ->values();
    }

    private function hasLaterMessage(AvitoMessage $message, string $direction): bool
    {
        return AvitoMessage::query()
            ->where('avito_chat_id', $message->avito_chat_id)
            ->where(fn (Builder $query) => $this->after($query, $message))
            ->where('direction', $direction)
            ->exists();
    }

    private function messageSnapshot(Collection $messages): array
    {
        return $messages->map(fn (AvitoMessage $message) => [
            ...$message->only(['id', 'avito_chat_id', 'direction', 'type', 'remote_type', 'text']),
            'remote_created_at' => $message->remote_created_at?->timestamp,
        ])->all();
    }

    private function after(Builder $query, AvitoMessage $message): void
    {
        $time = $message->remote_created_at ?: $message->created_at;
        $query->whereRaw('COALESCE(remote_created_at, created_at) > ?', [$time])
            ->orWhere(fn (Builder $query) => $query->whereRaw('COALESCE(remote_created_at, created_at) = ?', [$time])->where('id', '>', $message->id));
    }

    private function beforeOrEqual(Builder $query, AvitoMessage $message): void
    {
        $time = $message->remote_created_at ?: $message->created_at;
        $query->whereRaw('COALESCE(remote_created_at, created_at) < ?', [$time])
            ->orWhere(fn (Builder $query) => $query->whereRaw('COALESCE(remote_created_at, created_at) = ?', [$time])->where('id', '<=', $message->id));
    }

    /** The exact same policy resolves previews, archival analysis and live sends. */
    private function resolveResponse(
        AvitoAutoReplyClassification $classification,
        Collection $rules,
        AvitoAutoReplySetting $settings,
    ): array {
        $flexible = $settings->response_mode === 'assistant';
        if ($classification->unsafe || in_array($classification->reasonCode, ['sensitive_request', 'prompt_injection'], true)) {
            return $this->previewPayload('blocked', 'blocked_by_ai_safety');
        }
        if ((! $flexible && $classification->mixed) || $classification->reasonCode === 'mixed_request') {
            return $this->previewPayload('human_required', 'mixed_request');
        }
        $keys = $flexible ? $classification->matchedIntents : [$classification->intent];
        $matched = $rules->whereIn('key', $keys)->values();
        if ($classification->reasonCode !== 'approved_intent'
            || ! in_array($classification->intent, $keys, true)
            || $matched->isEmpty() || $matched->count() !== count(array_unique($keys))) {
            return $this->previewPayload('human_required', 'not_approved_intent');
        }
        if ($classification->confidence < max($settings->minimum_confidence, $matched->max('confidence_threshold'))) {
            return $this->previewPayload('human_required', 'low_confidence');
        }
        // In assistant mode runner-up is another useful topic, not an exclusive
        // competing answer. A margin requirement incorrectly rejects safe bundles.
        if (! $flexible && ($classification->confidence - $classification->runnerUpConfidence) < $settings->minimum_margin) {
            return $this->previewPayload('human_required', 'low_margin');
        }
        $response = $flexible ? trim((string) $classification->responseText) : $matched->first()->response_text;
        if ($reason = $this->guard->responseBlockedReason($response, $matched)) {
            return $this->previewPayload('blocked', $reason);
        }

        return $this->previewPayload('would_send', 'approved_intent', ['response_text' => $response, 'rules' => $matched]);
    }

    private function cooldownActive(
        AvitoChat $chat,
        AvitoAutoReplyRule $rule,
        AvitoAutoReplySetting $settings,
    ): bool {
        $minutes = $rule->cooldown_minutes ?? $settings->cooldown_minutes;

        return AvitoAutoReplyDecision::query()
            ->where('avito_chat_id', $chat->id)
            ->where(fn (Builder $query) => $query->where('avito_auto_reply_rule_id', $rule->id)->orWhereJsonContains('matched_rule_keys', $rule->key))
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
            ->where(fn (Builder $query) => $query->where('avito_auto_reply_rule_id', $rule->id)->orWhereJsonContains('matched_rule_keys', $rule->key))
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
