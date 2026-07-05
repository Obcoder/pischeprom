<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Measure extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function checkCommodities()
    {
        return $this->hasMany(CheckCommodity::class);
    }

    public function checkServices()
    {
        return $this->hasMany(CheckService::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }
}
