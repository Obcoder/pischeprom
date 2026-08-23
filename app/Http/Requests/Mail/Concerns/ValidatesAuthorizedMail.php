<?php

namespace App\Http\Requests\Mail\Concerns;

use Illuminate\Validation\Validator;

trait ValidatesAuthorizedMail
{
    protected function mailRules(bool $allowRelations = false): array
    {
        $rules = [
            'idempotency_key' => ['required', 'uuid', 'max:36'],
            'subject' => ['nullable', 'string', 'max:255', 'not_regex:/[\r\n]/'],
            'body' => ['nullable', 'string', 'max:100000'],
            'mailbox' => ['nullable', 'string', 'email:rfc', 'max:254', 'not_regex:/[\r\n]/'],
            'to' => ['required', 'array', 'min:1', 'max:5'],
            'to.*' => ['required', 'string', 'email:rfc', 'max:254', 'distinct:ignore_case', 'not_regex:/[\r\n]/'],
            'cc' => ['sometimes', 'array', 'max:5'],
            'cc.*' => ['required', 'string', 'email:rfc', 'max:254', 'distinct:ignore_case', 'not_regex:/[\r\n]/'],
            'bcc' => ['sometimes', 'array', 'max:5'],
            'bcc.*' => ['required', 'string', 'email:rfc', 'max:254', 'distinct:ignore_case', 'not_regex:/[\r\n]/'],
            'attachments' => ['sometimes', 'array', 'max:5'],
            'attachments.*' => [
                'file', 'max:10240',
                'mimetypes:application/pdf,text/plain,text/csv,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,image/png,image/jpeg',
            ],
            'storage_files' => ['sometimes', 'array', 'max:5'],
            'storage_files.*' => ['required', 'string', 'max:500', 'not_regex:/[\r\n]/'],
            'reply_to_mail_message_id' => ['nullable', 'integer', 'exists:mail_messages,id'],
            'headers' => ['prohibited'],
            'from' => ['prohibited'],
            'from_address' => ['prohibited'],
            'reply_to' => ['prohibited'],
            'reply_to_address' => ['prohibited'],
            'attachments_paths' => ['prohibited'],
            'path' => ['prohibited'],
        ];

        if ($allowRelations) {
            $rules['entity_id'] = ['nullable', 'integer', 'exists:entities,id'];
            $rules['unit_id'] = ['nullable', 'integer', 'exists:units,id'];
        } else {
            $rules['entity_id'] = ['prohibited'];
            $rules['unit_id'] = ['prohibited'];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $recipients = collect([
                ...(is_array($this->input('to')) ? $this->input('to') : []),
                ...(is_array($this->input('cc')) ? $this->input('cc') : []),
                ...(is_array($this->input('bcc')) ? $this->input('bcc') : []),
            ])->map(static fn ($value) => mb_strtolower(trim((string) $value)))->unique();

            if ($recipients->count() > 10) {
                $validator->errors()->add('to', 'No more than 10 unique recipients are allowed.');
            }

            $totalBytes = collect($this->file('attachments', []))->sum(
                static fn ($file) => is_object($file) && method_exists($file, 'getSize') ? (int) $file->getSize() : 0,
            );

            if ($totalBytes > 20 * 1024 * 1024) {
                $validator->errors()->add('attachments', 'Combined attachment size exceeds 20 MiB.');
            }

            foreach (is_array($this->input('storage_files')) ? $this->input('storage_files') : [] as $path) {
                $value = str_replace('\\', '/', trim((string) $path));

                if ($value === '' || str_contains($value, '..') || str_contains($value, '//')
                    || str_starts_with($value, '/') || preg_match('/^[a-z][a-z0-9+.-]*:/i', $value)) {
                    $validator->errors()->add('storage_files', 'Storage attachment path is not allowed.');
                    break;
                }
            }
        });
    }
}
