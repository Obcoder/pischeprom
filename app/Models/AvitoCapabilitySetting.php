<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AvitoCapabilitySetting extends Model
{
    protected $fillable = [
        'capability_id',
        'enabled',
        'notes',
        'last_status',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }
}
