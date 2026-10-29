<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pre-cuenta {{ $order->order_number }}</title>
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
            <div class="ticket-title text-uppercase" style="letter-spacing: 0.5px;">SaborCaliFit</div>
            <div class="ticket-subtitle">BOCHALEMA • ARBOLATTA MALL</div>
            <div class="ticket-subtitle">Cl. 48 #106-86, Cali, Valle del Cauca</div>
            <div class="ticket-subtitle" style="font-weight: 700;">WhatsApp Pedidos: 312 770 4455</div>
            <div class="fw-bold text-uppercase" style="font-size: 13px; margin-top: 4px; color: #000000;">*** PRE-CUENTA INFORMATIVA ***</div>
        </div>

        <div class="box-highlight">
            <div class="ticket-bold" style="font-size: 11px;">
                DOCUMENTO INFORMATIVO<br>
                NO VÁLIDO COMO FACTURA O COMPROBANTE DE PAGO
            </div>
        </div>

        <div style="font-size: 13px;">
            <div><strong>ORDEN:</strong> {{ $order->order_number }}</div>
            <div><strong>UBICACIÓN:</strong> {{ $order->table ? $order->table->name : $order->order_type_label }}</div>
            <div><strong>FECHA/HORA:</strong> {{ now()->format('d/m/Y H:i:s') }}</div>
            <div><strong>MESERO:</strong> {{ $order->waiter_name ?: ($order->user->name ?? 'Personal') }}</div>
            <div><strong>COMENSALES:</strong> {{ $order->guest_count ?? 1 }}</div>
        </div>

        <div class="ticket-divider-double"></div>

        <table class="table-items">
            <thead>
                <tr>
                    <th style="width: 11%; text-align: left;">CANT</th>
                    <th style="width: 41%; text-align: left;">DETALLE</th>
                    <th style="width: 23%; text-align: right; padding-right: 6px;">VAL.U</th>
                    <th style="width: 25%; text-align: right;">TOTAL</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $item)
                    <tr>
                        <td style="font-weight: bold;">{{ (float)$item->quantity }}</td>
                        <td style="word-wrap: break-word; overflow-wrap: break-word; white-space: normal;">{{ $item->product->nombre }}</td>
                        <td class="text-right" style="white-space: nowrap; padding-right: 6px;">${{ number_format($item->unit_price, 0, ',', '.') }}</td>
                        <td class="text-right" style="white-space: nowrap; font-weight: bold;">${{ number_format(round((float)$item->quantity * (float)$item->unit_price, 2), 0, ',', '.') }}</td>
                    </tr>
                    @if($item->additions && $item->additions->count() > 0)
                        @foreach($item->additions as $add)
                            <tr>
                                <td></td>
                                <td colspan="2" class="addition-row">• + {{ $add->addition->nombre ?? $add->nombre }} (x{{ (float)$add->quantity }})</td>
                                <td class="text-right addition-row" style="white-space: nowrap;">+${{ number_format($add->subtotal, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    @endif
                @endforeach
            </tbody>
        </table>

        <div class="ticket-divider"></div>

        @php
            $subtotalAlimentos = (float)$order->subtotal;
            $propinaSugerida10 = round($subtotalAlimentos * 0.10, 2);
            $deliveryFeePrebill = ($order->order_type === 'delivery') ? (float)($order->delivery_fee ?? 0) : 0.0;
            $totalConPropina = $subtotalAlimentos + $propinaSugerida10 + $deliveryFeePrebill;
        @endphp

        <table style="width: 100%; border-collapse: collapse; font-size: 13.5px;">
            <tr>
                <td>Subtotal Alimentos:</td>
                <td class="text-right" style="white-space: nowrap;">${{ number_format($subtotalAlimentos, 0, ',', '.') }}</td>
            </tr>
            @if($order->order_type === 'delivery')
                <tr>
                    <td>Flete Domicilio:</td>
                    <td class="text-right" style="white-space: nowrap;">${{ number_format($deliveryFeePrebill, 0, ',', '.') }}</td>
                </tr>
            @endif
            @if($order->order_type === 'table')
                <tr>
                    <td>Propina Sugerida (10%):</td>
                    <td class="text-right" style="white-space: nowrap;">${{ number_format($propinaSugerida10, 0, ',', '.') }}</td>
                </tr>
                <tr class="ticket-bold fs-total">
                    <td style="padding-top: 5px;">TOTAL CON PROPINA:</td>
                    <td class="text-right" style="padding-top: 5px; white-space: nowrap;">${{ number_format($totalConPropina, 0, ',', '.') }}</td>
                </tr>
                <tr class="ticket-divider">
                    <td colspan="2"></td>
                </tr>
                <tr style="font-size: 13px;">
                    <td style="padding-top: 4px;">TOTAL SIN PROPINA:</td>
                    <td class="text-right" style="padding-top: 4px; white-space: nowrap;">${{ number_format($subtotalAlimentos + $deliveryFeePrebill, 0, ',', '.') }}</td>
                </tr>
            @else
                <tr class="ticket-bold fs-total">
                    <td style="padding-top: 5px;">TOTAL A PAGAR:</td>
                    <td class="text-right" style="padding-top: 5px; white-space: nowrap;">${{ number_format($subtotalAlimentos + $deliveryFeePrebill, 0, ',', '.') }}</td>
                </tr>
            @endif
        </table>

        @if($order->order_type === 'table')
            <div style="font-size: 10.5px; margin-top: 5px; text-align: justify; line-height: 1.25;">
                * La propina es totalmente voluntaria. Puede ser aceptada, modificada o rechazada al momento del pago.
            </div>
        @endif

        <div class="ticket-divider-double"></div>

        <div class="ticket-footer" style="font-size: 12px;">
            Solicite su factura electrónica si la requiere.<br>
            ¡Gracias por preferir la comida saludable!
        </div>
    </div>
</body>
</html>
