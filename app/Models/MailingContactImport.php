<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailingContactImport extends Model
{
    protected $fillable = ['filename', 'source_type', 'status', 'total_rows', 'imported_count', 'skipped_count', 'duplicate_count', 'invalid_count', 'errors_json', 'created_by'];

    protected $casts = ['errors_json' => 'array'];
}
