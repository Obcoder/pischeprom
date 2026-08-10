<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AvitoPublicationMedia extends Model
{
    protected $table = 'avito_publication_media';

    protected $fillable = [
        'avito_publication_revision_id',
        'good_media_id',
        'disk',
        'path',
        'file_name',
        'mime_type',
        'size',
        'sha256',
        'title',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'avito_publication_revision_id' => 'integer',
            'good_media_id' => 'integer',
            'size' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function revision(): BelongsTo
    {
        return $this->belongsTo(AvitoPublicationRevision::class, 'avito_publication_revision_id');
    }

    public function goodMedia(): BelongsTo
    {
        return $this->belongsTo(GoodMedia::class);
    }
}
