<?php

namespace App\Http\Requests;

use App\Models\Sale;
use App\Traits\NormalizesCurrencyInputs;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCreditPaymentRequest extends FormRequest
{
    use NormalizesCurrencyInputs;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->has('amount')) {
            $merge['amount'] = $this->normalizeCurrency($this->input('amount'));
        }
        if ($this->has('cash_received')) {
            $merge['cash_received'] = $this->normalizeCurrency($this->input('cash_received'));
        }
        if (! empty($merge)) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        return [
            'sale_id' => ['nullable', 'exists:sales,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'in:cash,card,transfer,other'],
            'cash_received' => ['nullable', 'numeric', 'min:0'],
            'reference' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $sale = $this->route('sale');
            if (! ($sale instanceof Sale) && $this->filled('sale_id')) {
                $sale = Sale::find($this->input('sale_id'));
            }

            if (! $sale) {
                $v->errors()->add('sale_id', 'La factura asociada es obligatoria.');
                return;
            }

            if ($sale instanceof Sale) {
                if (! $sale->isCredit()) {
                    $v->errors()->add('amount', "La venta {$sale->invoice_number} no es una venta a crédito.");

                    return;
                }

                if ($sale->status !== 'completed') {
                    $v->errors()->add('amount', "No se pueden registrar pagos para una venta en estado {$sale->status}.");

                    return;
                }

                $pending = $sale->credit_pending_amount;
                $amount = (float) $this->input('amount', 0);

                if ($pending <= 0.0) {
                    $v->errors()->add('amount', 'Esta factura ya se encuentra totalmente pagada.');

                    return;
                }

                if ($amount > $pending) {
                    $pendingFmt = number_format($pending, 0, ',', '.');
                    $amountFmt = number_format($amount, 0, ',', '.');
                    $v->errors()->add('amount', "El abono (\${$amountFmt}) no puede ser mayor al saldo pendiente de la factura (\${$pendingFmt}).");
                }
            }

            if ($this->input('method') === 'cash') {
                $amount = (float) $this->input('amount', 0);
                $cashReceived = $this->filled('cash_received') ? (float) $this->input('cash_received') : $amount;

                if ($cashReceived < $amount) {
                    $v->errors()->add('cash_received', 'El efectivo recibido no puede ser inferior al monto a abonar.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'El monto a abonar es obligatorio.',
            'amount.min' => 'El monto debe ser superior a $0.',
            'method.required' => 'Debe seleccionar un método de pago.',
            'method.in' => 'El método de pago no es válido.',
        ];
    }
}
