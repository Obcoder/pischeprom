<?php

namespace App\Http\Requests\Mail;

use App\Services\Mail\MailWorkspaceAccess;
use Illuminate\Foundation\Http\FormRequest;

class PreviewWordAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        app(MailWorkspaceAccess::class)->authorize($this->user());

        return true;
    }

    public function rules(): array
    {
        return ['attachment_id' => ['nullable', 'integer', 'min:1']];
    }
}
