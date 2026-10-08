<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoodUrlAlias extends Model
{
    public $timestamps = false;

    protected $fillable = ['good_id', 'slug'];
}
