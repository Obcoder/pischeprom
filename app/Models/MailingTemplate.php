<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MailingTemplate extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'type', 'subject', 'preheader', 'from_email', 'from_name', 'reply_to', 'editor_schema', 'html_markup', 'plaintext', 'template_engine', 'unisender_template_id', 'track_links', 'track_read', 'active', 'created_by', 'updated_by'];

    protected $casts = ['editor_schema' => 'array', 'track_links' => 'boolean', 'track_read' => 'boolean', 'active' => 'boolean'];

    protected static function booted(): void
    {
        static::saving(function (MailingTemplate $template): void {
            if (! $template->slug) {
                $template->slug = Str::slug($template->name) ?: Str::random(8);
            }
        });
    }

    public function versions(): HasMany
    {
        return $this->hasMany(MailingTemplateVersion::class, 'template_id')->orderByDesc('version_number');
    }
}
