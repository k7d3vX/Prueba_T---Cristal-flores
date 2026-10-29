<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReviewSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:reviewed,flagged'],
            'note' => ['nullable', 'string', 'max:500', 'required_if:decision,flagged'],
        ];
    }

    public function messages(): array
    {
        return ['note.required_if' => 'Una observación necesita un comentario.'];
    }
}
