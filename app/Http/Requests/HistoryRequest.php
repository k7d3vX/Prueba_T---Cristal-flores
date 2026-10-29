<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cashier_id' => ['nullable', Rule::exists('users', 'id')->where('role', 'cashier')],
            'cash_register_id' => ['nullable', Rule::exists('cash_registers', 'id')],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'review' => ['nullable', 'in:pending,reviewed,flagged'],
        ];
    }
}
