<?php

namespace App\Http\Requests;

use App\Models\Customer;
use App\Traits\NormalizesCurrencyInputs;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SplitAndSettleOrderRequest extends FormRequest
{
    use NormalizesCurrencyInputs;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if (! $this->has('payments') && $this->has('method') && $this->has('amount')) {
            $merge['payments'] = [
                [
                    'method' => $this->input('method'),
                    'amount' => $this->input('amount'),
                    'cash_received' => $this->input('cash_received'),
                    'reference' => $this->input('reference'),
                ],
            ];
        }

        $payments = $merge['payments'] ?? $this->input('payments');
        if (is_array($payments)) {
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
            $merge['payments'] = $payments;
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

        if (! empty($merge)) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        $isCredit = $this->boolean('is_credit');
        $isItemsSplit = $this->input('split_type') === 'items';
        $hasItemsPayload = is_array($this->input('items')) && count($this->input('items')) > 0;

        $rules = [
            'split_type' => ['required', 'in:equal,items'],
            'shares' => ['required_if:split_type,equal', 'nullable', 'integer', 'min:2', 'max:20'],
            'share_number' => ['required_if:split_type,equal', 'nullable', 'integer', 'min:1'],
            'item_ids' => [
                Rule::requiredIf($isItemsSplit && ! $hasItemsPayload),
                'nullable',
                'array',
                'min:1',
            ],
            'item_ids.*' => ['integer', 'exists:restaurant_order_items,id'],
            'items' => [
                Rule::requiredIf($isItemsSplit && empty($this->input('item_ids'))),
                'nullable',
                'array',
                'min:1',
            ],
            'items.*.item_id' => ['required', 'integer', 'exists:restaurant_order_items,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.additions' => ['nullable', 'array'],
            'items.*.additions.*.addition_id' => ['required_with:items.*.additions', 'integer', 'exists:adiciones,id'],
            'items.*.additions.*.quantity' => ['required_with:items.*.additions', 'numeric', 'min:0'],
            'is_credit' => ['nullable', 'boolean'],
            'credit_due_date' => ['nullable', 'date', 'after_or_equal:today'],
            'customer_id' => $isCredit ? ['required', 'exists:customers,id'] : ['nullable', 'exists:customers,id'],
            'customer_name' => ['nullable', 'string', 'max:150'],
            'customer_document' => ['nullable', 'string', 'max:30'],
            'discount_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'service_fee' => ['nullable', 'numeric', 'min:0'],
            'tax_inc' => ['nullable', 'numeric', 'min:0'],
            'tip' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];

        if ($isCredit) {
            $rules['payments'] = ['nullable', 'array'];
        } else {
            $rules['payments'] = ['required', 'array', 'min:1', 'max:5'];
            $rules['payments.*.method'] = ['required', 'in:cash,card,transfer,other'];
            $rules['payments.*.amount'] = ['required', 'numeric', 'min:0.01'];
            $rules['payments.*.reference'] = ['nullable', 'string', 'max:60'];
            $rules['payments.*.cash_received'] = ['nullable', 'numeric', 'min:0'];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if ($this->boolean('is_credit')) {
                if ($this->input('split_type') === 'equal') {
                    $v->errors()->add('is_credit', 'El despacho a crédito/fiado solo está disponible en división por platos o cobro total.');
                    return;
                }

                $customerId = $this->input('customer_id');
                if ($customerId) {
                    $cust = Customer::find($customerId);
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
            'split_type.required' => 'Debe seleccionar la modalidad de división por platos.',
            'item_ids.required' => 'Debe seleccionar al menos un plato para liquidar el pago parcial.',
            'item_ids.min' => 'Debe seleccionar al menos un plato para liquidar el pago parcial.',
            'items.required' => 'Debe seleccionar al menos un plato para liquidar el pago parcial.',
            'items.min' => 'Debe seleccionar al menos un plato para liquidar el pago parcial.',
            'customer_id.required' => 'Debe seleccionar un cliente registrado para despachar una venta a crédito o fiado.',
            'payments.required' => 'Debes incluir al menos un método de pago.',
        ];
    }
}
