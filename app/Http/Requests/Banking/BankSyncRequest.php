<?php

namespace App\Http\Requests\Banking;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BankSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('bank.sync') ?? false;
    }

    public function rules(): array
    {
        return [
            'connection_id' => ['required', 'integer', 'exists:bank_connections,id'],
            'mode' => ['required', Rule::in(['incremental', 'initial', 'control', 'manual'])],
            'from' => ['required_if:mode,manual', 'prohibited_unless:mode,manual', 'nullable', 'date_format:Y-m-d'],
            'to' => ['required_if:mode,manual', 'prohibited_unless:mode,manual', 'nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('mode') !== 'manual' || ! $this->filled(['from', 'to'])) {
                return;
            }

            try {
                $timezone = (string) config('banking.bank_timezone', 'Europe/Moscow');
                $from = CarbonImmutable::createFromFormat('!Y-m-d', (string) $this->input('from'), $timezone);
                $to = CarbonImmutable::createFromFormat('!Y-m-d', (string) $this->input('to'), $timezone);
            } catch (\Throwable) {
                return;
            }

            if (! $from || ! $to) {
                return;
            }

            if ($from->diffInDays($to) > 366) {
                $validator->errors()->add('to', 'Диапазон синхронизации не может превышать 366 дней.');
            }

            if ($to->startOfDay()->isAfter(CarbonImmutable::now($timezone)->startOfDay())) {
                $validator->errors()->add('to', 'Дата окончания синхронизации не может быть в будущем.');
            }
        });
    }
}
