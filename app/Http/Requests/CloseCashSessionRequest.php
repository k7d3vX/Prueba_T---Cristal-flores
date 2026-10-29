<?php

namespace App\Http\Requests;

use App\Support\Denominations;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Conteo de cierre: por planilla de denominaciones (`counts`) o solo el total (`counted_cash`).
 * Con planilla, el total lo calcula SIEMPRE el servidor (RN-25, AR-26).
 */
class CloseCashSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $counts = $this->input('counts');

        // Una planilla sin ninguna cantidad cuenta como "no enviada" (queda el total directo, si lo hay).
        if (is_array($counts) && collect($counts)->every(fn ($v) => $v === null || $v === '')) {
            $this->request->remove('counts');
        }
    }

    public function rules(): array
    {
        return [
            'counted_cash' => ['required_without:counts', 'nullable', 'numeric', 'min:0', 'regex:/^\\d{1,7}(\\.\\d{1,2})?$/'],
            'counts' => ['required_without:counted_cash', 'array', function (string $attribute, mixed $value, \Closure $fail) {
                foreach (array_keys((array) $value) as $key) {
                    if (! Denominations::isValid((string) $key)) {
                        $fail('Denominación desconocida.');
                    }
                }
            }],
            'counts.*' => ['nullable', 'integer', 'min:0', 'max:'.Denominations::MAX_QUANTITY],
        ];
    }

    public function messages(): array
    {
        return [
            'counted_cash.required_without' => 'Cuenta al menos un billete o moneda, o escribe el total.',
            'counts.required_without' => 'Cuenta al menos un billete o moneda, o escribe el total.',
        ];
    }

    private function hasSheet(): bool
    {
        return is_array($this->validated('counts'));
    }

    /** "1950.00": con planilla, Σ cantidad × valor calculado aquí; si no, el total escrito. */
    public function countedCash(): string
    {
        return $this->hasSheet()
            ? Money::fromCents(Denominations::totalCents($this->validated('counts')))
            : (string) $this->validated('counted_cash');
    }

    /** @return array<string, int>|null */
    public function breakdown(): ?array
    {
        return $this->hasSheet() ? Denominations::normalize($this->validated('counts')) : null;
    }
}
