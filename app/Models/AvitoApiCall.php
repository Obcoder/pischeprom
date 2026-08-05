<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvitoApiCall extends Model
{
    protected $fillable = [
        'avito_connection_id',
        'request_id',
        'capability_id',
        'method',
        'endpoint',
        'status',
        'http_status',
        'duration_ms',
        'request_meta',
        'response_meta',
        'error_message',
    ];

    protected $hidden = [
        'request_meta',
        'response_meta',
    ];

    protected function casts(): array
    {
        return [
            'request_meta' => 'encrypted:array',
            'response_meta' => 'encrypted:array',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(AvitoConnection::class, 'avito_connection_id');
    }
}
