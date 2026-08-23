<?php

namespace App\Http\Requests\Unit;

use App\Models\Unit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreUnitEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        $unit = $this->route('unit');

        return $unit instanceof Unit
            && $this->user() !== null
            && Gate::forUser($this->user())->allows('manageContacts', $unit);
    }

    protected function prepareForValidation(): void
    {
        $address = $this->input('address');
        $name = $this->input('name');

        $this->merge([
            'address' => is_string($address) ? Str::lower(trim($address)) : $address,
            'name' => is_string($name) ? trim($name) : $name,
        ]);
    }

    public function rules(): array
    {
        return [
            'email_id' => [
                'bail',
                'nullable',
                'integer',
                'required_without:address',
                Rule::prohibitedIf(fn () => $this->filled('address')),
                Rule::exists('emails', 'id')->whereNull('deleted_at'),
            ],
            'address' => [
                'bail',
                'nullable',
                'required_without:email_id',
                Rule::prohibitedIf(fn () => $this->filled('email_id')),
                'email:rfc',
                'max:255',
            ],
            'name' => [
                'nullable',
                'string',
                'max:255',
                Rule::prohibitedIf(fn () => ! $this->filled('address')),
            ],
        ];
    }
}
