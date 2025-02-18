<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Entity extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    protected $with = [
        'entityClassification',
    ];

    public function entityClassification()
    {
        return $this->belongsTo(EntityClassification::class)
            ->withDefault();
    }
}
