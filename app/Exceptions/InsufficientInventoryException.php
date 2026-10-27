<?php

namespace App\Exceptions;

class InsufficientInventoryException extends InsufficientStockException
{
    public function __construct(
        protected string $productName,
        float $availableStock,
        float $requiredQuantity,
        protected string $unit = 'g',
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        $missing = round($requiredQuantity - $availableStock, 3);
        $formattedAvail = $this->formatNumber($availableStock);
        $formattedReq = $this->formatNumber($requiredQuantity);
        $formattedMissing = $this->formatNumber($missing);

        if (empty($message)) {
            $message = "Inventario insuficiente para el ingrediente/producto '{$productName}'. "
                . "Stock actual: {$formattedAvail} {$unit}, Requerido: {$formattedReq} {$unit} (Faltante: {$formattedMissing} {$unit}).";
        }

        parent::__construct($message, $availableStock, $requiredQuantity, $code, $previous);
    }

    public function getProductName(): string
    {
        return $this->productName;
    }

    public function getAvailableStock(): float
    {
        return (float) $this->availableStock;
    }

    public function getRequiredQuantity(): float
    {
        return (float) $this->requestedQuantity;
    }

    public function getMissingQuantity(): float
    {
        return round((float) $this->requestedQuantity - (float) $this->availableStock, 3);
    }

    public function getUnit(): string
    {
        return $this->unit;
    }

    private function formatNumber(float $number): string
    {
        return (float) $number == (int) $number
            ? (string) (int) $number
            : rtrim(rtrim(number_format($number, 3, '.', ''), '0'), '.');
    }
}
