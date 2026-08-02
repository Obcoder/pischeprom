<?php

namespace App\Http\Requests\Logistics;

use App\Models\LogisticsCityDistance;
use Illuminate\Foundation\Http\FormRequest;

class MatrixPreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! config('logistics.authorization_enabled')) {
            return true;
        }

        $distance = $this->route('distance');

        return $distance instanceof LogisticsCityDistance
            && (bool) $this->user()?->can('view', $distance);
    }

    public function rules(): array
    {
        return [];
    }
}
