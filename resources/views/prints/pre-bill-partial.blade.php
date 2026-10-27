<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pre-cuenta Parcial - {{ $order->table ? $order->table->name : $order->order_number }}</title>
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
        {{-- Cabecera con Logo --}}
        <div class="ticket-header text-center pb-2 mb-2" style="border-bottom: 1.5px dashed #000000; padding-bottom: 4px; margin-bottom: 4px;">
            <img src="{{ asset('images/brand/logo-califit.svg') }}" 
                 alt="SaborCaliFit" 
                 class="ticket-logo mb-1">
            <div class="fw-bold fs-6 text-uppercase" style="letter-spacing: 0.5px; color: #000000;">SaborCaliFit</div>
            <div style="font-size: 10px; color: #000000;">BOCHALEMA • ARBOLATTA MALL</div>
            <div style="font-size: 9.5px; color: #000000;">Cl. 48 #106-86, Cali, Valle del Cauca</div>
            <div style="font-size: 9.5px; color: #000000;">WhatsApp Pedidos: +57 312 770 4455</div>
            <div class="fw-bold text-uppercase" style="font-size: 10.5px; margin-top: 3px; color: #000000;">
                *** PRE-CUENTA PARCIAL (CONSUMO INDIVIDUAL) ***
            </div>
        </div>

        <div class="box-highlight">
            <div class="ticket-bold" style="font-size: 9px;">
                DOCUMENTO INFORMATIVO DE REVISIÓN<br>
                NO VÁLIDO COMO FACTURA O COMPROBANTE DE VENTA
            </div>
        </div>

        @php
            $tableName = $order->table ? (str_starts_with(trim($order->table->name), 'Mesa') ? $order->table->name : 'Mesa ' . $order->table->name) : 'Salón';
        @endphp

        <div style="font-size: 10px;">
            <div><strong>ORDEN:</strong> {{ $order->order_number }} &bull; <strong>MESA:</strong> {{ $tableName }}</div>
            <div><strong>FECHA/HORA:</strong> {{ now()->format('d/m/Y H:i') }}</div>
            <div><strong>MESERO:</strong> {{ $order->waiter_name ?: ($order->user->name ?? 'Personal') }}</div>
        </div>

        <div class="ticket-divider-double"></div>

        <table class="table-items">
            <thead>
                <tr>
                    <th style="width: 16%; text-align: left;">CANT</th>
                    <th style="width: 54%; text-align: left;">DESCRIPCIÓN</th>
                    <th style="width: 30%; text-align: right;">VALOR</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $item)
                    <tr>
                        <td style="font-weight: 700; white-space: nowrap;">[{{ (int)$item->quantity }}]</td>
                        <td style="word-wrap: break-word; overflow-wrap: break-word; white-space: normal;">{{ $item->product->nombre }}</td>
                        <td class="text-right" style="white-space: nowrap; font-weight: bold;">${{ number_format(round((float)$item->quantity * (float)$item->unit_price, 2), 0, ',', '.') }}</td>
                    </tr>
                    @if($item->additions && $item->additions->count() > 0)
                        @foreach($item->additions as $add)
                            <tr>
                                <td></td>
                                <td class="addition-row">+ {{ $add->addition->nombre ?? $add->nombre }} (x{{ (int)$add->quantity }})</td>
                                <td class="text-right addition-row" style="white-space: nowrap;">+${{ number_format($add->subtotal, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    @endif
                @endforeach
            </tbody>
        </table>

        <div class="ticket-divider"></div>

        @php
            $propinaSugerida10 = round($subtotal * 0.10, 0);
            $totalConPropina = $subtotal + $propinaSugerida10;
        @endphp

        <table style="width: 100%; border-collapse: collapse; font-size: 10.5px;">
            <tr>
                <td><strong>SUBTOTAL PLATOS:</strong></td>
                <td class="text-right" style="white-space: nowrap;"><strong>${{ number_format($subtotal, 0, ',', '.') }}</strong></td>
            </tr>
            <tr>
                <td>Servicio Voluntario (10% sugerido):</td>
                <td class="text-right" style="white-space: nowrap;">${{ number_format($propinaSugerida10, 0, ',', '.') }}</td>
            </tr>
            <tr class="ticket-bold" style="font-size: 11.5px;">
                <td style="padding-top: 3px;">TOTAL SIN PROPINA:</td>
                <td class="text-right" style="padding-top: 3px; white-space: nowrap;">${{ number_format($subtotal, 0, ',', '.') }}</td>
            </tr>
            <tr class="ticket-bold" style="font-size: 12.5px;">
                <td>TOTAL CON SERVICIO SUGERIDO:</td>
                <td class="text-right" style="white-space: nowrap;">${{ number_format($totalConPropina, 0, ',', '.') }}</td>
            </tr>
        </table>

        <div class="ticket-divider"></div>

        <div class="ticket-footer" style="font-size: 9px; margin-top: 3px;">
            Documento informativo para revisión del comensal.<br>
            No válido como factura o comprobante de venta.<br>
            ¡Gracias por preferir la nutrición activa de SaborCaliFit!
        </div>
    </div>
</body>
</html>
