<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnitMarketRole extends Model
{
    protected $table = 'market_role_unit';

    protected $fillable = [
        'unit_id',
        'market_role_id',
        'source',
        'assigned_by',
        'removed_by',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(MarketRole::class, 'market_role_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
