<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailingLink extends Model
{
    protected $fillable = ['campaign_id', 'campaign_recipient_id', 'product_id', 'original_url', 'canonical_url', 'utm_url', 'label', 'click_count', 'unique_click_count'];
}
