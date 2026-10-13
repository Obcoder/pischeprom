<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class SupplierProfile extends Model
{
    public const STATUSES = ['active' => 'Активный', 'potential' => 'Потенциальный', 'paused' => 'Приостановлен', 'archived' => 'Архив'];

    public const PRIORITIES = ['low' => 'Низкий', 'normal' => 'Обычный', 'high' => 'Высокий', 'critical' => 'Критический'];

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'active', 'priority' => 'normal', 'is_watched' => false, 'review_interval_days' => 30, 'revision' => 1];

    protected $casts = [
        'is_watched' => 'boolean', 'review_interval_days' => 'integer', 'revision' => 'integer',
        'next_review_on' => 'date:Y-m-d', 'last_reviewed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleting(static function (): never {
            throw new LogicException('Supplier profiles are archived; their review history is retained.');
        });
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class)->without(['fields', 'labels', 'telephones', 'uris']);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(SupplierProfileEvent::class)->orderByDesc('id');
    }
}
