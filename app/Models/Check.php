<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Check extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'entity_id',
        'amount',
    ];

    public function entity(){
        return $this->belongsTo(Entity::class);
    }
}