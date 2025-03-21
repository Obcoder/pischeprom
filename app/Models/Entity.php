<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
        return $this->belongsTo(EntityClassification::class , 'entity_classification_id')
            ->withDefault();
    }
    public function telephones()
    {
        return $this->belongsToMany(Telephone::class);
    }
    public function chats(): BelongsToMany
    {
        return $this->belongsToMany(Chat::class);
    }
    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class);
    }
}
