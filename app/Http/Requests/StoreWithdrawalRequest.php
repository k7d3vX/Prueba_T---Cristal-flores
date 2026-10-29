<?php

namespace App\Http\Requests;

use App\Enums\WithdrawalCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWithdrawalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'regex:/^\\d{1,7}(\\.\\d{1,2})?$/'],
            'reason' => ['required', 'string', 'max:255'],
            'category' => ['nullable', Rule::enum(WithdrawalCategory::class)],
        ];
    }
}
