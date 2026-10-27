<?php

namespace App\Services\Credit;

use App\Models\Customer;
use App\Models\CustomerCreditPayment;
use App\Models\Sale;
use App\Models\SalePayment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CustomerCreditService
{
    /**
     * Calcula el saldo total adeudado por un cliente en compras a crédito.
     */
    public function getCustomerBalance(Customer $customer): float
    {
        $totalCredit = (float) Sale::where('customer_id', $customer->id)
            ->where('is_credit', true)
            ->where('status', 'completed')
            ->sum('total');

        $totalPaid = (float) CustomerCreditPayment::where('customer_id', $customer->id)
            ->whereHas('sale', fn ($q) => $q->where('status', 'completed'))
            ->sum('amount');

        return round(max(0.0, $totalCredit - $totalPaid), 2);
    }

    /**
     * Registra un abono o pago total a una factura a crédito de forma atómica y bajo bloqueo pesimista.
     *
     * @param  Sale   $sale
     * @param  array  $paymentData ['amount', 'method', 'cash_received', 'reference', 'notes']
     * @param  User   $user
     * @return CustomerCreditPayment
     *
     * @throws InvalidArgumentException
     */
    public function registerPayment(Sale $sale, array $paymentData, User $user): CustomerCreditPayment
    {
        return DB::transaction(function () use ($sale, $paymentData, $user) {
            /** @var Sale $lockedSale */
            $lockedSale = Sale::where('id', $sale->id)->lockForUpdate()->firstOrFail();

            if (! $lockedSale->isCredit()) {
                throw new InvalidArgumentException("La factura {$lockedSale->invoice_number} no fue liquidada bajo modalidad a crédito.");
            }

            if ($lockedSale->status !== 'completed' || $lockedSale->credit_status === 'cancelled') {
                throw new InvalidArgumentException("No se pueden recibir pagos para una factura en estado {$lockedSale->status}.");
            }

            $alreadyPaid = (float) $lockedSale->creditPayments()->sum('amount');
            $pendingBalance = round((float) $lockedSale->total - $alreadyPaid, 2);

            if ($pendingBalance <= 0.0) {
                if ($lockedSale->credit_status !== 'paid') {
                    $lockedSale->update(['credit_status' => 'paid']);
                }
                throw new InvalidArgumentException("La factura {$lockedSale->invoice_number} ya se encuentra totalmente saldada.");
            }

            $amount = round((float) ($paymentData['amount'] ?? 0), 2);
            if ($amount <= 0) {
                throw new InvalidArgumentException('El monto del abono debe ser mayor a $0.');
            }

            if ($amount > $pendingBalance) {
                $pendingFmt = number_format($pendingBalance, 0, ',', '.');
                $amountFmt = number_format($amount, 0, ',', '.');
                throw new InvalidArgumentException("El abono (\${$amountFmt}) no puede superar el saldo pendiente de la factura (\${$pendingFmt}).");
            }

            $method = strtolower(trim((string) ($paymentData['method'] ?? 'cash')));
            $validMethods = ['cash', 'card', 'transfer', 'other'];
            if (! in_array($method, $validMethods, true)) {
                throw new InvalidArgumentException("Método de pago no válido: {$method}.");
            }

            $cashReceived = null;
            $changeGiven = null;

            if ($method === 'cash') {
                $cashReceived = isset($paymentData['cash_received']) && (float) $paymentData['cash_received'] > 0
                    ? round((float) $paymentData['cash_received'], 2)
                    : $amount;

                if ($cashReceived < $amount) {
                    $recvFmt = number_format($cashReceived, 0, ',', '.');
                    $amtFmt = number_format($amount, 0, ',', '.');
                    throw new InvalidArgumentException("El efectivo recibido (\${$recvFmt}) no puede ser menor al monto a pagar (\${$amtFmt}).");
                }

                $changeGiven = round($cashReceived - $amount, 2);
            }

            $now = now();

            // 1. Registrar abono en customer_credit_payments
            $creditPayment = CustomerCreditPayment::create([
                'sale_id' => $lockedSale->id,
                'customer_id' => $lockedSale->customer_id,
                'user_id' => $user->id,
                'method' => $method,
                'amount' => $amount,
                'cash_received' => $cashReceived,
                'change_given' => $changeGiven,
                'reference' => $paymentData['reference'] ?? null,
                'notes' => $paymentData['notes'] ?? null,
                'paid_at' => $now,
            ]);

            // 2. Registrar en sale_payments con timestamp actual para trazabilidad
            SalePayment::create([
                'sale_id' => $lockedSale->id,
                'method' => $method,
                'amount' => $amount,
                'reference' => $paymentData['reference'] ?? ("Abono crédito " . $lockedSale->invoice_number),
                'cash_received' => $cashReceived,
                'change_given' => $changeGiven,
            ]);

            // 3. Actualizar estado de crédito si la deuda quedó cubierta al 100%
            $newTotalPaid = round($alreadyPaid + $amount, 2);
            if ($newTotalPaid >= round((float) $lockedSale->total, 2)) {
                $lockedSale->update(['credit_status' => 'paid']);
            } else {
                $lockedSale->update(['credit_status' => 'pending']);
            }

            return $creditPayment->load(['sale', 'customer', 'user']);
        });
    }

    /**
     * Retorna el historial de compras a crédito de un cliente con sus respectivos abonos.
     *
     * @return Collection<int, Sale>
     */
    public function getCustomerCreditSales(Customer $customer): Collection
    {
        return Sale::where('customer_id', $customer->id)
            ->where('is_credit', true)
            ->with(['creditPayments.user', 'details.product'])
            ->latest('created_at')
            ->get();
    }
}
