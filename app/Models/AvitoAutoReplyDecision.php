<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvitoAutoReplyDecision extends Model
{
    protected $fillable = [
        'avito_message_id',
        'avito_chat_id',
        'avito_auto_reply_rule_id',
        'sent_avito_message_id',
        'mode',
        'outcome',
        'reason_code',
        'detected_intent',
        'confidence',
        'runner_up_confidence',
        'rule_version',
        'message_excerpt',
        'input_bundle',
        'classifier_payload',
        'model',
        'external_request_id',
        'input_tokens',
        'output_tokens',
        'latency_ms',
        'evaluated_at',
        'sent_at',
    ];

    protected $hidden = ['classifier_payload'];

    protected function casts(): array
    {
        return [
            'confidence' => 'float',
            'runner_up_confidence' => 'float',
            'rule_version' => 'integer',
            'message_excerpt' => 'encrypted',
            'input_bundle' => 'encrypted:array',
            'classifier_payload' => 'encrypted:array',
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'latency_ms' => 'integer',
            'evaluated_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(AvitoMessage::class, 'avito_message_id');
    }

    public function chat(): BelongsTo
    {
        return $this->belongsTo(AvitoChat::class, 'avito_chat_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(AvitoAutoReplyRule::class, 'avito_auto_reply_rule_id');
    }

    public function sentMessage(): BelongsTo
    {
        return $this->belongsTo(AvitoMessage::class, 'sent_avito_message_id');
    }
}
