<?php

namespace App\Http\Requests;

use App\Traits\NormalizesCurrencyInputs;
use Illuminate\Foundation\Http\FormRequest;

class SplitAndSettleOrderRequest extends FormRequest
{
    use NormalizesCurrencyInputs;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('payments') && $this->has('method') && $this->has('amount')) {
            $this->merge([
                'payments' => [
                    [
                        'method' => $this->input('method'),
                        'amount' => $this->input('amount'),
                        'cash_received' => $this->input('cash_received'),
                        'reference' => $this->input('reference'),
                    ]
                ]
            ]);
        }

        if ($this->has('payments') && is_array($this->input('payments'))) {
            $payments = $this->input('payments');
            foreach ($payments as $k => $p) {
                if (isset($p['amount'])) {
                    $payments[$k]['amount'] = $this->normalizeCurrency($p['amount']);
                }
                if (isset($p['cash_received'])) {
                    $payments[$k]['cash_received'] = $this->normalizeCurrency($p['cash_received']);
                }
                if (isset($p['method']) && ($p['method'] === 'nequi' || $p['method'] === 'daviplata')) {
                    $originalMethod = ucfirst($p['method']);
                    $payments[$k]['method'] = 'transfer';
                    if (empty($payments[$k]['reference'])) {
                        $payments[$k]['reference'] = $originalMethod;
                    }
                }
            }
            $this->merge(['payments' => $payments]);
        }
        if ($this->has('tip')) {
            $this->merge(['tip' => $this->normalizeCurrency($this->input('tip'))]);
        }
        if ($this->has('discount')) {
            $this->merge(['discount' => $this->normalizeCurrency($this->input('discount'))]);
        }
        if ($this->has('tax_inc')) {
            $this->merge(['tax_inc' => $this->normalizeCurrency($this->input('tax_inc'))]);
        }
    }

    public function rules(): array
    {
        return [
            'split_type' => ['required', 'in:equal,items'],
            'shares' => ['required_if:split_type,equal', 'nullable', 'integer', 'min:2', 'max:20'],
            'share_number' => ['required_if:split_type,equal', 'nullable', 'integer', 'min:1'],
            'item_ids' => ['required_if:split_type,items', 'nullable', 'array', 'min:1'],
            'item_ids.*' => ['integer', 'exists:restaurant_order_items,id'],
            'payments' => ['required', 'array', 'min:1', 'max:5'],
            'payments.*.method' => ['required', 'in:cash,card,transfer,other'],
            'payments.*.amount' => ['required', 'numeric', 'min:0.01'],
            'payments.*.reference' => ['nullable', 'string', 'max:60'],
            'payments.*.cash_received' => ['nullable', 'numeric', 'min:0'],
            'customer_id' => ['nullable', 'exists:customers,id'],
            'customer_name' => ['nullable', 'string', 'max:150'],
            'customer_document' => ['nullable', 'string', 'max:30'],
            'discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'service_fee' => ['nullable', 'numeric', 'min:0'],
            'tax_inc' => ['nullable', 'numeric', 'min:0'],
            'tip' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'split_type.required' => 'Debe seleccionar la modalidad de división por platos.',
            'item_ids.required_if' => 'Debe seleccionar al menos un plato para liquidar el pago parcial.',
            'item_ids.min' => 'Debe seleccionar al menos un plato para liquidar el pago parcial.',
            'payments.required' => 'Debe ingresar el pago del comensal.',
        ];
    }
}
