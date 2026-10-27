<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comanda Cocina {{ $order->order_number }} - Tanda {{ $batch }}</title>
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
        max-width: 34mm;
        height: auto;
        max-height: 18mm;
        display: block;
        margin: 0 auto 3px auto;
        filter: contrast(200%);
    }
    .ticket-title {
        font-size: 12px;
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
        word-wrap: break-word;
        overflow-wrap: break-word;
    }
    .addition-row {
        font-size: 10px;
        padding-left: 6px;
        font-weight: bold;
    }
    .notes-box {
        font-size: 9.5px;
        font-style: italic;
        padding-left: 6px;
    }
    .box-highlight {
        border: 1.5px solid #000000;
        padding: 4px;
        margin: 4px 0;
        text-align: center;
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
        {{-- Cabecera Ultra-Compacta con Logo Vectorial --}}
        <div class="ticket-header text-center pb-2 mb-2 border-bottom">
            <img src="{{ asset('images/brand/logo-califit.svg') }}" 
                 alt="SaborCaliFit" 
                 class="ticket-logo mb-1">
            <div class="fw-bold fs-6 text-uppercase" style="letter-spacing: 0.5px; color: #000000;">*** COMANDA COCINA / BARRA ***</div>
            <div style="font-size: 10px; color: #000000;">SABORCALIFIT | BOCHALEMA</div>
            @if(request()->boolean('reprint') || !empty($isReprint))
                <div style="background: #000000; color: #ffffff !important; font-weight: 900; font-size: 11px; padding: 2px 4px; margin: 3px 0; text-align: center; letter-spacing: 0.5px;">
                    ** COMANDA REIMPRESA **
                </div>
            @endif
        </div>

        <div class="box-highlight">
            <div class="ticket-bold" style="font-size: 14px;">
                @if($order->table)
                    UBICACIÓN: {{ strtoupper($order->table->name) }}
                @elseif($order->order_type === 'delivery')
                    CANAL: DOMICILIO
                @elseif($order->order_type === 'takeout')
                    CANAL: PARA LLEVAR
                @else
                    CANAL: SALÓN
                @endif
            </div>
            <div class="ticket-bold" style="font-size: 12px; margin-top: 1px;">TANDA #{{ $batch }}</div>
        </div>

        <div style="font-size: 10px;">
            <div><strong>ORDEN:</strong> {{ $order->order_number }}</div>
            <div><strong>HORA:</strong> {{ now()->format('d/m/Y H:i:s') }}</div>
            <div><strong>MESERO:</strong> {{ $order->waiter_name ?: ($order->user->name ?? 'Personal de Servicio') }}</div>
            @if($order->customer_name)
                <div><strong>CLIENTE:</strong> {{ $order->customer_name }}</div>
            @endif
        </div>

        <div class="ticket-divider-double"></div>

        {{-- LISTA DE PLATOS Y ADICIONES (ESTRICTAMENTE SIN PRECIOS NI $) --}}
        <table class="table-items">
            <thead>
                <tr>
                    <th style="width: 22%; text-align: left;">CANT</th>
                    <th style="width: 78%; text-align: left;">DESCRIPCIÓN PREPARACIÓN</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $item)
                    <tr>
                        <td style="font-size: 13px; font-weight: 900; white-space: nowrap;">[ {{ (float)$item->quantity }} ]</td>
                        <td style="font-size: 12px; font-weight: 900; word-wrap: break-word;">{{ $item->product->nombre }}</td>
                    </tr>
                    @if($item->additions && $item->additions->count() > 0)
                        @foreach($item->additions as $add)
                            <tr>
                                <td></td>
                                <td class="addition-row">&bull; + {{ $add->addition->nombre ?? $add->nombre }} (x{{ (float)$add->quantity }})</td>
                            </tr>
                        @endforeach
                    @endif
                    @if($item->notes)
                        <tr>
                            <td></td>
                            <td class="notes-box">>>> NOTA: {{ $item->notes }}</td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>

        @if($order->notes)
            <div class="ticket-divider"></div>
            <div style="font-size: 10px;">
                <strong>OBSERVACIONES GENERALES:</strong><br>
                {{ $order->notes }}
            </div>
        @endif

        <div class="ticket-divider-double"></div>

        <div class="ticket-footer" style="font-size: 10px; font-weight: bold;">
            *** PREPARAR CON ALTO ESTÁNDAR FIT ***
        </div>
    </div>
</body>
</html>
