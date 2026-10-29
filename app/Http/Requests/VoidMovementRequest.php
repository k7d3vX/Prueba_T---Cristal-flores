<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Motivo obligatorio: lo usa la dueña para anular y el cajero para solicitar la anulación. */
class VoidMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:255']];
    }

    public function messages(): array
    {
        return ['reason.required' => 'Escribe el motivo.'];
    }
}
