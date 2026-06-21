<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MailingContactSet extends Model
{
    protected $fillable = ['name', 'description', 'type', 'filter_definition', 'created_by', 'updated_by', 'contacts_count', 'active'];

    protected $casts = ['filter_definition' => 'array', 'active' => 'boolean', 'contacts_count' => 'integer'];

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(MailingContact::class, 'mailing_contact_set_members', 'set_id', 'contact_id')
            ->withPivot(['added_by', 'added_at', 'status']);
    }

    public function members(): HasMany
    {
        return $this->hasMany(MailingContactSetMember::class, 'set_id');
    }
}
