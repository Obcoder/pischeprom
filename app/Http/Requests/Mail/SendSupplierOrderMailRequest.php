<?php

namespace App\Http\Requests\Mail;

use App\Http\Requests\Mail\Concerns\ValidatesAuthorizedMail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Validator;

class SendSupplierOrderMailRequest extends FormRequest
{
    use ValidatesAuthorizedMail {
        withValidator as private validateMailConstraints;
    }

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'revision' => ['required', 'integer', 'min:1'],
            ...array_intersect_key($this->mailRules(), array_flip([
                'attachments', 'attachments.*', 'storage_files', 'storage_files.*',
            ])),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $this->validateMailConstraints($validator);
        $validator->after(function (Validator $validator): void {
            foreach (array_diff(array_keys($this->all()), ['revision', 'attachments', 'storage_files']) as $field) {
                $validator->errors()->add($field, 'Данные письма берутся из сохранённого заказа.');
            }
        });
    }

    /** Reuse the authorized-mail rules for the saved recipients and attachments. */
    public static function validateStoredMail(array $payload): array
    {
        $files = ['attachments' => $payload['attachments'] ?? []];
        unset($payload['attachments']);
        $request = static::create('/', 'POST', $payload, [], $files);
        $validator = ValidatorFacade::make($request->all(), $request->mailRules());
        $request->validateMailConstraints($validator);

        return $validator->validate();
    }
}
