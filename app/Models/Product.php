<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'rus',
        'eng',
        'zh',
        'es',
    ];

    public function units()
    {
        return $this->belongsToMany(Unit::class)
            ->using(product_unit::class)
            ->withPivot(['action_id'])
            ->withTimestamps();
    }

    public function action()
    {
        return $this->hasOneThrough(Action::class, product_unit::class, 'action_id', 'product_id', 'id', 'id');
    }
}
