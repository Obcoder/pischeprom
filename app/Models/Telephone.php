<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Telephone extends Model
{
    use HasFactory;

    protected $fillable = [
        'number',
    ];

    public function entities(): BelongsToMany
    {
        return $this->belongsToMany(Entity::class);
    }
}
