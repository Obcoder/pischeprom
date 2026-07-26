<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailMessageMaxDelivery extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENDING = 'sending';

    public const STATUS_RETRYING = 'retrying';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const TARGET_CHAT = 'chat';

    public const TARGET_USER = 'user';

    protected $fillable = [
        'mail_message_id',
        'target_type',
        'target_id',
        'status',
        'attempts',
        'text_parts_total',
        'text_parts_sent',
        'attachments_total',
        'attachments_sent',
        'attachment_tokens',
        'skipped_attachments',
        'provider_message_ids',
        'last_error',
        'last_attempt_at',
        'sent_at',
    ];

    protected $casts = [
        'attempts' => 'integer',
        'text_parts_total' => 'integer',
        'text_parts_sent' => 'integer',
        'attachments_total' => 'integer',
        'attachments_sent' => 'integer',
        'attachment_tokens' => 'array',
        'skipped_attachments' => 'array',
        'provider_message_ids' => 'array',
        'last_attempt_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function mailMessage(): BelongsTo
    {
        return $this->belongsTo(MailMessage::class);
    }

    public function targetQuery(): array
    {
        return $this->target_type === self::TARGET_USER
            ? ['user_id' => $this->target_id]
            : ['chat_id' => $this->target_id];
    }
}
