<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sending extends Model
{
    use HasFactory;

    protected $fillable = [
        'email_id',
        'subject',
    ];
}
