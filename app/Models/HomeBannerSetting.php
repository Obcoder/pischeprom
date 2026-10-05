<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomeBannerSetting extends Model
{
    public const DEFAULTS = [
        'enabled' => true,
        'desktop_height' => 96,
        'gap' => 8,
        'mobile_enabled' => true,
        'mobile_layout' => 'scroll',
        'mobile_height' => 96,
        'mobile_columns' => 2,
        'mobile_hide_empty' => true,
        'mobile_order' => [1, 2, 3, 4, 5, 6],
    ];

    public $incrementing = false;

    protected $fillable = [
        'id', 'enabled', 'desktop_height', 'gap', 'mobile_enabled', 'mobile_layout',
        'mobile_height', 'mobile_columns', 'mobile_hide_empty', 'mobile_order',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'desktop_height' => 'integer',
        'gap' => 'integer',
        'mobile_enabled' => 'boolean',
        'mobile_height' => 'integer',
        'mobile_columns' => 'integer',
        'mobile_hide_empty' => 'boolean',
        'mobile_order' => 'array',
    ];

    public static function singleton(): self
    {
        return static::query()->firstOrCreate(['id' => 1], self::DEFAULTS);
    }

    public function settingsPayload(): array
    {
        return $this->only(array_keys(self::DEFAULTS));
    }
}
