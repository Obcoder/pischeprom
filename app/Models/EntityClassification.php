<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EntityClassification extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function primaryEntities(): HasMany
    {
        return $this->hasMany(Entity::class, 'entity_classification_id');
    }

    public function entities(): BelongsToMany
    {
        return $this->belongsToMany(Entity::class, 'entity_entity_classification')
            ->withTimestamps();
    }

    public function goods(): BelongsToMany
    {
        return $this->belongsToMany(Good::class, 'entity_classification_good')
            ->withTimestamps();
    }

    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class, 'entity_classification_unit')
            ->withTimestamps();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'entity_classification_user')
            ->withTimestamps();
    }
}
