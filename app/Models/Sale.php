<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'entity_id',
        'total',
    ];

    protected $with = ['entity'];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }
    public function goods(): BelongsToMany
    {
        return $this->belongsToMany(Good::class)
            ->using(good_sale::class);
    }
}
