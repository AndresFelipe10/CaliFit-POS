@extends('layouts.app')

@section('title', 'Historial del Proveedor: ' . $supplier->name)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <a href="{{ route('suppliers.index') }}" class="text-decoration-none text-muted small fw-semibold">
            <i class="bi bi-arrow-left me-1"></i> Volver a Proveedores
        </a>
        <h3 class="fw-bold mb-0 text-dark mt-1">
            Ficha del Proveedor
        </h3>
    </div>
    <div class="d-flex gap-2">
        <form action="{{ route('suppliers.toggle-status', $supplier) }}" method="POST" class="d-inline">
            @csrf
            @method('PATCH')
            <button type="submit" class="btn btn-outline-secondary rounded-pill px-3">
                <i class="bi bi-arrow-repeat me-1"></i> {{ $supplier->is_active ? 'Desactivar' : 'Activar' }}
            </button>
        </form>
    </div>
</div>

<div class="row g-4 mb-4">
    {{-- Tarjeta de Perfil y Contacto --}}
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm rounded-4 bg-white p-4 h-100">
            <div class="text-center mb-3">
                <div class="rounded-circle d-inline-flex align-items-center justify-content-center text-white mb-2" style="width: 70px; height: 70px; background-color: #10B981; font-size: 1.8rem;">
                    <i class="bi bi-truck"></i>
                </div>
                <h5 class="fw-bold text-dark mb-0">{{ $supplier->name }}</h5>
                <span class="badge bg-light text-secondary border font-monospace mt-1">NIT: {{ $supplier->nit }}</span>
                <div class="mt-2">
                    @if ($supplier->is_active)
                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-2 py-1 rounded-pill small">Activo</span>
                    @else
                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-2 py-1 rounded-pill small">Inactivo</span>
                    @endif
                </div>
            </div>

            <hr class="my-3">

            <div class="d-flex flex-column gap-2 small">
                <div>
                    <span class="text-muted d-block">Contacto / Asesor:</span>
                    <strong class="text-dark">{{ $supplier->contact_name ?: 'No especificado' }}</strong>
                </div>
                <div>
                    <span class="text-muted d-block">Teléfono / Celular:</span>
                    <strong class="text-dark">{{ $supplier->phone ?: 'No registrado' }}</strong>
                </div>
                <div>
                    <span class="text-muted d-block">Correo Electrónico:</span>
                    <strong class="text-dark">{{ $supplier->email ?: 'No registrado' }}</strong>
                </div>
                <div>
                    <span class="text-muted d-block">Dirección / Sede:</span>
                    <strong class="text-dark">{{ $supplier->address ?: 'No registrada' }}</strong>
                </div>
            </div>
        </div>
    </div>

    {{-- Resumen de Compras / Facturas --}}
    <div class="col-lg-8">
        <div class="row g-3 mb-4">
            <div class="col-sm-6">
                <div class="card border-0 shadow-sm rounded-4 bg-white p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small fw-semibold text-uppercase">Facturas / Gastos</span>
                            <h3 class="fw-bold mb-0 text-dark">{{ $supplier->expenses->count() }}</h3>
                        </div>
                        <div class="rounded-circle p-2 bg-primary bg-opacity-10 text-primary">
                            <i class="bi bi-receipt-cutoff fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-sm-6">
                <div class="card border-0 shadow-sm rounded-4 bg-white p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted small fw-semibold text-uppercase">Total Facturado</span>
                            <h3 class="fw-bold mb-0 text-danger">
                                ${{ number_format($supplier->expenses->sum('amount'), 0, ',', '.') }}
                            </h3>
                        </div>
                        <div class="rounded-circle p-2 bg-danger bg-opacity-10 text-danger">
                            <i class="bi bi-cash-stack fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Tabla de Facturas y Gastos Asociados --}}
        <div class="card border-0 shadow-sm rounded-4 bg-white overflow-hidden">
            <div class="p-3 border-bottom d-flex align-items-center justify-content-between">
                <h6 class="fw-bold text-dark mb-0">
                    <i class="bi bi-journal-check me-2" style="color: #10B981;"></i>Facturas y Compras Asociadas
                </h6>
                <span class="small text-muted">Últimos registros</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr class="small text-uppercase text-muted">
                            <th class="ps-3">Código / Factura</th>
                            <th>Fecha Emisión</th>
                            <th>Categoría</th>
                            <th>Monto</th>
                            <th class="text-end pe-3">Comprobante</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($supplier->expenses as $expense)
                            <tr>
                                <td class="ps-3">
                                    <span class="font-monospace fw-bold text-dark">{{ $expense->invoice_number ?: '#' . $expense->id }}</span>
                                    <div class="small text-muted">{{ $expense->description }}</div>
                                </td>
                                <td>
                                    <small class="text-muted">{{ $expense->issue_date ? \Carbon\Carbon::parse($expense->issue_date)->format('d/m/Y') : '—' }}</small>
                                </td>
                                <td>
                                    <span class="badge bg-light text-secondary border small">{{ $expense->category }}</span>
                                </td>
                                <td>
                                    <span class="fw-bold text-danger">${{ number_format($expense->amount, 0, ',', '.') }}</span>
                                </td>
                                <td class="text-end pe-3">
                                    @if ($expense->receipt_file_path)
                                        <a href="{{ route('expenses.receipt', $expense) }}" target="_blank" class="btn btn-sm btn-outline-primary rounded-pill px-3" title="Descargar Comprobante">
                                            <i class="bi bi-file-earmark-pdf me-1"></i> Ver
                                        </a>
                                    @else
                                        <span class="text-muted small">Sin adjunto</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted small">
                                    No hay gastos o facturas de compras registradas para este proveedor.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
