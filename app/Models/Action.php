<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Action extends Model
{
    use HasFactory;

    public function product()
    {
        return $this->hasOneThrough(Product::class, product_unit::class);
    }
}
