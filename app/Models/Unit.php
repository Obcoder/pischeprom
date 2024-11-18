<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function uris()
    {
        return $this->belongsToMany(Uri::class)
            ->using(unit_uri::class);
    }

    public function labels()
    {
        return $this->belongsToMany(Label::class)
            ->using(label_unit::class);
    }
}
