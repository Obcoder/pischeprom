<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** A supplier request does not reserve goods or create warehouse receipts. */
class SupplierOrder extends Model
{
    public const STATUSES = ['draft', 'sending', 'sent', 'confirmed', 'closed', 'cancelled', 'send_unknown'];

    public const STATUS_LABELS = [
        'draft' => 'Черновик', 'sending' => 'Отправляется', 'sent' => 'Отправлен',
        'confirmed' => 'Подтверждён', 'closed' => 'Закрыт', 'cancelled' => 'Отменён',
        'send_unknown' => 'Результат отправки неизвестен',
    ];

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'draft', 'revision' => 1, 'include_prices' => true, 'auto_subject' => false];

    protected $casts = [
        'revision' => 'integer', 'include_prices' => 'boolean', 'to' => 'array', 'cc' => 'array',
        'supplier_sequence' => 'integer', 'auto_subject' => 'boolean', 'subject_date' => 'date:Y-m-d',
        'delivery_date' => 'date:Y-m-d', 'sent_at' => 'datetime', 'sending_started_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $order): void {
            $order->number ??= 'СО-'.now()->format('Ymd-His').'-'.Str::upper(Str::random(6));
        });
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class)->without(['fields', 'labels', 'telephones', 'uris']);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SupplierOrderItem::class)->orderBy('position')->orderBy('id');
    }

    public function mailMessage(): BelongsTo
    {
        return $this->belongsTo(MailMessage::class);
    }
}
