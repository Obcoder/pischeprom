<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Entity extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'entity_classification_id',
    ];

    public function classification()
    {
        return $this->belongsTo(EntityClassification::class , 'entity_classification_id')
            ->withDefault();
    }
}
