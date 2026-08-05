<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvitoMessageTemplateUsage extends Model
{
    protected $fillable = [
        'avito_message_template_id',
        'avito_chat_id',
        'avito_message_id',
        'mode',
        'rendered_body',
        'context',
        'sent_at',
    ];

    protected $hidden = ['rendered_body', 'context'];

    protected function casts(): array
    {
        return [
            'rendered_body' => 'encrypted',
            'context' => 'encrypted:array',
            'sent_at' => 'datetime',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(AvitoMessageTemplate::class, 'avito_message_template_id');
    }

    public function chat(): BelongsTo
    {
        return $this->belongsTo(AvitoChat::class, 'avito_chat_id');
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(AvitoMessage::class, 'avito_message_id');
    }
}
