<div class="card border-0 shadow-sm rounded-3 p-2 bg-white">
    <div class="d-flex justify-content-between align-items-center mb-1">
        <span class="badge {{ $order->order_type === 'delivery' ? 'bg-info-subtle text-info-emphasis' : 'bg-secondary-subtle text-secondary-emphasis' }} rounded-pill" style="font-size: 0.7rem;">
            <i class="bi {{ $order->order_type === 'delivery' ? 'bi-bicycle' : 'bi-bag' }} me-1"></i>{{ $order->order_type_label }}
        </span>
        <span class="fw-bold text-dark small">#{{ $order->order_number }}</span>
    </div>

    <div class="fw-bold text-dark text-truncate" style="font-size: 0.88rem;">
        <i class="bi bi-person me-1 text-muted"></i>{{ $order->customer_name ?? 'Consumidor Final' }}
    </div>

    @php
        $displayNotes = $order->clean_notes ?: $order->notes;
    @endphp
    @if($displayNotes)
        <div class="text-muted text-truncate small" style="font-size: 0.75rem;" title="{{ $displayNotes }}">
            <i class="bi bi-info-circle me-1"></i>{{ $displayNotes }}
        </div>
    @endif

    <div class="my-1 py-1 border-top border-bottom" style="font-size: 0.78rem;">
        @foreach($order->items->take(3) as $item)
            <div class="d-flex justify-content-between text-muted text-truncate">
                <span>{{ (float)$item->quantity }}x {{ $item->product->nombre }}</span>
                @if($item->additions && $item->additions->count() > 0)
                    <span class="text-success small">(+{{ $item->additions->count() }} adic.)</span>
                @endif
            </div>
        @endforeach
        @if($order->items->count() > 3)
            <div class="text-muted small fst-italic">+ {{ $order->items->count() - 3 }} productos más...</div>
        @endif
    </div>

    <div class="d-flex justify-content-between align-items-center mb-2">
        <span class="small text-muted">{{ $order->order_type === 'takeout' ? 'Total a Pagar:' : 'Total + Flete:' }}</span>
        <span class="fw-black text-success" style="color: #10B981 !important;">${{ number_format($order->total, 0, ',', '.') }}</span>
    </div>

    <div class="d-flex gap-1">
        <button type="button" onclick="printDispatchTicket({{ $order->id }}, '{{ route('prints.orders.dispatch', $order) }}')" class="btn btn-outline-secondary btn-sm p-1 flex-grow-1" style="font-size: 0.72rem;" title="Imprimir Tirilla de Despacho">
            <i class="bi bi-printer"></i> Tirilla
        </button>

        @if($nextStatus)
            @if($order->order_type === 'takeout' && in_array($order->status, ['open', 'in_kitchen']))
                {{-- En columna 1, pedido Para Llevar salta directo a Entregado (Por Cobrar) --}}
                <button type="button" class="btn btn-success btn-sm flex-grow-1 fw-bold p-1" style="font-size: 0.72rem;" onclick="advanceOrderStatus({{ $order->id }}, 'delivered', this)">
                    <i class="bi bi-bag-check-fill me-1"></i> Entregado / Recogido ->
                </button>
            @else
                <button type="button" class="btn btn-outline-success btn-sm p-1 flex-grow-1 fw-semibold" style="font-size: 0.72rem;" onclick="advanceOrderStatus({{ $order->id }}, '{{ $nextStatus }}', this)">
                    {{ $nextLabel }}
                </button>
            @endif
        @else
            <a href="{{ route('restaurant.orders.show', $order) }}" class="btn btn-califit btn-sm p-1 flex-grow-1 fw-bold" style="font-size: 0.72rem;">
                <i class="bi bi-wallet2 me-1"></i> {{ $nextLabel }}
            </a>
        @endif
    </div>
</div>
