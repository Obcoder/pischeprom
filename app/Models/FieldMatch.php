<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FieldMatch extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_CONTACTED = 'contacted';
    public const STATUS_OFFERED = 'offered';
    public const STATUS_WON = 'won';
    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_CONTACTED,
        self::STATUS_OFFERED,
        self::STATUS_WON,
        self::STATUS_REJECTED,
    ];

    protected $fillable = [
        'field_id',
        'producer_unit_id',
        'consumer_unit_id',
        'status',
        'note',
    ];

    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class);
    }

    public function producer(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'producer_unit_id');
    }

    public function consumer(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'consumer_unit_id');
    }
}
