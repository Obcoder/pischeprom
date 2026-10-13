<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class SupplierProfileEvent extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['changes' => 'array', 'created_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Supplier profile events are append-only.');
        });
        static::deleting(static function (): never {
            throw new LogicException('Supplier profile events are append-only.');
        });
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(SupplierProfile::class, 'supplier_profile_id');
    }
}
