@extends('layouts.app')

@section('title', 'Estado de Cuenta: ' . $customer->name)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <a href="{{ route('credit.index') }}" class="text-decoration-none text-muted small fw-semibold">
            <i class="bi bi-arrow-left me-1"></i> Volver a Cartera General
        </a>
        <h3 class="fw-bold mb-0 text-dark mt-1">
            Estado de Cartera: {{ $customer->name }}
        </h3>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('customers.show', $customer) }}" class="btn btn-outline-secondary rounded-pill px-3">
            <i class="bi bi-person me-1"></i> Ficha Completa
        </a>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm rounded-4 bg-white p-4">
            <h5 class="fw-bold text-dark mb-1">{{ $customer->name }}</h5>
            <div class="text-muted small font-monospace mb-2">{{ $customer->document ?: 'Sin documento' }}</div>
            <div class="small text-muted mb-3">
                <i class="bi bi-telephone me-1"></i> {{ $customer->phone ?: 'Sin teléfono' }}
            </div>

            <hr>

            @php
                $pendingBalance = $balance ?? $totalPending ?? 0;
            @endphp
            <div class="p-3 rounded-4 {{ $pendingBalance > 0 ? 'bg-danger bg-opacity-10 border border-danger border-opacity-25' : 'bg-success bg-opacity-10 border border-success border-opacity-25' }}">
                <span class="small fw-bold text-uppercase {{ $pendingBalance > 0 ? 'text-danger' : 'text-success' }}">Saldo Total Pendiente</span>
                <h2 class="fw-bold mb-0 mt-1 {{ $pendingBalance > 0 ? 'text-danger' : 'text-success' }}">
                    ${{ number_format($pendingBalance, 0, ',', '.') }}
                </h2>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
            <div class="p-3 border-bottom">
                <h6 class="fw-bold text-dark mb-0">Facturas a Crédito del Cliente</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr class="small text-uppercase text-muted">
                            <th class="ps-3">Factura</th>
                            <th>Emisión</th>
                            <th>Vencimiento</th>
                            <th class="text-end">Total</th>
                            <th class="text-end">Abonado</th>
                            <th class="text-end">Saldo</th>
                            <th class="text-center">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse(($creditSales ?? $sales ?? []) as $sale)
                            <tr>
                                <td class="ps-3 font-monospace fw-bold text-dark">{{ $sale->invoice_number }}</td>
                                <td><small class="text-muted">{{ $sale->created_at->format('d/m/Y') }}</small></td>
                                <td>
                                    @if($sale->credit_due_date)
                                        <span class="badge {{ \Carbon\Carbon::parse($sale->credit_due_date)->isPast() && $sale->credit_status === 'pending' ? 'bg-danger text-white' : 'bg-light text-dark border' }}">
                                            {{ \Carbon\Carbon::parse($sale->credit_due_date)->format('d/m/Y') }}
                                        </span>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                                <td class="text-end fw-semibold">${{ number_format($sale->total, 0, ',', '.') }}</td>
                                <td class="text-end text-success">${{ number_format($sale->credit_paid_amount, 0, ',', '.') }}</td>
                                <td class="text-end fw-bold text-danger">
                                    @if($sale->isCancelled() || $sale->credit_status === 'cancelled')
                                        <span class="text-muted text-decoration-line-through">$0</span>
                                    @else
                                        ${{ number_format($sale->credit_pending_amount, 0, ',', '.') }}
                                    @endif
                                </td>
                                <td class="text-center">
                                    @if($sale->isCancelled() || $sale->credit_status === 'cancelled')
                                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 rounded-pill">
                                            <i class="bi bi-x-circle me-1"></i>Anulada
                                        </span>
                                    @elseif($sale->credit_status === 'paid')
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 rounded-pill">
                                            <i class="bi bi-check-circle me-1"></i>Saldada
                                        </span>
                                    @else
                                        <span class="badge bg-warning bg-opacity-25 text-dark border border-warning rounded-pill">
                                            <i class="bi bi-clock me-1"></i>Pendiente
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted small">No tiene facturas a crédito registradas.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
