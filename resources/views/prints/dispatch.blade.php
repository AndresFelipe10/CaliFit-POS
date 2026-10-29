<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Tirilla Despacho {{ $order->order_number }}</title>
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
    .border-bottom {
        border-bottom: 1.5px dashed #000000;
    }
    .pb-2 {
        padding-bottom: 5px;
    }
    .mb-2 {
        margin-bottom: 5px;
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
        <div class="ticket-header text-center pb-2 mb-2 border-bottom">
            <img src="{{ asset('images/brand/logo-califit.svg') }}" 
                 alt="SaborCaliFit" 
                 class="ticket-logo mb-1">
            <div class="ticket-title text-uppercase" style="letter-spacing: 0.5px;">*** TIRILLA DE DESPACHO ***</div>
            <div class="ticket-subtitle">SABORCALIFIT | BOCHALEMA</div>
            <div class="ticket-subtitle">Cl. 48 #106-86, Cali, Valle del Cauca</div>
            <div class="ticket-subtitle" style="font-weight: 700;">WhatsApp Pedidos: 312 770 4455</div>
        </div>

        @php
            $orderCanal = $order->order_type === 'delivery' ? 'DOMICILIO' : 'PARA LLEVAR';
            $custPhone = $order->phone;
            $custAddress = $order->address;
            $custInstructions = $order->clean_notes;
        @endphp

        <div style="font-size: 13px;">
            <div><strong>ORDEN:</strong> {{ $order->order_number }}</div>
            <div><strong>CANAL:</strong> {{ $orderCanal }}</div>
            <div><strong>FECHA / HORA:</strong> {{ $order->created_at->format('d/m/Y H:i:s') }}</div>
            <div><strong>DESPACHADO POR:</strong> {{ $order->user->name ?? 'Caja' }}</div>
        </div>

        {{-- RECUADRO DESTACADO DE ENTREGA CON CAMPOS ESTRICTAMENTE SEPARADOS --}}
        <div class="box-highlight" style="font-size: 13.5px;">
            <div class="ticket-bold" style="font-size: 14.5px; margin-bottom: 3px;">DATOS DEL DESTINATARIO:</div>
            <div><strong>CLIENTE:</strong> {{ $order->customer_name ?: 'Consumidor Final' }}</div>
            @if($custPhone)
                <div><strong>TELÉFONO:</strong> {{ $custPhone }}</div>
            @endif
            @if($order->order_type === 'delivery')
                <div><strong>DIRECCIÓN:</strong> {{ $custAddress ?: 'No especificada' }}</div>
            @else
                <div><strong>MODALIDAD:</strong> Recoge en Local</div>
            @endif
            @if($custInstructions)
                <div style="margin-top: 3px; word-wrap: break-word;"><strong>INSTRUCCIONES:</strong> {{ $custInstructions }}</div>
            @endif
        </div>

        <div class="ticket-divider"></div>

        {{-- DETALLE DE PLATOS Y ADICIONES EMPACADAS CON PRECIOS DISCRIMINADOS --}}
        <div class="ticket-bold" style="font-size: 13.5px; margin-bottom: 3px;">PRODUCTOS EMPACADOS:</div>
        <table class="table-items">
            <thead>
                <tr>
                    <th style="width: 12%; text-align: left;">CANT</th>
                    <th style="width: 58%; text-align: left;">DESCRIPCIÓN</th>
                    <th style="width: 30%; text-align: right;">VALOR</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $item)
                    @php
                        // Subtotal base del plato sin adiciones para claridad contable
                        $dishBasePrice = round((float)$item->quantity * (float)$item->unit_price, 2);
                    @endphp
                    <tr>
                        <td style="font-weight: bold; white-space: nowrap;">[ {{ (float)$item->quantity }} ]</td>
                        <td style="font-weight: bold; word-wrap: break-word;">{{ $item->product->nombre }}</td>
                        <td class="text-right" style="white-space: nowrap; font-weight: bold;">${{ number_format($dishBasePrice, 0, ',', '.') }}</td>
                    </tr>
                    @if($item->additions && $item->additions->count() > 0)
                        @foreach($item->additions as $add)
                            <tr>
                                <td></td>
                                <td class="addition-row">&bull; + {{ $add->addition->nombre ?? $add->nombre }} (x{{ (float)$add->quantity }})</td>
                                <td class="text-right addition-row" style="white-space: nowrap;">+${{ number_format($add->subtotal, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    @endif
                    @if($item->notes)
                        <tr>
                            <td></td>
                            <td colspan="2" style="font-size: 11px; font-style: italic; padding-left: 8px;">Nota: {{ $item->notes }}</td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>

        {{-- RECUADRO ENMARCADO DE COBRO CONTRAENTREGA --}}
        <div class="box-highlight" style="margin-top: 8px; background-color: #FFFFFF; font-size: 13.5px;">
            <div class="ticket-bold text-center" style="font-size: 14.5px; margin-bottom: 4px;">COBRO CONTRAENTREGA:</div>
            <div class="row-flex">
                <span>VALOR PRODUCTOS:</span>
                <span style="white-space: nowrap;">${{ number_format($order->subtotal, 0, ',', '.') }}</span>
            </div>
            @if($order->order_type === 'delivery')
            <div class="row-flex">
                <span>FLETE DOMICILIO:</span>
                <span style="white-space: nowrap;">${{ number_format($order->delivery_fee ?? 0, 0, ',', '.') }}</span>
            </div>
            @endif
            <div class="ticket-divider" style="margin: 3px 0;"></div>
            <div class="row-flex" style="font-size: 18.5px; font-weight: 900;">
                <span>TOTAL A COBRAR:</span>
                <span style="white-space: nowrap;">${{ number_format($order->total, 0, ',', '.') }}</span>
            </div>
        </div>

        <div class="ticket-divider-double"></div>

        <div class="ticket-footer" style="font-size: 13.5px; font-weight: 800; margin-top: 6px;">
            ¡Que disfrutes tu comida saludable!
        </div>
    </div>
</body>
</html>
