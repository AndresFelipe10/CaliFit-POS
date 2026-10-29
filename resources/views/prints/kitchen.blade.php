<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comanda Cocina {{ $order->order_number }} - Tanda {{ $batch }}</title>
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
        font-size: 14px;
        line-height: 1.35;
        font-weight: 700;
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
        font-size: 18px;
        font-weight: 900;
        letter-spacing: 0.5px;
    }
    .ticket-subtitle {
        font-size: 13px;
        line-height: 1.3;
    }
    .ticket-divider {
        border-top: 1.5px dashed #000000;
        margin: 5px 0;
        width: 100%;
    }
    .ticket-divider-double {
        border-top: 2.5px solid #000000;
        margin: 6px 0;
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
    .border-bottom {
        border-bottom: 2px dashed #000000;
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
        border-bottom: 2px solid #000000;
        padding: 4px 0;
        font-size: 14px;
        font-weight: 900;
    }
    .table-items td {
        padding: 4px 0;
        vertical-align: top;
        word-wrap: break-word;
        overflow-wrap: break-word;
    }
    .dish-qty {
        font-size: 18.5px;
        font-weight: 900;
        white-space: nowrap;
        vertical-align: top;
        padding-top: 3px;
    }
    .dish-name {
        font-size: 18.5px;
        font-weight: 900;
        line-height: 1.25;
        word-wrap: break-word;
        padding: 3px 0;
    }
    .addition-row {
        font-size: 16px;
        padding-left: 10px;
        font-weight: 800;
        line-height: 1.3;
    }
    .notes-box {
        font-size: 15px;
        font-weight: 800;
        font-style: italic;
        padding-left: 10px;
        line-height: 1.3;
    }
    .box-highlight {
        border: 2px solid #000000;
        padding: 6px;
        margin: 6px 0;
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
        {{-- Cabecera con Logo --}}
        <div class="ticket-header text-center pb-2 mb-2 border-bottom">
            <img src="{{ asset('images/brand/logo-califit.svg') }}" 
                 alt="SaborCaliFit" 
                 class="ticket-logo mb-1">
            <div class="ticket-title text-uppercase">*** COMANDA COCINA / BARRA ***</div>
            <div class="ticket-subtitle">SABORCALIFIT | BOCHALEMA</div>
            @if(request()->boolean('reprint') || !empty($isReprint))
                <div style="background: #000000; color: #ffffff !important; font-weight: 900; font-size: 13px; padding: 3px 6px; margin: 4px 0; text-align: center; letter-spacing: 0.5px;">
                    ** COMANDA REIMPRESA **
                </div>
            @endif
        </div>

        {{-- Recuadro de Ubicación y Tanda en Tamaño Extra-Legible (19px) --}}
        <div class="box-highlight">
            <div class="ticket-bold" style="font-size: 19px; line-height: 1.25;">
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
            <div class="ticket-bold" style="font-size: 17px; margin-top: 3px;">TANDA #{{ $batch }}</div>
        </div>

        {{-- Metadatos en Tamaño 14.5px --}}
        <div style="font-size: 14.5px; font-weight: 700;">
            <div><strong>ORDEN:</strong> {{ $order->order_number }}</div>
            <div><strong>HORA:</strong> {{ now()->format('d/m/Y H:i:s') }}</div>
            <div><strong>MESERO:</strong> {{ $order->waiter_name ?: ($order->user->name ?? 'Personal de Servicio') }}</div>
            @if($order->customer_name)
                <div><strong>CLIENTE:</strong> {{ $order->customer_name }}</div>
            @endif
        </div>

        <div class="ticket-divider-double"></div>

        {{-- LISTA DE PLATOS Y ADICIONES EN TIPOGRAFÍA GIGANTE Y EXTRA-LEGIBLE (17px / 15px) --}}
        <table class="table-items">
            <thead>
                <tr>
                    <th style="width: 20%; text-align: left;">CANT</th>
                    <th style="width: 80%; text-align: left;">DESCRIPCIÓN PREPARACIÓN</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $item)
                    <tr>
                        <td class="dish-qty">[ {{ (float)$item->quantity }} ]</td>
                        <td class="dish-name">{{ $item->product->nombre }}</td>
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

        @php
            $orderInstructions = $order->clean_notes ?: $order->notes;
        @endphp
        @if($orderInstructions)
            <div class="ticket-divider"></div>
            <div style="font-size: 13px; font-weight: 700;">
                <strong>OBSERVACIONES GENERALES:</strong><br>
                {{ $orderInstructions }}
            </div>
        @endif

        <div class="ticket-divider-double"></div>

        <div class="ticket-footer" style="font-size: 13px; font-weight: 900;">
            *** PREPARAR CON ALTO ESTÁNDAR FIT ***
        </div>
    </div>
</body>
</html>
