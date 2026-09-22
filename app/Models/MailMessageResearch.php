<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailMessageResearch extends Model
{
    protected $table = 'mail_message_researches';

    protected $fillable = ['mail_message_id', 'user_id', 'kind', 'input_hash', 'query', 'result'];

    protected $casts = ['result' => 'array'];

    protected $hidden = ['input_hash', 'user_id'];
}
