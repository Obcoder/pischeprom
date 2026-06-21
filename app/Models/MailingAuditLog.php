<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailingAuditLog extends Model
{
    protected $table = 'mailing_audit_log';

    public $timestamps = false;

    protected $fillable = ['user_id', 'action', 'entity_type', 'entity_id', 'before_json', 'after_json', 'ip', 'user_agent', 'created_at'];

    protected $casts = ['before_json' => 'array', 'after_json' => 'array', 'created_at' => 'datetime'];
}
