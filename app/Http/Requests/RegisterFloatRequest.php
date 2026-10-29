<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterFloatRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'default_float' => ['required', 'numeric', 'min:0', 'regex:/^\\d{1,7}(\\.\\d{1,2})?$/'],
        ];
    }
}
