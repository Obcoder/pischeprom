<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AvitoAutoReplySetting extends Model
{
    public const MODES = ['off', 'shadow', 'pilot', 'active'];

    protected $fillable = [
        'mode',
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
            'debounce_seconds' => 15,
            'bundle_window_seconds' => 120,
            'cooldown_minutes' => 1440,
            'daily_limit' => 20,
            'minimum_confidence' => 0.97,
            'minimum_margin' => 0.10,
        ]);
    }
}
