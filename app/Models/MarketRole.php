<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class MarketRole extends Model
{
    protected $fillable = [
        'display_name',
        'name_translations',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'name_translations' => 'array',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $role): void {
            if ($role->isDirty('code')) {
                throw new LogicException('Market role system code is immutable.');
            }
        });

        static::deleting(function (self $role): void {
            if ($role->is_system || $role->units()->exists() || $role->contexts()->exists()) {
                throw new LogicException('System or used market roles cannot be deleted.');
            }
        });
    }

    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class)
            ->withPivot(['source', 'assigned_by', 'removed_by', 'archived_at'])
            ->withTimestamps();
    }

    public function contexts(): HasMany
    {
        return $this->hasMany(UnitBusinessContext::class, 'role_code', 'code');
    }
}
