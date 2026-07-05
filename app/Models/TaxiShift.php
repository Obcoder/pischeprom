<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaxiShift extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'revenue_amount',
    ];

    protected $casts = [
        'date' => 'date',
        'revenue_amount' => 'float',
    ];
}
