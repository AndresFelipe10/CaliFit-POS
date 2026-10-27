<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cuadre de Caja {{ $date }} | SaborCaliFit</title>
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
        font-size: 10.5px;
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
        font-size: 12px;
        font-weight: 900;
        letter-spacing: 0.5px;
    }
    .ticket-subtitle {
        font-size: 9.5px;
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
    .row-flex {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        width: 100%;
    }
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .text-left { text-align: left; }
    .fw-bold { font-weight: bold; }
    .fs-lg { font-size: 12px; }
    .fs-sm { font-size: 9.5px; }
    .signature-box {
        margin-top: 12px;
        padding-top: 8px;
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
        body.thermal-ticket {
            width: 58mm !important;
            max-width: 58mm !important;
            margin: 0 auto !important;
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
        .no-print {
            display: none !important;
        }
    }
    </style>
</head>
<body class="thermal-ticket">
    <div class="ticket-content">
        {{-- Encabezado del Negocio --}}
        <div class="ticket-header text-center pb-2 mb-2" style="border-bottom: 1.5px dashed #000000; padding-bottom: 4px; margin-bottom: 4px;">
            <img src="{{ asset('images/brand/logo-califit.svg') }}" 
                 alt="SaborCaliFit" 
                 class="ticket-logo mb-1">
            <div class="ticket-title">SABORCALIFIT | BOCHALEMA</div>
            <div class="ticket-subtitle">Suplementación y Comida Saludable</div>
            <div class="ticket-subtitle">Arbolatta Mall - Cali, Colombia</div>
            <div class="ticket-divider-double"></div>
            <div class="fw-bold fs-lg">*** CUADRE DE CAJA DIARIO ***</div>
            <div class="ticket-divider"></div>
        </div>

        {{-- Metadatos del Cuadre --}}
        <div class="fs-sm">
            <div class="row-flex">
                <span>FECHA:</span>
                <span class="fw-bold">{{ $date }}</span>
            </div>
            <div class="row-flex">
                <span>HORA EMISIÓN:</span>
                <span>{{ now()->timezone('America/Bogota')->format('H:i:s') }}</span>
            </div>
            <div class="row-flex">
                <span>RESPONSABLE:</span>
                <span style="max-width: 28mm; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ auth()->user()->name ?? 'Cajero / Admin' }}</span>
            </div>
            <div class="row-flex">
                <span>VENTAS CERRADAS:</span>
                <span class="fw-bold">{{ $salesCount }}</span>
            </div>
        </div>

        <div class="ticket-divider-double"></div>

        {{-- Resumen Financiero Facturado --}}
        <div class="text-center fw-bold fs-sm">--- RESUMEN FACTURACIÓN ---</div>
        <div class="ticket-divider"></div>
        <div class="row-flex">
            <span>Subtotal Bruto:</span>
            <span style="white-space: nowrap;">${{ number_format($grossSales, 0, ',', '.') }}</span>
        </div>
        @if($discounts > 0)
        <div class="row-flex">
            <span>(-) Descuentos:</span>
            <span style="white-space: nowrap;">-${{ number_format($discounts, 0, ',', '.') }}</span>
        </div>
        @endif
        @if($serviceTips > 0)
        <div class="row-flex">
            <span>(+) Propinas/Servicio:</span>
            <span style="white-space: nowrap;">+${{ number_format($serviceTips, 0, ',', '.') }}</span>
        </div>
        @endif
        <div class="row-flex">
            <span>Imp. Consumo (INC 8%):</span>
            <span style="white-space: nowrap;">${{ number_format($taxInc, 0, ',', '.') }}</span>
        </div>
        <div class="row-flex">
            <span>Total Fletes Domicilio:</span>
            <span style="white-space: nowrap;">${{ number_format($deliveryFees, 0, ',', '.') }}</span>
        </div>
        <div class="ticket-divider"></div>
        <div class="row-flex fw-bold fs-lg">
            <span>TOTAL FACTURADO:</span>
            <span style="white-space: nowrap;">${{ number_format($netTotalBilled, 0, ',', '.') }}</span>
        </div>
        @if($creditSalesTotal > 0)
        <div class="row-flex fs-sm" style="margin-top: 2px;">
            <span>↳ Créditos (Fiados):</span>
            <span class="fw-bold" style="white-space: nowrap;">${{ number_format($creditSalesTotal, 0, ',', '.') }}</span>
        </div>
        <div class="fs-sm text-center" style="font-size: 8.5px;">
            (Cartera por cobrar - No ingresó a caja hoy)
        </div>
        @endif

        <div class="ticket-divider-double"></div>

        {{-- Discriminación por Canales de Venta --}}
        <div class="text-center fw-bold fs-sm">--- CANALES DE VENTA ---</div>
        <div class="ticket-divider"></div>
        <div class="row-flex">
            <span>Salón [{{ $tableSales->count() }}]:</span>
            <span class="fw-bold" style="white-space: nowrap;">${{ number_format($tableTotal, 0, ',', '.') }}</span>
        </div>
        <div class="row-flex">
            <span>Domicilios [{{ $deliverySales->count() }}]:</span>
            <span class="fw-bold" style="white-space: nowrap;">${{ number_format($deliveryTotal, 0, ',', '.') }}</span>
        </div>
        @if($deliveryFeesTotal > 0)
        <div class="row-flex fs-sm" style="padding-left: 6px;">
            <span>↳ Fletes Repartidores:</span>
            <span style="white-space: nowrap;">${{ number_format($deliveryFeesTotal, 0, ',', '.') }}</span>
        </div>
        @endif
        <div class="row-flex">
            <span>Para Llevar [{{ $takeoutSales->count() }}]:</span>
            <span class="fw-bold" style="white-space: nowrap;">${{ number_format($takeoutTotal, 0, ',', '.') }}</span>
        </div>
        <div class="row-flex">
            <span>Mostrador/Retail [{{ $retailSales->count() }}]:</span>
            <span class="fw-bold" style="white-space: nowrap;">${{ number_format($retailTotal, 0, ',', '.') }}</span>
        </div>

        <div class="ticket-divider-double"></div>

        {{-- Conciliación y Arqueo de Medios de Pago --}}
        <div class="text-center fw-bold fs-sm">--- ARQUEO MEDIOS DE PAGO ---</div>
        <div class="ticket-divider"></div>

        {{-- Efectivo en gaveta destacado --}}
        <div class="row-flex fw-bold">
            <span>EFECTIVO NETO EN GAVETA:</span>
            <span style="white-space: nowrap;">${{ number_format($netCashInDrawer, 0, ',', '.') }}</span>
        </div>
        <div class="row-flex fs-sm" style="padding-left: 4px; color: #000000;">
            <span>↳ Ingresos Efectivo:</span>
            <span style="white-space: nowrap;">+${{ number_format($grossCashInDrawer, 0, ',', '.') }}</span>
        </div>
        @if($expensesCashTotal > 0)
        <div class="row-flex fs-sm" style="padding-left: 4px; color: #000000;">
            <span>↳ Salidas Gastos Efec.:</span>
            <span style="white-space: nowrap;">-${{ number_format($expensesCashTotal, 0, ',', '.') }}</span>
        </div>
        @endif
        <div class="row-flex fs-sm" style="padding-left: 4px; color: #000000; font-size: 8.5px;">
            <span>↳ Recibido: ${{ number_format($cashReceived, 0, ',', '.') }}</span>
            <span>Vueltos: -${{ number_format($changeGiven, 0, ',', '.') }}</span>
        </div>

        <div class="ticket-divider"></div>
        <div class="row-flex">
            <span>Transf. (Nequi/Bancos):</span>
            <span class="fw-bold" style="white-space: nowrap;">${{ number_format($transferAmount, 0, ',', '.') }}</span>
        </div>
        <div class="row-flex">
            <span>Tarjetas (Datáfono):</span>
            <span class="fw-bold" style="white-space: nowrap;">${{ number_format($cardAmount, 0, ',', '.') }}</span>
        </div>
        @if($otherAmount > 0)
        <div class="row-flex">
            <span>Otros Métodos:</span>
            <span class="fw-bold" style="white-space: nowrap;">${{ number_format($otherAmount, 0, ',', '.') }}</span>
        </div>
        @endif

        @if($creditCollectionsTotal > 0)
        <div class="row-flex" style="background: #EEEEEE; padding: 2px;">
            <span>↳ Abonos Cartera:</span>
            <span class="fw-bold" style="white-space: nowrap;">+${{ number_format($creditCollectionsTotal, 0, ',', '.') }}</span>
        </div>
        <div class="fs-sm text-center" style="font-size: 8.5px; margin-bottom: 2px;">
            ({{ $creditCollectionsCount }} cobros recibidos hoy)
        </div>
        @endif

        <div class="ticket-divider"></div>
        <div class="row-flex fw-bold fs-lg">
            <span>TOTAL RECAUDADO:</span>
            <span style="white-space: nowrap;">${{ number_format($totalCollected, 0, ',', '.') }}</span>
        </div>
        <div class="fs-sm text-center" style="display: block; margin-top: 1px; font-size: 8.5px;">
            (Efectivo Físico + Dinero Digital)
        </div>

        <div class="ticket-divider-double"></div>

        {{-- Egresos y Gastos del Día --}}
        <div class="text-center fw-bold fs-sm">--- EGRESOS / GASTOS DEL DÍA ---</div>
        <div class="ticket-divider"></div>
        <div class="row-flex">
            <span>GASTOS PAGADOS EN EFECTIVO:</span>
            <span class="fw-bold" style="white-space: nowrap;">-${{ number_format($expensesCashTotal, 0, ',', '.') }}</span>
        </div>
        <div class="row-flex">
            <span>GASTOS DIGITALES/OTROS:</span>
            <span style="white-space: nowrap;">-${{ number_format($expensesTransferTotal + $expensesCardOtherTotal, 0, ',', '.') }}</span>
        </div>
        <div class="ticket-divider"></div>
        <div class="row-flex fw-bold">
            <span>TOTAL EGRESOS PAGADOS HOY:</span>
            <span style="white-space: nowrap;">-${{ number_format($totalExpensesPaidToday, 0, ',', '.') }}</span>
        </div>

        @if($paidExpenses->count() > 0)
        <div class="ticket-divider"></div>
        <div class="fs-sm fw-bold" style="margin-bottom: 2px;">DETALLE DE GASTOS PAGADOS:</div>
        @foreach($paidExpenses as $exp)
        <div class="row-flex fs-sm" style="font-size: 9px;">
            <span style="max-width: 32mm; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                • {{ $exp->invoice_number ? '#' . $exp->invoice_number . ' ' : '' }}{{ $exp->description ?: 'Gasto' }}
            </span>
            <span style="white-space: nowrap;">-${{ number_format($exp->amount, 0, ',', '.') }}</span>
        </div>
        @endforeach
        @endif

        @if($totalExpensesPendingToday > 0)
        <div class="row-flex fs-sm" style="margin-top: 2px; font-size: 8.5px;">
            <span>↳ Facturas Pendientes (CxP):</span>
            <span style="white-space: nowrap;">${{ number_format($totalExpensesPendingToday, 0, ',', '.') }}</span>
        </div>
        @endif

        <div class="ticket-divider"></div>
        <div class="row-flex fw-bold fs-lg">
            <span>BALANCE NETO DEL TURNO:</span>
            <span style="white-space: nowrap;">${{ number_format($netCashBalance, 0, ',', '.') }}</span>
        </div>
        <div class="fs-sm text-center" style="font-size: 8.5px;">
            (Total Recaudado - Total Egresos)
        </div>

        <div class="ticket-divider-double"></div>

        {{-- Firmas de Responsabilidad --}}
        <div class="signature-box fs-sm">
            <div style="margin-bottom: 18px;">
                <div>Firma Cajero: _____________________</div>
                <div style="font-size: 8.5px; margin-top: 2px;">C.C. / {{ auth()->user()->name ?? 'Cajero' }}</div>
            </div>
            <div>
                <div>Firma Administrador: ______________________</div>
                <div style="font-size: 8.5px; margin-top: 2px;">Verificación y Custodia Gaveta</div>
            </div>
        </div>

        <div class="ticket-divider"></div>
        <div class="ticket-footer fs-sm">
            <div>SABORCALIFIT POS v1.0</div>
            <div>Control Contable y Operativo</div>
        </div>
    </div>

    {{-- Auto-impresión térmica --}}
    <script>
    window.addEventListener('DOMContentLoaded', () => {
        setTimeout(() => {
            window.print();
        }, 350);
    });
    </script>
</body>
</html>
