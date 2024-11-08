<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoodOfTheDay extends Model
{
    use HasFactory;

    public function good()
    {
        return $this->belongsTo(Good::class)
            ->withDefault();
    }
}
