<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Consumption extends Model
{
    use HasFactory;

    public function product()
    {
        return $this->belongsTo(Product::class)
            ->withDefault();
    }
    public function measure(){
        return $this->belongsTo(Measure::class)
            ->withDefault();
    }
}
