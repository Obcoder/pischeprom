<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailingTemplateVersion extends Model
{
    public $timestamps = false;

    protected $fillable = ['template_id', 'version_number', 'subject', 'preheader', 'editor_schema', 'html_markup', 'plaintext', 'change_note', 'created_by', 'created_at'];

    protected $casts = ['editor_schema' => 'array', 'created_at' => 'datetime'];

    public function template(): BelongsTo
    {
        return $this->belongsTo(MailingTemplate::class, 'template_id');
    }
}
