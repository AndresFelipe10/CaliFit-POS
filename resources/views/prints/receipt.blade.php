<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comprobante de Venta {{ $sale->invoice_number }}</title>
    <style>
    @page {
        size: 58mm auto;
        margin: 0mm !important;
    }
    * {
        box-sizing: border-box;
    }
    html, body {
        width: 58mm;
        max-width: 58mm;
        margin: 0 !important;
        padding: 0 !important;
        background: #FFFFFF;
        color: #000000 !important;
        font-family: 'Courier New', Courier, monospace;
        font-size: 11px;
        line-height: 1.25;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    body.thermal-ticket {
        width: 58mm;
        max-width: 58mm;
        margin: 0 auto;
        background: #FFFFFF;
        position: relative;
    }
    .ticket-content {
        width: 100%;
        max-width: 50mm;
        margin: 0 auto;
        padding: 2mm 1.5mm;
        box-sizing: border-box;
        overflow: hidden;
        position: relative;
        z-index: 1;
    }
    .ticket-header, .ticket-footer {
        text-align: center;
    }
    .ticket-logo {
        max-width: 36mm;
        height: auto;
        max-height: 20mm;
        display: block;
        margin: 0 auto 3px auto;
        filter: contrast(200%);
    }
    .ticket-title {
        font-size: 13px;
        font-weight: 900;
        letter-spacing: 0.5px;
    }
    .ticket-subtitle {
        font-size: 10px;
    }
    .ticket-divider {
        border-top: 1px dashed #000000;
        margin: 4px 0;
        width: 100%;
    }
    .ticket-divider-double {
        border-top: 2px solid #000000;
        margin: 4px 0;
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
    .fs-6 {
        font-size: 12px;
    }
    .border-bottom {
        border-bottom: 1.5px dashed #000000;
    }
    .pb-2 {
        padding-bottom: 4px;
    }
    .mb-2 {
        margin-bottom: 4px;
    }
    .mb-1 {
        margin-bottom: 2px;
    }
    .table-items {
        width: 100%;
        table-layout: fixed;
        border-collapse: collapse;
    }
    .table-items th {
        border-bottom: 1px solid #000000;
        padding: 2px 0;
        font-size: 9.5px;
    }
    .table-items td {
        padding: 2px 0;
        vertical-align: top;
        font-size: 10px;
        word-wrap: break-word;
        overflow-wrap: break-word;
    }
    .addition-row {
        font-size: 9px;
        padding-left: 6px;
        font-style: italic;
    }
    .box-highlight {
        border: 1.5px solid #000000;
        padding: 3px 4px;
        margin: 4px 0;
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
            width: 58mm !important;
            max-width: 58mm !important;
            margin: 0 !important;
            padding: 0 !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            background: #FFFFFF !important;
        }
        .ticket-content {
            width: 100% !important;
            max-width: 50mm !important;
            margin: 0 auto !important;
            padding: 1mm 1mm !important;
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
            <div class="fw-bold fs-6 text-uppercase" style="letter-spacing: 0.5px; color: #000000;">SABORCALIFIT | BOCHALEMA</div>
            <div style="font-size: 10px; color: #000000;">Arbolatta Mall • Cali, Valle</div>
            <div style="font-size: 9.5px; color: #000000;">Cl. 48 #106-86, Bochalema</div>
            <div style="font-size: 9.5px; color: #000000;">WhatsApp Pedidos: +57 312 770 4455</div>
        </div>

        {{-- Datos de la Venta --}}
        <div style="font-size: 10px;">
            <div><strong>COMPROBANTE:</strong> {{ $sale->invoice_number }}</div>
            <div><strong>FECHA:</strong> {{ $sale->created_at->format('d/m/Y H:i:s') }}</div>
            <div><strong>CAJERO:</strong> {{ $sale->user->name ?? 'Caja General' }}</div>
            @if($sale->waiter_name)
                <div><strong>MESERO:</strong> {{ $sale->waiter_name }}</div>
            @endif
            <div><strong>CLIENTE:</strong> {{ $sale->customer_name }}</div>
            <div><strong>DOC/NIT:</strong> {{ $sale->customer_document }}</div>
            @if($sale->order_type)
                <div><strong>CANAL:</strong> {{ strtoupper($sale->order_type) }}</div>
            @endif
            @if($sale->is_credit)
                <div class="box-highlight" style="font-weight: 900; background: #000; color: #FFF !important; padding: 3px;">
                    *** VENTA A CRÉDITO / FIADO ***
                </div>
                <div><strong>ESTADO CRÉDITO:</strong> {{ strtoupper($sale->credit_status) }}</div>
                @if($sale->credit_due_date)
                    <div><strong>VENCE:</strong> {{ \Carbon\Carbon::parse($sale->credit_due_date)->format('d/m/Y') }}</div>
                @endif
            @endif
            @if(optional($sale->restaurantOrder)->split_type)
                @php
                    $ro = $sale->restaurantOrder;
                    $rTableName = $ro->table ? (str_starts_with(trim($ro->table->name), 'Mesa') ? $ro->table->name : 'Mesa ' . $ro->table->name) : 'Salón';
                @endphp
                <div class="box-highlight" style="font-weight: 900; margin: 4px 0;">
                    *** COMPROBANTE DE PAGO PARCIAL ***<br>
                    <span style="font-size: 8.5px; font-weight: normal; display: block; margin-top: 2px;">
                        COBRO PARCIAL DE COMANDA: {{ $ro->order_number }}<br>
                        MESA: {{ $rTableName }}
                    </span>
                </div>
            @endif
        </div>

        <div class="ticket-divider"></div>

        {{-- Tabla de Productos y Adiciones --}}
        <table class="table-items">
            <thead>
                <tr>
                    <th style="width: 14%; text-align: left;">CANT</th>
                    <th style="width: 44%; text-align: left;">DESCRIPCIÓN</th>
                    <th style="width: 20%; text-align: right;">VAL.U</th>
                    <th style="width: 22%; text-align: right;">TOTAL</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sale->details as $det)
                    <tr>
                        <td style="font-weight: bold;">{{ (float)$det->quantity }}</td>
                        <td style="word-wrap: break-word; overflow-wrap: break-word; white-space: normal;">{{ $det->product->nombre }}</td>
                        <td class="text-right" style="white-space: nowrap;">${{ number_format($det->unit_price, 0, ',', '.') }}</td>
                        <td class="text-right" style="white-space: nowrap; font-weight: bold;">${{ number_format($det->subtotal, 0, ',', '.') }}</td>
                    </tr>
                    @if($det->additions && $det->additions->count() > 0)
                        @foreach($det->additions as $add)
                            <tr>
                                <td></td>
                                <td colspan="2" class="addition-row">+ {{ $add->addition->nombre ?? 'Adición' }} (x{{ (float)$add->quantity }})</td>
                                <td class="text-right addition-row" style="white-space: nowrap;">+${{ number_format($add->subtotal, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    @endif
                @endforeach
            </tbody>
        </table>

        <div class="ticket-divider"></div>

        {{-- Totales Financieros --}}
        <table style="width: 100%; border-collapse: collapse; font-size: 10.5px;">
            <tr>
                <td>Subtotal:</td>
                <td class="text-right" style="white-space: nowrap;">${{ number_format($sale->subtotal, 0, ',', '.') }}</td>
            </tr>
            @if((float)$sale->discount > 0)
                <tr>
                    <td>Descuento ({{ (float)$sale->discount_percentage }}%):</td>
                    <td class="text-right" style="white-space: nowrap;">-${{ number_format($sale->discount, 0, ',', '.') }}</td>
                </tr>
            @endif
            @if($sale->order_type === 'delivery' || optional($sale->restaurantOrder)->order_type === 'delivery')
                <tr>
                    <td>Flete Domicilio:</td>
                    <td class="text-right" style="white-space: nowrap;">+${{ number_format($sale->delivery_fee ?? 0, 0, ',', '.') }}</td>
                </tr>
            @elseif((float)$sale->delivery_fee > 0 && $sale->order_type !== 'takeout')
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
            <tr class="ticket-bold" style="font-size: 12.5px;">
                <td style="padding-top: 4px;">TOTAL A PAGAR:</td>
                <td class="text-right" style="padding-top: 4px; white-space: nowrap;">${{ number_format($sale->total, 0, ',', '.') }}</td>
            </tr>
        </table>

        <div class="ticket-divider"></div>

        {{-- Desglose de Pagos --}}
        <div style="font-size: 10px;">
            <div class="ticket-bold" style="font-size: 10.5px; margin-bottom: 2px;">FORMA DE PAGO:</div>
            @if($sale->is_credit)
                <div class="row-flex">
                    <span>Crédito / Fiado:</span>
                    <span style="white-space: nowrap;">${{ number_format($sale->total, 0, ',', '.') }}</span>
                </div>
                <div class="row-flex" style="font-size: 9.5px;">
                    <span>&nbsp; Total Abonado:</span>
                    <span style="white-space: nowrap;">${{ number_format($sale->credit_paid_amount, 0, ',', '.') }}</span>
                </div>
                <div class="row-flex" style="font-size: 9.5px; font-weight: bold;">
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
                    <div class="row-flex" style="font-size: 9.5px; color: #000000;">
                        <span>&nbsp; Efectivo recibido:</span>
                        <span style="white-space: nowrap;">${{ number_format($pay->cash_received, 0, ',', '.') }}</span>
                    </div>
                    <div class="row-flex" style="font-size: 9.5px; color: #000000;">
                        <span>&nbsp; Cambio / Vuelto:</span>
                        <span style="white-space: nowrap;">${{ number_format($pay->change_given ?? 0, 0, ',', '.') }}</span>
                    </div>
                @endif
            @endforeach
        </div>

        <div class="ticket-divider-double"></div>

        {{-- Pie Legal y Agradecimiento --}}
        <div class="ticket-footer">
            <div style="font-size: 9px; margin-bottom: 3px;">
                Comprobante interno de venta.<br>
                No reemplaza la factura electrónica DIAN.
            </div>
            <div class="ticket-bold" style="font-size: 11px; margin-top: 4px;">¡GRACIAS POR TU COMPRA FIT!</div>
            <div style="font-size: 9.5px;">@saborcalifit.bochalema</div>
        </div>
    </div>
</body>
</html>
