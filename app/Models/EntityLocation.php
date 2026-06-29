<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntityLocation extends Model
{
    use HasFactory;

    protected $primaryKey = 'entity_id';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'entity_id',
        'address_text',
        'lat',
        'lon',
        'source',
        'provider_object_id',
        'precision_level',
        'is_confirmed',
        'geocoded_at',
        'confirmed_at',
    ];

    protected $casts = [
        'entity_id' => 'integer',
        'lat' => 'float',
        'lon' => 'float',
        'is_confirmed' => 'boolean',
        'geocoded_at' => 'datetime',
        'confirmed_at' => 'datetime',
    ];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }
}
