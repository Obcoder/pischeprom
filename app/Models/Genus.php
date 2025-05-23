<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Genus extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'nameLat',
        'wiki',
    ];
}
