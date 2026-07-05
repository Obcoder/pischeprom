<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaxiShift extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'orders_count',
        'revenue_amount',
    ];

    protected $casts = [
        'date' => 'date',
        'orders_count' => 'integer',
        'revenue_amount' => 'float',
    ];
}
