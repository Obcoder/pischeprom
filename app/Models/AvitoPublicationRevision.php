<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AvitoPublicationRevision extends Model
{
    protected $fillable = [
        'avito_publication_id',
        'version',
        'status',
        'is_current',
        'selected_fields',
        'source_snapshot',
        'payload_snapshot',
        'remote_report',
        'approved_at',
        'submitted_at',
        'processed_at',
    ];

    protected $hidden = [
        'source_snapshot',
        'payload_snapshot',
        'remote_report',
    ];

    protected function casts(): array
    {
        return [
            'avito_publication_id' => 'integer',
            'version' => 'integer',
            'is_current' => 'boolean',
            'selected_fields' => 'array',
            'source_snapshot' => 'encrypted:array',
            'payload_snapshot' => 'encrypted:array',
            'remote_report' => 'encrypted:array',
            'approved_at' => 'datetime',
            'submitted_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function publication(): BelongsTo
    {
        return $this->belongsTo(AvitoPublication::class, 'avito_publication_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(AvitoPublicationMedia::class)->orderBy('sort_order')->orderBy('id');
    }
}
