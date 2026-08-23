<?php

namespace App\Http\Requests\Mail;

use App\Http\Requests\Mail\Concerns\ValidatesAuthorizedMail;
use App\Models\Unit;
use App\Services\Mail\AuthorizedMailDispatchService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Throwable;

class SendUnitMailRequest extends FormRequest
{
    use ValidatesAuthorizedMail;

    public function authorize(): bool
    {
        $unit = $this->route('unit');

        try {
            return $unit instanceof Unit
                && ($this->user()?->hasRole('admin', 'crm') === true
                    || $this->user()?->hasPermissionTo(AuthorizedMailDispatchService::PERMISSION, 'crm') === true)
                && Gate::forUser($this->user())->allows('sendMail', $unit);
        } catch (Throwable) {
            return false;
        }
    }

    public function rules(): array
    {
        return $this->mailRules();
    }
}
