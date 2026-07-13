<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'flag',
        'сodeTelefon',
        'сodeISO',
        'population',
    ];

    protected $casts = [
        'population' => 'integer',
    ];

    public function goods(): HasMany
    {
        return $this->hasMany(Good::class);
    }

    public function regions(): HasMany
    {
        return $this->hasMany(Region::class);
    }

    public function entities(): HasMany
    {
        return $this->hasMany(Entity::class);
    }
}
