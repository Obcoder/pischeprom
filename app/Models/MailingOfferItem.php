<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailingOfferItem extends Model
{
    protected $fillable = ['campaign_id', 'campaign_recipient_id', 'template_id', 'product_id', 'category_id', 'item_type', 'title', 'sku', 'thumbnail_url', 'canonical_url', 'original_price', 'offer_price', 'currency', 'description', 'sort_order', 'snapshot'];

    protected $casts = ['snapshot' => 'array', 'original_price' => 'decimal:4', 'offer_price' => 'decimal:4'];
}
