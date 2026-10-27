<?php

namespace App\Traits;

trait NormalizesCurrencyInputs
{
    /**
     * Normaliza un valor monetario eliminando símbolos, separadores de miles (puntos)
     * y convirtiendo comas decimales a formato numérico estándar (float).
     */
    protected function normalizeCurrency($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $clean = trim((string) $value);
        // Remover símbolos de moneda y espacios
        $clean = preg_replace('/[^\d.,-]/', '', $clean);

        if ($clean === '' || $clean === '-') {
            return null;
        }

        // Si contiene coma, es el separador decimal en Colombia (ej: 140.000,50 o 140,50)
        if (str_contains($clean, ',')) {
            $clean = str_replace('.', '', $clean);
            $clean = str_replace(',', '.', $clean);
        } else {
            // Si solo tiene puntos: en Colombia son separadores de miles (ej: 140.000 o 1.500.000)
            if (substr_count($clean, '.') > 1 || preg_match('/\.\d{3}$/', $clean)) {
                $clean = str_replace('.', '', $clean);
            }
        }

        return is_numeric($clean) ? (float) $clean : null;
    }
}
