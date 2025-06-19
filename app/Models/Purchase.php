<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Purchase extends Model
{
    use HasFactory;

    protected $with = [
        'entity',
    ];
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }
}
