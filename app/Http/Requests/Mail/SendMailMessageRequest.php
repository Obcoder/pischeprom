<?php

namespace App\Http\Requests\Mail;

use App\Http\Requests\Mail\Concerns\ValidatesAuthorizedMail;
use App\Services\Mail\AuthorizedMailDispatchService;
use Illuminate\Foundation\Http\FormRequest;
use Throwable;

class SendMailMessageRequest extends FormRequest
{
    use ValidatesAuthorizedMail;

    public function authorize(): bool
    {
        try {
            return $this->user()?->hasRole('admin', 'crm') === true
                || $this->user()?->hasPermissionTo(AuthorizedMailDispatchService::PERMISSION, 'crm') === true;
        } catch (Throwable) {
            return false;
        }
    }

    public function rules(): array
    {
        return $this->mailRules(allowRelations: true);
    }
}
