<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AvitoAutoReplySetting extends Model
{
    public const MODES = ['off', 'shadow', 'pilot', 'active'];

    protected $fillable = [
        'mode',
        'response_mode',
        'debounce_seconds',
        'bundle_window_seconds',
        'cooldown_minutes',
        'daily_limit',
        'minimum_confidence',
        'minimum_margin',
    ];

    protected function casts(): array
    {
        return [
            'emergency_stopped_at' => 'datetime',
            'debounce_seconds' => 'integer',
            'bundle_window_seconds' => 'integer',
            'cooldown_minutes' => 'integer',
            'daily_limit' => 'integer',
            'minimum_confidence' => 'float',
            'minimum_margin' => 'float',
        ];
    }

    public static function current(): self
    {
        return self::query()->firstOrCreate(['id' => 1], [
            'mode' => 'shadow',
            'response_mode' => 'assistant',
            'debounce_seconds' => 15,
            'bundle_window_seconds' => 120,
            'cooldown_minutes' => 60,
            'daily_limit' => 20,
            'minimum_confidence' => 0.90,
            'minimum_margin' => 0.10,
        ]);
    }

    public static function withLockedCurrent(callable $callback): mixed
    {
        self::current();

        return DB::transaction(fn () => $callback(self::query()->lockForUpdate()->findOrFail(1)));
    }
}
