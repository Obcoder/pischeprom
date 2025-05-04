<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Plant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'genus_id',
    ];
    protected $with = [
        'genus',
    ];

    public function genus(): BelongsTo
    {
        return $this->belongsTo(Genus::class);
    }
}
