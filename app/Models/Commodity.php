<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Commodity extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function checks()
    {
        return $this->belongsToMany(Check::class, 'check_commodity')
            ->using(check_commodity::class)
            ->withPivot('quantity', 'measure_id', 'price', 'total_price');
    }
}
