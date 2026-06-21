<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailingContactSetMember extends Model
{
    public $timestamps = false;

    protected $fillable = ['set_id', 'contact_id', 'added_by', 'added_at', 'status'];

    protected $casts = ['added_at' => 'datetime'];
}
