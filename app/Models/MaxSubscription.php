<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaxSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'url',
        'secret',
        'update_types',
        'is_active',
        'provider_response',
        'subscribed_at',
        'unsubscribed_at',
    ];

    protected $casts = [
        'update_types' => 'array',
        'provider_response' => 'array',
        'is_active' => 'boolean',
        'subscribed_at' => 'datetime',
        'unsubscribed_at' => 'datetime',
    ];
}
