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

    protected $with = [
        'classification',
    ];

    public function classification()
    {
        return $this->belongsTo(EntityClassification::class)
            ->withDefault();
    }
}
