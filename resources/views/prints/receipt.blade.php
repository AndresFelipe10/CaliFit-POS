<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comprobante de Venta {{ $sale->invoice_number }}</title>
    <style>
    @page {
        size: 80mm auto;
        margin: 0mm !important;
    }
    * {
        box-sizing: border-box;
    }
    html, body {
        width: 80mm;
        max-width: 80mm;
        margin: 0 !important;
        padding: 0 !important;
        background: #FFFFFF;
        color: #000000 !important;
        font-family: 'Courier New', Courier, monospace;
        font-size: 13.5px;
        line-height: 1.35;
        font-weight: 600;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    body.thermal-ticket {
        width: 80mm;
        max-width: 80mm;
        margin: 0 auto;
        background: #FFFFFF;
        position: relative;
    }
    .ticket-content {
        width: 100%;
        max-width: 74mm;
        margin: 0 auto;
        padding: 3mm 2mm;
        box-sizing: border-box;
        overflow: hidden;
        position: relative;
        z-index: 1;
    }
    .ticket-header, .ticket-footer {
        text-align: center;
    }
    .ticket-logo {
        max-width: 62mm;
        height: auto;
        max-height: 42mm;
        display: block;
        margin: 0 auto 8px auto;
        filter: contrast(200%);
    }
    .ticket-title {
        font-size: 17px;
        font-weight: 900;
        letter-spacing: 0.5px;
    }
    .ticket-subtitle {
        font-size: 12px;
        line-height: 1.3;
    }
    .ticket-divider {
        border-top: 1px dashed #000000;
        margin: 5px 0;
        width: 100%;
    }
    .ticket-divider-double {
        border-top: 2px solid #000000;
        margin: 6px 0;
        width: 100%;
    }
    .ticket-bold {
        font-weight: 900;
    }
    .text-right {
        text-align: right;
    }
    .text-center {
        text-align: center;
    }
    .text-uppercase {
        text-transform: uppercase;
    }
    .fw-bold {
        font-weight: 900;
    }
    .fs-title {
        font-size: 16px;
        font-weight: 800;
    }
    .fs-total {
        font-size: 18.5px;
        font-weight: 900;
    }
    .border-bottom {
        border-bottom: 1.5px dashed #000000;
    }
    .pb-2 {
        padding-bottom: 5px;
    }
    .mb-2 {
        margin-bottom: 5px;
    }
    .mb-1 {
        margin-bottom: 3px;
    }
    .table-items {
        width: 100%;
        table-layout: fixed;
        border-collapse: collapse;
    }
    .table-items th {
        border-bottom: 1.5px solid #000000;
        padding: 3px 2px;
        font-size: 12.5px;
        font-weight: 800;
    }
    .table-items td {
        padding: 3px 2px;
        vertical-align: top;
        font-size: 13.5px;
        word-wrap: break-word;
        overflow-wrap: break-word;
    }
    .addition-row {
        font-size: 12.5px;
        padding-left: 8px;
        font-weight: 700;
    }
    .box-highlight {
        border: 2px solid #000000;
        padding: 5px 6px;
        margin: 6px 0;
        text-align: center;
    }
    .row-flex {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        width: 100%;
    }
    body, .ticket, table, th, td, p, span, div, strong, small {
        color: #000000 !important;
        font-family: 'Courier New', Courier, monospace;
    }
    .border, .border-bottom, .border-top, hr {
        border-color: #000000 !important;
        opacity: 1 !important;
    }
    .text-muted, .text-secondary, .text-black-50 {
        color: #000000 !important;
    }
    @media print {
        *, *::before, *::after {
            color: #000000 !important;
            text-shadow: none !important;
            box-shadow: none !important;
        }
        body {
            width: 80mm !important;
            max-width: 80mm !important;
            margin: 0 !important;
            padding: 0 !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            background: #FFFFFF !important;
        }
        .ticket-content {
            width: 100% !important;
            max-width: 74mm !important;
            margin: 0 auto !important;
            padding: 2mm 1.5mm !important;
        }
    }
    </style>
    <script>
    window.addEventListener('DOMContentLoaded', () => {
        window.print();
    });
    </script>
</head>
<body class="thermal-ticket">
    <div class="ticket-content">
        {{-- Cabecera Oficial con Logo Monocromático --}}
        <div class="ticket-header text-center pb-2 mb-2 border-bottom">
            <img src="{{ asset('images/brand/logo-califit.svg') }}" 
                 alt="SaborCaliFit" 
                 class="ticket-logo mb-1">
            <div class="ticket-title text-uppercase" style="letter-spacing: 0.5px;">SABORCALIFIT | BOCHALEMA</div>
            <div class="ticket-subtitle">Arbolatta Mall • Cali, Valle</div>
            <div class="ticket-subtitle">Cl. 48 #106-86, Bochalema</div>
            <div class="ticket-subtitle" style="font-weight: 700;">WhatsApp Pedidos: 312 770 4455</div>
        </div>

        @php
            $ro = $sale->restaurantOrder;
            // Determinar canal en español
            $effectiveOrderType = $sale->order_type ?: ($ro ? $ro->order_type : 'pos');
            $canalLabel = match($effectiveOrderType) {
                'delivery' => 'DOMICILIO',
                'takeout' => 'PARA LLEVAR / RECOGER',
                'table' => 'SALÓN / MESA',
                default => 'MOSTRADOR POS',
            };

            // Solo mostrar comprobante parcial si la orden es de mesa/salón dividida mediante división de cuenta (split)
            // y no es una comanda liquidada totalmente
            $isPartialSplit = false;
            if ($effectiveOrderType === 'table' && $ro && $ro->isSplit()) {
                if (!str_starts_with((string) $sale->notes, 'Liquidación total')) {
                    $isPartialSplit = true;
                }
            }
        @endphp

        {{-- Datos de la Venta --}}
        <div style="font-size: 13px;">
            <div><strong>COMPROBANTE:</strong> {{ $sale->invoice_number }}</div>
            <div><strong>FECHA:</strong> {{ $sale->created_at->format('d/m/Y H:i:s') }}</div>
            <div><strong>CAJERO:</strong> {{ $sale->user->name ?? 'Caja General' }}</div>
            @if($sale->waiter_name)
                <div><strong>MESERO:</strong> {{ $sale->waiter_name }}</div>
            @endif
            <div><strong>CLIENTE:</strong> {{ $sale->customer_name }}</div>
            <div><strong>DOC/NIT:</strong> {{ $sale->customer_document }}</div>
            <div><strong>CANAL:</strong> {{ $canalLabel }}</div>

            @if($effectiveOrderType === 'delivery' && $ro)
                <div><strong>ORDEN DE DOMICILIO:</strong> {{ $ro->order_number }}</div>
                @if($ro->address)
                    <div><strong>DIRECCIÓN:</strong> {{ $ro->address }}</div>
                @endif
                @if($ro->phone)
                    <div><strong>TELÉFONO:</strong> {{ $ro->phone }}</div>
                @endif
            @elseif($effectiveOrderType === 'takeout' && $ro)
                <div><strong>ORDEN PARA LLEVAR:</strong> {{ $ro->order_number }}</div>
                @if($ro->phone)
                    <div><strong>TELÉFONO:</strong> {{ $ro->phone }}</div>
                @endif
            @elseif($effectiveOrderType === 'table' && $ro)
                @php
                    $tName = $ro->table ? (str_starts_with(trim($ro->table->name), 'Mesa') ? $ro->table->name : 'Mesa ' . $ro->table->name) : 'Salón';
                @endphp
                <div><strong>COMANDA:</strong> {{ $ro->order_number }} • {{ $tName }}</div>
            @endif

            @if($sale->is_credit)
                <div class="box-highlight" style="font-weight: 900; background: #000; color: #FFF !important; padding: 4px;">
                    *** VENTA A CRÉDITO / FIADO ***
                </div>
                <div><strong>ESTADO CRÉDITO:</strong> {{ $sale->credit_status === 'pending' ? 'PENDIENTE' : ($sale->credit_status === 'paid' ? 'PAGADO' : 'ANULADO') }}</div>
                @if($sale->credit_due_date)
                    <div><strong>VENCE:</strong> {{ \Carbon\Carbon::parse($sale->credit_due_date)->format('d/m/Y') }}</div>
                @endif
            @endif

            @if($isPartialSplit && $ro)
                @php
                    $rTableName = $ro->table ? (str_starts_with(trim($ro->table->name), 'Mesa') ? $ro->table->name : 'Mesa ' . $ro->table->name) : 'Salón';
                @endphp
                <div class="box-highlight" style="font-weight: 900; margin: 5px 0;">
                    *** COMPROBANTE DE PAGO PARCIAL ***<br>
                    <span style="font-size: 11px; font-weight: normal; display: block; margin-top: 2px;">
                        COBRO PARCIAL DE COMANDA: {{ $ro->order_number }}<br>
                        MESA: {{ $rTableName }}
                    </span>
                </div>
            @endif
        </div>

        <div class="ticket-divider"></div>

        {{-- Tabla de Productos y Adiciones con Ancho Calibrado a 74mm (Cero Solapamiento) --}}
        <table class="table-items">
            <thead>
                <tr>
                    <th style="width: 11%; text-align: left;">CANT</th>
                    <th style="width: 41%; text-align: left;">DESCRIPCIÓN</th>
                    <th style="width: 23%; text-align: right; padding-right: 6px;">VAL.U</th>
                    <th style="width: 25%; text-align: right;">TOTAL</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sale->details as $det)
                    <tr>
                        <td style="font-weight: bold;">{{ (float)$det->quantity }}</td>
                        <td style="word-wrap: break-word; overflow-wrap: break-word; white-space: normal;">{{ $det->product->nombre }}</td>
                        <td class="text-right" style="white-space: nowrap; padding-right: 6px;">${{ number_format($det->unit_price, 0, ',', '.') }}</td>
                        <td class="text-right" style="white-space: nowrap; font-weight: bold;">${{ number_format($det->subtotal, 0, ',', '.') }}</td>
                    </tr>
                    @if($det->additions && $det->additions->count() > 0)
                        @foreach($det->additions as $add)
                            <tr>
                                <td></td>
                                <td colspan="2" class="addition-row">• + {{ $add->addition->nombre ?? 'Adición' }} (x{{ (float)$add->quantity }})</td>
                                <td class="text-right addition-row" style="white-space: nowrap;">+${{ number_format($add->subtotal, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    @endif
                @endforeach
            </tbody>
        </table>

        <div class="ticket-divider"></div>

        {{-- Totales Financieros --}}
        <table style="width: 100%; border-collapse: collapse; font-size: 13.5px;">
            <tr>
                <td>Subtotal Productos:</td>
                <td class="text-right" style="white-space: nowrap;">${{ number_format($sale->subtotal, 0, ',', '.') }}</td>
            </tr>
            @if((float)$sale->discount > 0)
                <tr>
                    <td>Descuento ({{ (float)$sale->discount_percentage }}%):</td>
                    <td class="text-right" style="white-space: nowrap;">-${{ number_format($sale->discount, 0, ',', '.') }}</td>
                </tr>
            @endif
            @if($effectiveOrderType === 'delivery')
                <tr>
                    <td>Flete Domicilio:</td>
                    <td class="text-right" style="white-space: nowrap;">+${{ number_format($sale->delivery_fee ?? 0, 0, ',', '.') }}</td>
                </tr>
            @elseif((float)$sale->delivery_fee > 0 && $effectiveOrderType !== 'takeout')
                <tr>
                    <td>Flete Domicilio:</td>
                    <td class="text-right" style="white-space: nowrap;">+${{ number_format($sale->delivery_fee, 0, ',', '.') }}</td>
                </tr>
            @endif
            @if((float)$sale->tip > 0)
                <tr>
                    <td>PROPINA VOLUNTARIA:</td>
                    <td class="text-right" style="white-space: nowrap;">+${{ number_format($sale->tip, 0, ',', '.') }}</td>
                </tr>
            @elseif((float)$sale->service_fee > 0)
                <tr>
                    <td>PROPINA VOLUNTARIA:</td>
                    <td class="text-right" style="white-space: nowrap;">+${{ number_format($sale->service_fee, 0, ',', '.') }}</td>
                </tr>
            @endif
            @if((float)$sale->tax_inc > 0)
                <tr>
                    <td>IMPUESTO CONSUMO (8%):</td>
                    <td class="text-right" style="white-space: nowrap;">+${{ number_format($sale->tax_inc, 0, ',', '.') }}</td>
                </tr>
            @endif
            <tr class="ticket-bold fs-total">
                <td style="padding-top: 5px;">TOTAL A PAGAR:</td>
                <td class="text-right" style="padding-top: 5px; white-space: nowrap;">${{ number_format($sale->total, 0, ',', '.') }}</td>
            </tr>
        </table>

        <div class="ticket-divider"></div>

        {{-- Desglose de Pagos --}}
        <div style="font-size: 13.5px;">
            <div class="ticket-bold" style="font-size: 14.5px; margin-bottom: 3px;">FORMA DE PAGO:</div>
            @if($sale->is_credit)
                <div class="row-flex">
                    <span>Crédito / Fiado:</span>
                    <span style="white-space: nowrap;">${{ number_format($sale->total, 0, ',', '.') }}</span>
                </div>
                <div class="row-flex" style="font-size: 12.5px;">
                    <span>&nbsp; Total Abonado:</span>
                    <span style="white-space: nowrap;">${{ number_format($sale->credit_paid_amount, 0, ',', '.') }}</span>
                </div>
                <div class="row-flex" style="font-size: 12.5px; font-weight: bold;">
                    <span>&nbsp; SALDO PENDIENTE:</span>
                    <span style="white-space: nowrap;">${{ number_format($sale->credit_pending_amount, 0, ',', '.') }}</span>
                </div>
            @endif

            @foreach($sale->payments as $pay)
                @php
                    $methodName = match($pay->method) {
                        'cash' => 'Efectivo',
                        'transfer' => 'Transferencia / Nequi',
                        'card' => 'Tarjeta',
                        'credit' => 'Crédito Inicial',
                        default => 'Otro'
                    };
                @endphp
                <div class="row-flex">
                    <span style="word-break: break-word;">{{ $methodName }} {{ $pay->reference ? "({$pay->reference})" : '' }}:</span>
                    <span style="white-space: nowrap;">${{ number_format($pay->amount, 0, ',', '.') }}</span>
                </div>
                @if($pay->method === 'cash' && $pay->cash_received)
                    <div class="row-flex" style="font-size: 12.5px; color: #000000;">
                        <span>&nbsp; Efectivo recibido:</span>
                        <span style="white-space: nowrap;">${{ number_format($pay->cash_received, 0, ',', '.') }}</span>
                    </div>
                    <div class="row-flex" style="font-size: 12.5px; color: #000000;">
                        <span>&nbsp; Cambio / Vuelto:</span>
                        <span style="white-space: nowrap;">${{ number_format($pay->change_given ?? 0, 0, ',', '.') }}</span>
                    </div>
                @endif
            @endforeach
        </div>

        <div class="ticket-divider-double"></div>

        {{-- Pie Legal y Agradecimiento --}}
        <div class="ticket-footer">
            <div style="font-size: 11.5px; margin-bottom: 4px;">
                Comprobante interno de venta.<br>
                No reemplaza la factura electrónica DIAN.
            </div>
            <div class="ticket-bold" style="font-size: 14.5px; margin-top: 5px;">¡GRACIAS POR TU COMPRA FIT!</div>
            <div style="font-size: 12.5px;">@saborcalifit.bochalema</div>
        </div>
    </div>
</body>
</html>
