<?php

namespace App\Http\Requests;

use App\Traits\NormalizesCurrencyInputs;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ProcessSaleRequest extends FormRequest
{
    use NormalizesCurrencyInputs;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->has('delivery_fee')) {
            $merge['delivery_fee'] = $this->normalizeCurrency($this->input('delivery_fee'));
        }
        if ($this->has('service_fee')) {
            $merge['service_fee'] = $this->normalizeCurrency($this->input('service_fee'));
        }
        if ($this->has('tip')) {
            $merge['tip'] = $this->normalizeCurrency($this->input('tip'));
        }
        if ($this->has('discount')) {
            $merge['discount'] = $this->normalizeCurrency($this->input('discount'));
        }
        if ($this->has('tax_inc')) {
            $merge['tax_inc'] = $this->normalizeCurrency($this->input('tax_inc'));
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
            }
            $merge['payments'] = $payments;
        }
        if (! empty($merge)) {
            $this->merge($merge);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isCredit = $this->boolean('is_credit');

        $rules = [
            'is_credit' => 'nullable|boolean',
            'credit_due_date' => 'nullable|date|after_or_equal:today',
            'customer_id' => $isCredit ? 'required|exists:customers,id' : 'nullable|exists:customers,id',
            'customer_name' => 'nullable|string|max:150',
            'customer_document' => 'nullable|string|max:30',
            'waiter_name' => 'nullable|string|max:100',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'discount' => 'nullable|numeric|min:0',
            'service_fee' => 'nullable|numeric|min:0',
            'tax_inc' => 'nullable|numeric|min:0',
            'tip' => 'nullable|numeric|min:0',
            'delivery_fee' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500',

            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.001|max:999',
            'items.*.additions' => 'nullable|array',
            'items.*.additions.*.addition_id' => 'required_with:items.*.additions|exists:adiciones,id',
            'items.*.additions.*.quantity' => 'required_with:items.*.additions|numeric|min:1|max:99',
        ];

        if ($isCredit) {
            $rules['payments'] = 'nullable|array';
        } else {
            $rules['payments'] = 'required|array|min:1|max:5';
            $rules['payments.*.method'] = 'required|in:cash,card,transfer,other';
            $rules['payments.*.amount'] = 'required|numeric|min:0.01';
            $rules['payments.*.reference'] = 'nullable|string|max:60';
            $rules['payments.*.cash_received'] = 'nullable|numeric|min:0';
        }

        return $rules;
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($this->boolean('is_credit')) {
                $customerId = $this->input('customer_id');
                if ($customerId) {
                    $cust = \App\Models\Customer::find($customerId);
                    if (! $cust || $cust->document === '222222222222' || str_contains(strtoupper($cust->name), 'CONSUMIDOR FINAL')) {
                        $v->errors()->add('customer_id', 'Está terminantemente prohibido otorgar crédito al Consumidor Final genérico (documento 222222222222). Debe seleccionar un cliente registrado.');
                    } elseif (empty(trim((string) $cust->phone))) {
                        $v->errors()->add('customer_id', 'El cliente seleccionado debe contar con un número de teléfono de contacto registrado para otorgar crédito.');
                    }
                }

                return;
            }

            $payments = $this->input('payments', []);

            if (! is_array($payments)) {
                return;
            }

            $cashLines = 0;
            foreach ($payments as $index => $payment) {
                if (($payment['method'] ?? null) === 'cash') {
                    $cashLines++;
                    if (isset($payment['cash_received']) && $payment['cash_received'] !== null && $payment['cash_received'] !== '') {
                        $cashReceived = (float) $payment['cash_received'];
                        $amount = (float) ($payment['amount'] ?? 0);
                        if ($cashReceived < $amount) {
                            $v->errors()->add("payments.{$index}.cash_received", 'El efectivo recibido debe ser mayor o igual al monto a pagar.');
                        }
                    }
                }
            }

            if ($cashLines > 1) {
                $v->errors()->add('payments', 'Solo se permite una sola línea de pago en efectivo.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Debes incluir al menos un producto o plato.',
            'items.min' => 'Debes incluir al menos un producto o plato.',
            'payments.required' => 'Debes incluir al menos un método de pago.',
            'payments.min' => 'Debes incluir al menos un método de pago.',
            'payments.max' => 'No puedes registrar más de 5 métodos de pago simultáneos.',
        ];
    }
}
