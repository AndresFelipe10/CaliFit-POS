<?php

namespace App\Http\Requests\Restaurant;

use App\Models\RestaurantOrder;
use App\Models\RestaurantTable;
use Illuminate\Foundation\Http\FormRequest;

class ChangeOrderTableRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     * Acción disponible para todos los roles autorizados en Salón: admin y employee.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->isAdmin() || $user->isEmployee());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'table_id' => ['required', 'integer', 'exists:restaurant_tables,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'table_id.required' => 'Debe seleccionar una mesa de destino.',
            'table_id.integer' => 'El identificador de mesa debe ser un número entero.',
            'table_id.exists' => 'La mesa seleccionada no existe en el sistema.',
        ];
    }

    /**
     * Configure the validator instance with additional business rules.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $order = $this->route('order');
            if (! $order instanceof RestaurantOrder) {
                $order = RestaurantOrder::find($order);
            }

            if (! $order) {
                $validator->errors()->add('order', 'La comanda especificada no existe.');
                return;
            }

            // Solo en pedidos de salón activos
            if ($order->order_type !== 'table') {
                $validator->errors()->add('order', 'Solo se pueden transferir comandas asignadas a mesas del salón.');
            }

            if (! in_array($order->status, ['open', 'in_kitchen'], true)) {
                $validator->errors()->add('order', 'Solo se pueden cambiar de mesa comandas activas (en estado abierta o en cocina).');
            }

            $newTableId = (int) $this->input('table_id');

            // Rechazo de mesa redundante
            if ($order->table_id && (int) $order->table_id === $newTableId) {
                $validator->errors()->add('table_id', 'La comanda ya se encuentra asignada a esta mesa.');
                return;
            }

            // Mesa destino disponible y activa
            $newTable = RestaurantTable::find($newTableId);
            if ($newTable) {
                if (! $newTable->is_active) {
                    $validator->errors()->add('table_id', 'La mesa seleccionada se encuentra inactiva.');
                } elseif ($newTable->status !== 'available') {
                    $validator->errors()->add('table_id', 'La mesa seleccionada ya se encuentra ocupada o en cobro.');
                }
            }
        });
    }
}
