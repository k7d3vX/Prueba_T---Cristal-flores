<?php

namespace App\Http\Requests;

use App\Enums\ClosingMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OpenCashSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cash_register_id' => ['required', Rule::exists('cash_registers', 'id')],
            // Si abre el cajero, el controlador fuerza su propio id.
            'cashier_id' => ['required', Rule::exists('users', 'id')->where('role', 'cashier')],
            'opening_float' => ['required', 'numeric', 'min:0', 'regex:/^\\d{1,7}(\\.\\d{1,2})?$/'],
            'closing_mode' => ['required', Rule::enum(ClosingMode::class)],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->user()?->isCashier()) {
            $this->merge(['cashier_id' => $this->user()->id]);
        }
    }
}
