<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sending extends Model
{
    use HasFactory;

    protected $with = [
        'email',
    ];

    protected $fillable = [
        'email_id',
        'subject',
    ];

    public function email()
    {
        return $this->belongsTo(Email::class);
    }
}
