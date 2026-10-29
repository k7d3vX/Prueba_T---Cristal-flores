<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** El cajero solo indica QUÉ caja abre: fondo y modo no vienen de él (AR-17). */
class OpenShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cash_register_id' => ['required', Rule::exists('cash_registers', 'id')],
        ];
    }
}
