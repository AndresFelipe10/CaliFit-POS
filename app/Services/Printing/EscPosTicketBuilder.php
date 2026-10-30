<?php

namespace App\Services\Printing;

use App\Models\RestaurantOrder;
use App\Models\Sale;
use Illuminate\Support\Collection;
use Mike42\Escpos\PrintConnectors\DummyPrintConnector;
use Mike42\Escpos\Printer;

class EscPosTicketBuilder
{
    public const LINE_WIDTH = 48;

    /**
     * Normaliza caracteres especiales y tildes para máxima compatibilidad ESC/POS.
     */
    public function sanitizeText(string $text): string
    {
        $replacements = [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U',
            'ñ' => 'n', 'Ñ' => 'N',
            '•' => '*', '↳' => '->', '✓' => 'OK', '–' => '-', '—' => '-',
        ];

        return strtr($text, $replacements);
    }

    /**
     * Formatea un valor monetario en formato colombiano COP ($XX.XXX).
     */
    public function formatMoney(float|int|string|null $amount): string
    {
        $val = (float) ($amount ?? 0);
        return '$' . number_format($val, 0, ',', '.');
    }

    /**
     * Alinea dos columnas a exactamente 48 caracteres (izquierda y derecha).
     */
    public function twoColumns(string $left, string $right, int $width = self::LINE_WIDTH): string
    {
        $left = $this->sanitizeText($left);
        $right = $this->sanitizeText($right);
        $rightLen = mb_strlen($right);
        $maxLeftLen = $width - $rightLen - 1;

        if ($maxLeftLen < 1) {
            return mb_substr($left, 0, $width) . "\n" . str_pad($right, $width, ' ', STR_PAD_LEFT) . "\n";
        }

        if (mb_strlen($left) > $maxLeftLen) {
            $left = mb_substr($left, 0, $maxLeftLen);
        }

        $spaces = $width - mb_strlen($left) - $rightLen;
        return $left . str_repeat(' ', max(1, $spaces)) . $right . "\n";
    }

    /**
     * Retorna una línea divisoria simple de 48 caracteres.
     */
    public function divider(string $char = '-'): string
    {
        return str_repeat($char, self::LINE_WIDTH) . "\n";
    }

    /**
     * Retorna una línea divisoria doble de 48 caracteres.
     */
    public function doubleDivider(): string
    {
        return str_repeat('=', self::LINE_WIDTH) . "\n";
    }

    /**
     * Imprime la cabecera oficial de SaborCaliFit Bochalema.
     */
    protected function printOfficialHeader(Printer $printer, ?string $documentTitle = null): void
    {
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setEmphasis(true);
        $printer->text("SABORCALIFIT | BOCHALEMA\n");
        $printer->setEmphasis(false);
        $printer->text("Arbolatta Mall - Cali, Valle\n");
        $printer->text("Cl. 48 #106-86, Bochalema\n");
        $printer->text("WhatsApp Pedidos: 312 770 4455\n");

        if ($documentTitle !== null) {
            $printer->feed(1);
            $printer->setEmphasis(true);
            $printer->text($this->sanitizeText($documentTitle) . "\n");
            $printer->setEmphasis(false);
        }

        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->text($this->divider());
    }

    /**
     * Genera ticket ESC/POS para Comanda de Cocina / Barra (80 mm).
     */
    public function comanda(RestaurantOrder $order, ?int $batchNumber = null, bool $isReprint = false): string
    {
        $connector = new DummyPrintConnector();
        $printer = new Printer($connector);

        // 1. Cabecera Comanda
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setEmphasis(true);
        $printer->setTextSize(1, 2); // Doble alto
        $printer->text("*** COMANDA COCINA / BARRA ***\n");
        $printer->setTextSize(1, 1);
        $printer->text("SABORCALIFIT | BOCHALEMA\n");

        if ($isReprint) {
            $printer->feed(1);
            $printer->setTextSize(1, 2);
            $printer->text("** COMANDA REIMPRESA **\n");
            $printer->setTextSize(1, 1);
        }
        $printer->setEmphasis(false);
        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->text($this->divider());

        // 2. Ubicación y Tanda en Grande
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setEmphasis(true);
        $printer->setTextSize(2, 2); // Doble alto y ancho

        $locationLabel = match ($order->order_type) {
            'delivery' => 'DOMICILIO',
            'takeout'  => 'PARA LLEVAR',
            default    => $order->table ? strtoupper($order->table->name) : 'SALON',
        };

        $printer->text($this->sanitizeText($locationLabel) . "\n");

        $batch = $batchNumber ?? $order->current_batch ?? 1;
        $printer->setTextSize(1, 2);
        $printer->text("TANDA #{$batch}\n");
        $printer->setTextSize(1, 1);
        $printer->setEmphasis(false);
        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->text($this->doubleDivider());

        // 3. Metadatos
        $orderNum = $order->order_number;
        $hora = now()->format('d/m/Y H:i:s');
        $mesero = $order->waiter_name ?: ($order->user->name ?? 'Personal de Servicio');
        $cliente = $order->customer_name;

        $printer->text("ORDEN:   {$orderNum}\n");
        $printer->text("HORA:    {$hora}\n");
        $printer->text("MESERO:  " . $this->sanitizeText($mesero) . "\n");
        if (!empty($cliente)) {
            $printer->text("CLIENTE: " . $this->sanitizeText($cliente) . "\n");
        }
        $printer->text($this->divider());

        // 4. Platos y Adiciones de la Tanda
        $printer->setEmphasis(true);
        $printer->text("CANT   DESCRIPCION PREPARACION\n");
        $printer->setEmphasis(false);
        $printer->text($this->divider('-'));

        $itemsQuery = $order->items();
        if ($batchNumber !== null) {
            $itemsQuery->where('batch_number', $batchNumber);
        }
        // Filtrar platos no cancelados
        $items = $itemsQuery->where('status', '!=', 'cancelled')
            ->with(['product', 'additions.addition'])
            ->get();

        foreach ($items as $item) {
            $qty = (float) $item->quantity;
            $dishName = $item->product->nombre ?? 'Plato';

            // Plato en doble alto y negrita
            $printer->setEmphasis(true);
            $printer->setTextSize(1, 2);
            $printer->text(sprintf("[ %g ]  %s\n", $qty, $this->sanitizeText($dishName)));
            $printer->setTextSize(1, 1);
            $printer->setEmphasis(false);

            // Adiciones
            if ($item->additions && $item->additions->count() > 0) {
                foreach ($item->additions as $add) {
                    $addQty = (float) $add->quantity;
                    $addName = $add->addition->nombre ?? $add->nombre ?? 'Adicion';
                    $printer->text("       + " . $this->sanitizeText($addName) . " (x{$addQty})\n");
                }
            }

            // Notas
            if (!empty($item->notes)) {
                $printer->text("       > NOTA: " . $this->sanitizeText($item->notes) . "\n");
            }
            $printer->feed(1);
        }

        $printer->text($this->doubleDivider());

        // 5. Pie
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setEmphasis(true);
        $printer->text("*** PREPARAR CON ALTO ESTANDAR FIT ***\n");
        $printer->setEmphasis(false);
        $printer->setJustification(Printer::JUSTIFY_LEFT);

        $printer->feed(3);
        $printer->cut();

        $bytes = $connector->getData();
        $printer->close();

        return base64_encode($bytes);
    }

    /**
     * Genera ticket ESC/POS para Factura / Comprobante de Venta (80 mm).
     */
    public function factura(Sale $sale): string
    {
        $connector = new DummyPrintConnector();
        $printer = new Printer($connector);

        // 1. Cabecera Oficial
        $this->printOfficialHeader($printer, "COMPROBANTE DE VENTA");

        $ro = $sale->restaurantOrder;
        $effectiveOrderType = $sale->order_type ?: ($ro ? $ro->order_type : 'pos');
        $canalLabel = match ($effectiveOrderType) {
            'delivery' => 'DOMICILIO',
            'takeout'  => 'PARA LLEVAR / RECOGER',
            'table'    => 'SALON / MESA',
            default    => 'MOSTRADOR POS',
        };

        $isPartialSplit = false;
        if ($effectiveOrderType === 'table' && $ro && $ro->isSplit()) {
            if (!str_starts_with((string) $sale->notes, 'Liquidación total')) {
                $isPartialSplit = true;
            }
        }

        // 2. Metadatos de la Venta
        $printer->text("COMPROBANTE: " . $sale->invoice_number . "\n");
        $printer->text("FECHA:       " . $sale->created_at->format('d/m/Y H:i:s') . "\n");
        $printer->text("CAJERO:      " . $this->sanitizeText($sale->user->name ?? 'Caja General') . "\n");
        if (!empty($sale->waiter_name)) {
            $printer->text("MESERO:      " . $this->sanitizeText($sale->waiter_name) . "\n");
        }
        $printer->text("CLIENTE:     " . $this->sanitizeText($sale->customer_name ?: 'Consumidor Final') . "\n");
        $printer->text("DOC/NIT:     " . ($sale->customer_document ?: '222222222222') . "\n");
        $printer->text("CANAL:       " . $canalLabel . "\n");

        if ($effectiveOrderType === 'delivery' && $ro) {
            $printer->text("ORDEN DOMICILIO: " . $ro->order_number . "\n");
            if (!empty($ro->address)) {
                $printer->text("DIRECCION:       " . $this->sanitizeText($ro->address) . "\n");
            }
            if (!empty($ro->phone)) {
                $printer->text("TELEFONO:        " . $this->sanitizeText($ro->phone) . "\n");
            }
        } elseif ($effectiveOrderType === 'takeout' && $ro) {
            $printer->text("ORDEN LLEVAR:    " . $ro->order_number . "\n");
            if (!empty($ro->phone)) {
                $printer->text("TELEFONO:        " . $this->sanitizeText($ro->phone) . "\n");
            }
        } elseif ($effectiveOrderType === 'table' && $ro) {
            $tName = $ro->table ? (str_starts_with(trim($ro->table->name), 'Mesa') ? $ro->table->name : 'Mesa ' . $ro->table->name) : 'Salon';
            $printer->text("COMANDA:         " . $ro->order_number . " - " . $this->sanitizeText($tName) . "\n");
        }

        if ($sale->is_credit) {
            $printer->feed(1);
            $printer->setEmphasis(true);
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->text("*** VENTA A CREDITO / FIADO ***\n");
            $stLabel = match ($sale->credit_status) {
                'paid'      => 'PAGADO',
                'cancelled' => 'ANULADO',
                default     => 'PENDIENTE',
            };
            $printer->text("ESTADO: {$stLabel}\n");
            if ($sale->credit_due_date) {
                $printer->text("VENCE: " . \Carbon\Carbon::parse($sale->credit_due_date)->format('d/m/Y') . "\n");
            }
            $printer->setEmphasis(false);
            $printer->setJustification(Printer::JUSTIFY_LEFT);
        }

        if ($isPartialSplit && $ro) {
            $printer->feed(1);
            $printer->setEmphasis(true);
            $printer->setJustification(Printer::JUSTIFY_CENTER);
            $printer->text("*** COMPROBANTE DE PAGO PARCIAL ***\n");
            $printer->text("COBRO PARCIAL COMANDA: " . $ro->order_number . "\n");
            $printer->setEmphasis(false);
            $printer->setJustification(Printer::JUSTIFY_LEFT);
        }

        $printer->text($this->divider());

        // 3. Tabla de Productos y Adiciones
        $printer->setEmphasis(true);
        // Formato columnas: CANT(5) DESCRIPCION(21) VAL.U(11) TOTAL(11) = 48
        $printer->text(sprintf("%-5s %-20s %10s %10s\n", "CANT", "DESCRIPCION", "VAL.U", "TOTAL"));
        $printer->setEmphasis(false);
        $printer->text($this->divider('-'));

        $sale->loadMissing(['details.product', 'details.additions.addition', 'payments']);

        foreach ($sale->details as $det) {
            $qty = (float) $det->quantity;
            $name = $this->sanitizeText($det->product->nombre ?? 'Producto');
            $unitPrice = $this->formatMoney($det->unit_price);
            $subtotal = $this->formatMoney($det->subtotal);

            // Si el nombre supera 20 caracteres, imprimir truncado o en línea propia
            if (mb_strlen($name) > 20) {
                $printer->text(sprintf("%-5s %s\n", (string)$qty, $name));
                $printer->text(sprintf("%-26s %10s %10s\n", "", $unitPrice, $subtotal));
            } else {
                $printer->text(sprintf("%-5s %-20s %10s %10s\n", (string)$qty, $name, $unitPrice, $subtotal));
            }

            // Adiciones
            if ($det->additions && $det->additions->count() > 0) {
                foreach ($det->additions as $add) {
                    $addQty = (float) $add->quantity;
                    $addName = "+ " . $this->sanitizeText($add->addition->nombre ?? 'Adicion') . " (x{$addQty})";
                    $addSubtotal = "+" . $this->formatMoney($add->subtotal);
                    $printer->text($this->twoColumns("   " . $addName, $addSubtotal));
                }
            }
        }

        $printer->text($this->divider());

        // 4. Totales y Desglose
        $printer->text($this->twoColumns("Subtotal Productos:", $this->formatMoney($sale->subtotal)));

        if ((float)($sale->discount ?? 0) > 0) {
            $printer->text($this->twoColumns("Descuento (" . round($sale->discount_percentage) . "%):", "-" . $this->formatMoney($sale->discount)));
        }

        if ((float)($sale->delivery_fee ?? 0) > 0) {
            $printer->text($this->twoColumns("Flete Domicilio:", $this->formatMoney($sale->delivery_fee)));
        }

        if ((float)($sale->tax_inc ?? 0) > 0) {
            $printer->text($this->twoColumns("Imp. Consumo (INC 8%):", $this->formatMoney($sale->tax_inc)));
        }

        if ((float)($sale->tip ?? 0) > 0) {
            $printer->text($this->twoColumns("Propina Voluntaria:", $this->formatMoney($sale->tip)));
        }

        $printer->text($this->divider('='));

        // TOTAL A PAGAR EN GRANDE
        $printer->setEmphasis(true);
        $printer->setTextSize(1, 2);
        $printer->text($this->twoColumns("TOTAL A PAGAR:", $this->formatMoney($sale->total)));
        $printer->setTextSize(1, 1);
        $printer->setEmphasis(false);
        $printer->text($this->divider('='));

        // 5. Formas de Pago
        $printer->setEmphasis(true);
        $printer->text("FORMAS DE PAGO:\n");
        $printer->setEmphasis(false);

        foreach ($sale->payments as $p) {
            $methodName = match ($p->payment_method) {
                'cash'          => 'Efectivo',
                'nequi'         => 'Nequi',
                'daviplata'     => 'Daviplata',
                'transfer'      => 'Transferencia',
                'card'          => 'Tarjeta / Datafono',
                'credit'        => 'Credito / Fiado',
                default         => ucfirst($p->payment_method),
            };
            $printer->text($this->twoColumns("  " . $methodName . ":", $this->formatMoney($p->amount)));

            if ($p->payment_method === 'cash' && (float)$p->cash_received > 0) {
                $printer->text($this->twoColumns("    Recibido:", $this->formatMoney($p->cash_received)));
                $printer->text($this->twoColumns("    Cambio / Vueltos:", $this->formatMoney($p->change_given)));
            }
        }

        $printer->text($this->divider());

        // 6. Leyenda Legal y Pie
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->text("Comprobante interno de venta.\n");
        $printer->text("No reemplaza la factura electronica DIAN.\n\n");
        $printer->setEmphasis(true);
        $printer->text("!GRACIAS POR TU COMPRA FIT!\n");
        $printer->setEmphasis(false);
        $printer->text("@saborcalifit.bochalema\n");
        $printer->setJustification(Printer::JUSTIFY_LEFT);

        $printer->feed(3);
        $printer->cut();

        $bytes = $connector->getData();
        $printer->close();

        return base64_encode($bytes);
    }

    /**
     * Genera ticket ESC/POS para Tirilla de Despacho (Domicilio o Para Llevar, 80 mm).
     */
    public function despacho(RestaurantOrder $order): string
    {
        $connector = new DummyPrintConnector();
        $printer = new Printer($connector);

        $isDelivery = $order->order_type === 'delivery';
        $title = $isDelivery ? "*** TIRILLA DE DESPACHO / DOMICILIO ***" : "*** TIRILLA DE DESPACHO / PARA LLEVAR ***";

        // 1. Cabecera Oficial
        $this->printOfficialHeader($printer, $title);

        $canalLabel = $isDelivery ? 'DOMICILIO' : 'PARA LLEVAR';
        $printer->text("ORDEN:          " . $order->order_number . "\n");
        $printer->text("CANAL:          " . $canalLabel . "\n");
        $printer->text("FECHA / HORA:   " . $order->created_at->format('d/m/Y H:i:s') . "\n");
        $printer->text("DESPACHADO POR: " . $this->sanitizeText($order->user->name ?? 'Caja') . "\n");
        $printer->text($this->divider());

        // 2. Datos del Destinatario
        $printer->setEmphasis(true);
        $printer->text("DATOS DEL DESTINATARIO:\n");
        $printer->setEmphasis(false);
        $printer->text("CLIENTE:   " . $this->sanitizeText($order->customer_name ?: 'Consumidor Final') . "\n");
        if (!empty($order->phone)) {
            $printer->text("TELEFONO:  " . $this->sanitizeText($order->phone) . "\n");
        }
        if ($isDelivery) {
            $printer->text("DIRECCION: " . $this->sanitizeText($order->address ?: 'No especificada') . "\n");
        } else {
            $printer->text("MODALIDAD: Recoge en Local\n");
        }
        $cleanNotes = $order->clean_notes ?: $order->notes;
        if (!empty($cleanNotes)) {
            $printer->text("INSTRUCCIONES: " . $this->sanitizeText($cleanNotes) . "\n");
        }
        $printer->text($this->divider());

        // 3. Productos Empacados
        $printer->setEmphasis(true);
        $printer->text("PRODUCTOS EMPACADOS:\n");
        $printer->text(sprintf("%-6s %-28s %12s\n", "CANT", "DESCRIPCION", "VALOR"));
        $printer->setEmphasis(false);
        $printer->text($this->divider('-'));

        $order->loadMissing(['items.product', 'items.additions.addition']);

        foreach ($order->items as $item) {
            $qty = (float) $item->quantity;
            $dishName = $this->sanitizeText($item->product->nombre ?? 'Producto');
            $dishBasePrice = round($qty * (float)$item->unit_price, 2);

            if (mb_strlen($dishName) > 28) {
                $printer->text(sprintf("[ %g ] %s\n", $qty, $dishName));
                $printer->text(sprintf("%-35s %12s\n", "", $this->formatMoney($dishBasePrice)));
            } else {
                $printer->text(sprintf("[ %g ] %-27s %12s\n", $qty, $dishName, $this->formatMoney($dishBasePrice)));
            }

            if ($item->additions && $item->additions->count() > 0) {
                foreach ($item->additions as $add) {
                    $addQty = (float) $add->quantity;
                    $addName = "+ " . $this->sanitizeText($add->addition->nombre ?? $add->nombre ?? 'Adicion') . " (x{$addQty})";
                    $printer->text($this->twoColumns("   " . $addName, "+" . $this->formatMoney($add->subtotal)));
                }
            }

            if (!empty($item->notes)) {
                $printer->text("   > Nota: " . $this->sanitizeText($item->notes) . "\n");
            }
        }

        $printer->text($this->divider());

        // 4. Cobro Contraentrega
        $printer->setEmphasis(true);
        $printer->text("COBRO CONTRAENTREGA:\n");
        $printer->setEmphasis(false);
        $printer->text($this->twoColumns("VALOR PRODUCTOS:", $this->formatMoney($order->subtotal)));

        if ($isDelivery) {
            $printer->text($this->twoColumns("FLETE DOMICILIO:", $this->formatMoney($order->delivery_fee ?? 0)));
        }

        $printer->text($this->divider('='));
        $printer->setEmphasis(true);
        $printer->setTextSize(1, 2);
        $printer->text($this->twoColumns("TOTAL A COBRAR:", $this->formatMoney($order->total)));
        $printer->setTextSize(1, 1);
        $printer->setEmphasis(false);
        $printer->text($this->divider('='));

        // 5. Pie
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setEmphasis(true);
        $printer->text("!Que disfrutes tu comida saludable!\n");
        $printer->setEmphasis(false);
        $printer->setJustification(Printer::JUSTIFY_LEFT);

        $printer->feed(3);
        $printer->cut();

        $bytes = $connector->getData();
        $printer->close();

        return base64_encode($bytes);
    }

    /**
     * Genera ticket ESC/POS para Pre-cuenta (Mesa o Parcial, 80 mm).
     */
    public function precuenta(RestaurantOrder $order, ?Collection $selectedItems = null, array $extraData = []): string
    {
        $connector = new DummyPrintConnector();
        $printer = new Printer($connector);

        $isPartial = $selectedItems !== null && $selectedItems->isNotEmpty();
        $docTitle = $isPartial
            ? "*** PRE-CUENTA PARCIAL (CONSUMO INDIVIDUAL) ***"
            : "*** PRE-CUENTA INFORMATIVA ***";

        // 1. Cabecera Oficial
        $this->printOfficialHeader($printer, $docTitle);

        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setEmphasis(true);
        $printer->text("DOCUMENTO INFORMATIVO DE REVISION\n");
        $printer->text("NO VALIDO COMO FACTURA O COMPROBANTE\n");
        $printer->setEmphasis(false);
        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->text($this->divider());

        // 2. Metadatos
        $tableName = $order->table ? (str_starts_with(trim($order->table->name), 'Mesa') ? $order->table->name : 'Mesa ' . $order->table->name) : 'Salon';
        $printer->text("ORDEN:      " . $order->order_number . " - " . $this->sanitizeText($tableName) . "\n");
        $printer->text("FECHA/HORA: " . now()->format('d/m/Y H:i:s') . "\n");
        $printer->text("MESERO:     " . $this->sanitizeText($order->waiter_name ?: ($order->user->name ?? 'Personal')) . "\n");
        if (!$isPartial) {
            $printer->text("COMENSALES: " . ($order->guest_count ?? 1) . "\n");
        }
        $printer->text($this->divider());

        // 3. Ítems
        $printer->setEmphasis(true);
        $printer->text(sprintf("%-5s %-20s %10s %10s\n", "CANT", "DETALLE", "VAL.U", "TOTAL"));
        $printer->setEmphasis(false);
        $printer->text($this->divider('-'));

        $itemsToPrint = $isPartial ? $selectedItems : $order->items;
        $subtotal = 0.0;

        foreach ($itemsToPrint as $item) {
            $qty = (float) $item->quantity;
            $name = $this->sanitizeText($item->product->nombre ?? 'Plato');
            $unitPrice = (float) $item->unit_price;
            $itemBaseTotal = round($qty * $unitPrice, 2);
            $subtotal += $itemBaseTotal;

            if (mb_strlen($name) > 20) {
                $printer->text(sprintf("%-5s %s\n", (string)$qty, $name));
                $printer->text(sprintf("%-26s %10s %10s\n", "", $this->formatMoney($unitPrice), $this->formatMoney($itemBaseTotal)));
            } else {
                $printer->text(sprintf("%-5s %-20s %10s %10s\n", (string)$qty, $name, $this->formatMoney($unitPrice), $this->formatMoney($itemBaseTotal)));
            }

            if ($item->additions && $item->additions->count() > 0) {
                foreach ($item->additions as $add) {
                    $addQty = (float) $add->quantity;
                    $addSubtotal = (float) $add->subtotal;
                    $subtotal += $addSubtotal;
                    $addName = "+ " . $this->sanitizeText($add->addition->nombre ?? $add->nombre ?? 'Adicion') . " (x{$addQty})";
                    $printer->text($this->twoColumns("   " . $addName, "+" . $this->formatMoney($addSubtotal)));
                }
            }
        }

        $printer->text($this->divider());

        // 4. Totales y Propinas
        if ($isPartial) {
            $printer->text($this->twoColumns("Subtotal Seleccionado:", $this->formatMoney($subtotal)));
        } else {
            $printer->text($this->twoColumns("Subtotal Alimentos:", $this->formatMoney($subtotal)));
        }

        if (!$isPartial && $order->order_type === 'delivery') {
            $delFee = (float)($order->delivery_fee ?? 0);
            $printer->text($this->twoColumns("Flete Domicilio:", $this->formatMoney($delFee)));
            $printer->text($this->divider('='));
            $printer->setEmphasis(true);
            $printer->setTextSize(1, 2);
            $printer->text($this->twoColumns("TOTAL A PAGAR:", $this->formatMoney($subtotal + $delFee)));
            $printer->setTextSize(1, 1);
            $printer->setEmphasis(false);
        } elseif ($order->order_type === 'table') {
            $suggestedTip = round($subtotal * 0.10, 2);
            $totalConPropina = $subtotal + $suggestedTip;

            $printer->text($this->twoColumns("Propina Sugerida (10%):", $this->formatMoney($suggestedTip)));
            $printer->text($this->divider('='));
            $printer->setEmphasis(true);
            $printer->setTextSize(1, 2);
            $printer->text($this->twoColumns("TOTAL CON PROPINA:", $this->formatMoney($totalConPropina)));
            $printer->setTextSize(1, 1);
            $printer->setEmphasis(false);
            $printer->text($this->twoColumns("TOTAL SIN PROPINA:", $this->formatMoney($subtotal)));
        } else {
            $printer->text($this->divider('='));
            $printer->setEmphasis(true);
            $printer->setTextSize(1, 2);
            $printer->text($this->twoColumns("TOTAL:", $this->formatMoney($subtotal)));
            $printer->setTextSize(1, 1);
            $printer->setEmphasis(false);
        }

        $printer->text($this->divider());

        // 5. Pie
        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setEmphasis(true);
        $printer->text("!Gracias por preferir a SaborCaliFit!\n");
        $printer->setEmphasis(false);
        $printer->setJustification(Printer::JUSTIFY_LEFT);

        $printer->feed(3);
        $printer->cut();

        $bytes = $connector->getData();
        $printer->close();

        return base64_encode($bytes);
    }

    /**
     * Genera ticket ESC/POS para el Cuadre de Caja Diario y Arqueo (80 mm).
     */
    public function cuadreCaja(array $summaryData): string
    {
        $connector = new DummyPrintConnector();
        $printer = new Printer($connector);

        // 1. Cabecera Oficial
        $this->printOfficialHeader($printer, "*** CUADRE DE CAJA DIARIO ***");

        $date = $summaryData['date'] ?? now()->format('Y-m-d');
        $printer->text("FECHA DE ARQUEO: " . $date . "\n");
        $printer->text("GENERADO:        " . now()->format('d/m/Y H:i:s') . "\n");
        $printer->text("RESPONSABLE:     " . $this->sanitizeText($summaryData['responsible'] ?? auth()->user()?->name ?? 'Administrador') . "\n");
        $printer->text($this->divider());

        // 2. Resumen General de Facturación
        $printer->setEmphasis(true);
        $printer->text("RESUMEN GENERAL DE VENTAS:\n");
        $printer->setEmphasis(false);

        $grossSales = $summaryData['grossSales'] ?? $summaryData['grossSubtotal'] ?? 0;
        $discounts = $summaryData['discounts'] ?? 0;
        $serviceTips = $summaryData['serviceTips'] ?? 0;
        $taxInc = $summaryData['taxInc'] ?? 0;
        $deliveryFees = $summaryData['deliveryFees'] ?? 0;
        $netTotalBilled = $summaryData['netTotalBilled'] ?? 0;
        $creditSalesTotal = $summaryData['creditSalesTotal'] ?? 0;

        $printer->text($this->twoColumns("Ventas Brutas:", $this->formatMoney($grossSales)));
        if ($discounts > 0) {
            $printer->text($this->twoColumns("(-) Descuentos:", "-" . $this->formatMoney($discounts)));
        }
        if ($serviceTips > 0) {
            $printer->text($this->twoColumns("(+) Propinas/Servicio:", "+" . $this->formatMoney($serviceTips)));
        }
        $printer->text($this->twoColumns("Imp. Consumo (INC 8%):", $this->formatMoney($taxInc)));
        $printer->text($this->twoColumns("Total Fletes Domicilio:", $this->formatMoney($deliveryFees)));
        $printer->text($this->divider('-'));
        $printer->setEmphasis(true);
        $printer->setTextSize(1, 2);
        $printer->text($this->twoColumns("TOTAL FACTURADO:", $this->formatMoney($netTotalBilled)));
        $printer->setTextSize(1, 1);
        $printer->setEmphasis(false);

        if ($creditSalesTotal > 0) {
            $printer->text($this->twoColumns("-> Creditos (Fiados):", $this->formatMoney($creditSalesTotal)));
            $printer->text("  (Cartera por cobrar - No ingreso a caja)\n");
        }

        $printer->text($this->doubleDivider());

        // 3. Canales de Venta
        $printer->setEmphasis(true);
        $printer->text("--- CANALES DE VENTA ---\n");
        $printer->setEmphasis(false);

        $tableTotal = $summaryData['tableTotal'] ?? 0;
        $tableCount = $summaryData['tableCount'] ?? 0;
        $deliveryTotal = $summaryData['deliveryTotal'] ?? 0;
        $deliveryCount = $summaryData['deliveryCount'] ?? 0;
        $takeoutTotal = $summaryData['takeoutTotal'] ?? 0;
        $takeoutCount = $summaryData['takeoutCount'] ?? 0;
        $retailTotal = $summaryData['retailTotal'] ?? 0;
        $retailCount = $summaryData['retailCount'] ?? 0;

        $printer->text($this->twoColumns("Salon [{$tableCount}]:", $this->formatMoney($tableTotal)));
        $printer->text($this->twoColumns("Domicilios [{$deliveryCount}]:", $this->formatMoney($deliveryTotal)));
        $printer->text($this->twoColumns("Para Llevar [{$takeoutCount}]:", $this->formatMoney($takeoutTotal)));
        $printer->text($this->twoColumns("Mostrador POS [{$retailCount}]:", $this->formatMoney($retailTotal)));

        $printer->text($this->doubleDivider());

        // 4. Arqueo y Medios de Pago
        $printer->setEmphasis(true);
        $printer->text("--- ARQUEO MEDIOS DE PAGO ---\n");
        $printer->setEmphasis(false);

        $netCashInDrawer = $summaryData['netCashInDrawer'] ?? 0;
        $grossCashInDrawer = $summaryData['grossCashInDrawer'] ?? 0;
        $expensesCashTotal = $summaryData['expensesCashTotal'] ?? 0;
        $cashReceived = $summaryData['cashReceived'] ?? 0;
        $changeGiven = $summaryData['changeGiven'] ?? 0;

        $transferAmount = $summaryData['transferAmount'] ?? 0;
        $cardAmount = $summaryData['cardAmount'] ?? 0;
        $otherAmount = $summaryData['otherAmount'] ?? 0;

        $printer->setEmphasis(true);
        $printer->text($this->twoColumns("EFECTIVO NETO GAVETA:", $this->formatMoney($netCashInDrawer)));
        $printer->setEmphasis(false);
        $printer->text($this->twoColumns("  + Ingresos Efectivo:", $this->formatMoney($grossCashInDrawer)));
        if ($expensesCashTotal > 0) {
            $printer->text($this->twoColumns("  - Salidas Gastos Efec:", "-" . $this->formatMoney($expensesCashTotal)));
        }
        $printer->text($this->twoColumns("  Recibido:", $this->formatMoney($cashReceived)));
        $printer->text($this->twoColumns("  Vueltos entregados:", "-" . $this->formatMoney($changeGiven)));
        $printer->text($this->divider('-'));

        $printer->text($this->twoColumns("Transf. (Nequi/Bancos):", $this->formatMoney($transferAmount)));
        $printer->text($this->twoColumns("Tarjetas (Datafono):", $this->formatMoney($cardAmount)));
        if ($otherAmount > 0) {
            $printer->text($this->twoColumns("Otros Metodos:", $this->formatMoney($otherAmount)));
        }

        $printer->text($this->doubleDivider());

        // 5. Firmas de Cierre
        $printer->feed(2);
        $printer->text($this->twoColumns("____________________", "____________________"));
        $printer->text($this->twoColumns("   Firma Cajero     ", "   Firma Auditor    "));

        $printer->feed(3);
        $printer->cut();

        $bytes = $connector->getData();
        $printer->close();

        return base64_encode($bytes);
    }
}

