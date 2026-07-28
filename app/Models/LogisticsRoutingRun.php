<?php

namespace App\Models;

use App\Enums\Logistics\RoutingRunStatus;
use App\Enums\Logistics\RoutingRunType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class LogisticsRoutingRun extends Model
{
    use HasFactory;

    public $incrementing = false;

    protected $table = 'logistics_routing_runs';

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'operation_type',
        'status',
        'routing_profile',
        'total_pairs',
        'completed_pairs',
        'failed_pairs',
        'initiated_by',
        'parameters',
        'started_at',
        'finished_at',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'operation_type' => RoutingRunType::class,
            'status' => RoutingRunStatus::class,
            'total_pairs' => 'integer',
            'completed_pairs' => 'integer',
            'failed_pairs' => 'integer',
            'parameters' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    protected static function booted(): void
    {
        static::creating(function (LogisticsRoutingRun $run): void {
            $run->id ??= (string) Str::uuid();
        });
    }
}
