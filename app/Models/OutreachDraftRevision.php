<?php

namespace App\Models;

use App\Domain\AiSales\Outreach\Enums\OutreachDlpStatus;
use App\Domain\AiSales\Outreach\Enums\OutreachGenerationOrigin;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class OutreachDraftRevision extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'public_id', 'outreach_draft_id', 'parent_revision_id', 'revision_number', 'origin',
        'structured_content', 'subject', 'plaintext', 'html', 'renderer_version', 'renderer_hash',
        'dlp_status', 'dlp_findings', 'dlp_hash', 'claim_set_hash', 'input_hash', 'edited_by',
    ];

    protected function casts(): array
    {
        return [
            'origin' => OutreachGenerationOrigin::class,
            'structured_content' => 'array', 'dlp_findings' => 'array',
            'dlp_status' => OutreachDlpStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Outreach revisions are append-only.');
        });
        static::deleting(static function (): never {
            throw new LogicException('Outreach revisions are append-only.');
        });
    }

    public function draft(): BelongsTo
    {
        return $this->belongsTo(OutreachDraft::class, 'outreach_draft_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_revision_id');
    }

    public function claims(): HasMany
    {
        return $this->hasMany(OutreachDraftClaim::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(OutreachDraftReview::class);
    }
}
