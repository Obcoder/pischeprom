<?php

namespace App\Models;

use App\Domain\AiSales\Outreach\Enums\OutreachReplyClass;
use App\Domain\AiSales\Outreach\Enums\OutreachReplyTriageStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class OutreachReplyLink extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'public_id', 'outreach_dispatch_id', 'incoming_mail_message_id', 'correlation_method',
        'correlation_hash', 'triage_profile', 'triage_status', 'triage_class', 'triage_hash',
        'safe_reason_code', 'reviewed_by', 'reviewed_at', 'created_at',
    ];

    protected function casts(): array
    {
        return [
            'triage_status' => OutreachReplyTriageStatus::class,
            'triage_class' => OutreachReplyClass::class,
            'reviewed_at' => 'datetime', 'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(static function (): never {
            throw new LogicException('Outreach reply links cannot be deleted.');
        });
    }

    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(OutreachDispatch::class, 'outreach_dispatch_id');
    }

    public function incomingMessage(): BelongsTo
    {
        return $this->belongsTo(MailMessage::class, 'incoming_mail_message_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
