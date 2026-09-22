<?php

namespace App\Http\Requests\Mail;

use App\Http\Requests\Mail\Concerns\ValidatesMailOffer;
use App\Services\Mail\AuthorizedMailDispatchService;
use Illuminate\Foundation\Http\FormRequest;

class PreviewMailOfferRequest extends FormRequest
{
    use ValidatesMailOffer;

    public function authorize(): bool
    {
        if (! $this->user()) {
            return false;
        }

        app(AuthorizedMailDispatchService::class)->authorize($this->user());

        return true;
    }

    public function rules(): array
    {
        return ['body' => ['nullable', 'string', 'max:100000'], ...$this->mailOfferRules()];
    }
}
