<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailingSuppression extends Model
{
    protected $table = 'mailing_suppression_list';

    protected $fillable = ['email', 'normalized_email', 'cause', 'source', 'note', 'created_by'];

    protected static function booted(): void
    {
        static::saving(function (MailingSuppression $suppression): void {
            $suppression->normalized_email = MailingContact::normalizeEmail($suppression->normalized_email ?: $suppression->email);
        });
    }
}
