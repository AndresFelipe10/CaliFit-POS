<?php

namespace App\Http\Requests;

use App\Traits\NormalizesCurrencyInputs;
use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
{
    use NormalizesCurrencyInputs;

    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('amount')) {
            $this->merge(['amount' => $this->normalizeCurrency($this->input('amount'))]);
        }

        if ($this->input('status') === 'paid') {
            if (! $this->filled('paid_at')) {
                $this->merge(['paid_at' => $this->input('issue_date') ?: date('Y-m-d')]);
            }
            if (! $this->filled('payment_method')) {
                $this->merge(['payment_method' => 'cash']);
            }
        }
    }

    public function rules(): array
    {
        return [
            'category' => ['required', 'in:merchandise,utilities,rent,supplies,payroll,other'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'issue_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'description' => ['nullable', 'string', 'max:255'],
            'invoice_number' => ['nullable', 'string', 'max:50'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'status' => ['required', 'in:paid,pending'],
            'paid_at' => ['nullable', 'date'],
            'payment_method' => ['nullable', 'string', 'in:cash,card,transfer,other'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:3072'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->hasFile('attachment')) {
                $file = $this->file('attachment');
                $originalName = $file->getClientOriginalName();

                // Rechazar nombres con extensiones ejecutables o doble extensión
                if (preg_match('/\.(php[0-9]?|phtml|phar|exe|sh|bat|cmd|html|htm|svg|js|cgi|pl|py)(\.|$)/i', $originalName)) {
                    $validator->errors()->add('attachment', 'El archivo contiene una extensión o formato no permitido por motivos de seguridad.');
                    return;
                }

                // Validar MIME real del archivo
                $allowedMimes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
                $detectedMime = $file->getMimeType();
                if (! in_array($detectedMime, $allowedMimes, true)) {
                    $validator->errors()->add('attachment', 'El archivo no corresponde a un tipo MIME de documento o imagen permitido.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'attachment.mimes' => 'El comprobante debe ser un archivo PDF, JPG, PNG o WEBP.',
            'attachment.max' => 'El tamaño máximo permitido para el comprobante es de 3 MB.',
            'due_date.after_or_equal' => 'La fecha de vencimiento no puede ser anterior a la fecha de emisión.',
            'paid_at.required_if' => 'Debe indicar la fecha de pago si el gasto está marcado como pagado.',
            'payment_method.required_if' => 'Debe indicar el método de pago si el gasto está marcado como pagado.',
        ];
    }
}
