<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdjustSessionFloatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'opening_float' => ['required', 'numeric', 'min:0', 'regex:/^\\d{1,7}(\\.\\d{1,2})?$/'],
        ];
    }
}
