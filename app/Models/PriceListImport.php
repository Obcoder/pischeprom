<?php

namespace App\Models;

use App\Domain\AiPriceLists\Enums\DocumentClass;
use App\Domain\AiPriceLists\Enums\PriceListStatus;
use App\Domain\AiPriceLists\Enums\SourceChannel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PriceListImport extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (self $import): void {
            $import->uuid ??= (string) Str::uuid();
            $import->status ??= PriceListStatus::Received;
            $import->document_class ??= DocumentClass::Uncertain;
        });
    }

    protected function casts(): array
    {
        return [
            'source_channel' => SourceChannel::class,
            'status' => PriceListStatus::class,
            'document_class' => DocumentClass::class,
            'requires_ocr' => 'boolean',
            'document_defaults' => 'array',
            'document_metadata' => 'array',
            'error_retryable' => 'boolean',
            'source_received_at' => 'datetime',
            'stage_started_at' => 'datetime',
            'stage_heartbeat_at' => 'datetime',
            'processing_completed_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'applied_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'size_bytes' => 'integer',
            'progress' => 'integer',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'entity_id');
    }

    public function mailMessage(): BelongsTo
    {
        return $this->belongsTo(MailMessage::class);
    }

    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicate_of_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function appliedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PriceListImportItem::class)->orderBy('position');
    }

    public function events(): HasMany
    {
        return $this->hasMany(PriceListEvent::class)->latest('id');
    }

    public function usageRecords(): HasMany
    {
        return $this->hasMany(AiUsageRecord::class);
    }

    public function supplierPrices(): HasMany
    {
        return $this->hasMany(SupplierGoodPrice::class);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        $search = trim((string) $search);

        if ($search === '') {
            return $query;
        }

        return $query->where(function (Builder $nested) use ($search): void {
            $nested->where('original_name', 'like', "%{$search}%")
                ->orWhere('sender_address', 'like', "%{$search}%")
                ->orWhere('source_subject', 'like', "%{$search}%")
                ->orWhere('source_external_message_id', 'like', "%{$search}%")
                ->orWhereHas('supplier', fn (Builder $supplier) => $supplier->where('name', 'like', "%{$search}%"));
        });
    }
}
