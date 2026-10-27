<?php

namespace App\AI\Enums;

class AiIntent
{
    // Clientes
    public const LIST_CUSTOMERS = 'list_customers';
    public const COUNT_CUSTOMERS = 'count_customers';

    // Productos y Catálogo
    public const LIST_PRODUCTS = 'list_products';
    public const TOP_SELLING_PRODUCTS = 'top_selling_products';

    // Inventario e Insumos (CaliFit Específicos)
    public const LOW_STOCK_INGREDIENTS = 'low_stock_ingredients';
    public const OUT_OF_STOCK_INGREDIENTS = 'out_of_stock_ingredients';
    public const LOW_STOCK_SUPPLEMENTS = 'low_stock_supplements';
    public const OUT_OF_STOCK_SUPPLEMENTS = 'out_of_stock_supplements';
    public const LOW_STOCK_PRODUCTS = 'low_stock_products';
    public const OUT_OF_STOCK_PRODUCTS = 'out_of_stock_products';
    public const INVENTORY_SUMMARY = 'inventory_summary';

    // Ventas y Facturación
    public const SALES_SUMMARY = 'sales_summary';
    public const SALES_BY_PERIOD = 'sales_by_period';
    public const SALES_BY_CHANNEL = 'sales_by_channel';

    // Operación de Restaurante y Caja
    public const ACTIVE_ORDERS = 'active_orders';
    public const DAILY_CASH_SUMMARY = 'daily_cash_summary';

    /**
     * Complete immutable whitelist of authorized read-only intents for SaborCaliFit.
     */
    public const ALL = [
        self::LIST_CUSTOMERS,
        self::COUNT_CUSTOMERS,
        self::LIST_PRODUCTS,
        self::TOP_SELLING_PRODUCTS,
        self::LOW_STOCK_INGREDIENTS,
        self::OUT_OF_STOCK_INGREDIENTS,
        self::LOW_STOCK_SUPPLEMENTS,
        self::OUT_OF_STOCK_SUPPLEMENTS,
        self::LOW_STOCK_PRODUCTS,
        self::OUT_OF_STOCK_PRODUCTS,
        self::INVENTORY_SUMMARY,
        self::SALES_SUMMARY,
        self::SALES_BY_PERIOD,
        self::SALES_BY_CHANNEL,
        self::ACTIVE_ORDERS,
        self::DAILY_CASH_SUMMARY,
    ];

    /**
     * Check if an intent is in the authorized whitelist.
     */
    public static function isValid(?string $intent): bool
    {
        return in_array($intent, self::ALL, true);
    }

    /**
     * Function declarations and schemas for LLM tool use / function calling (Gemini).
     */
    public static function getFunctionDeclarations(): array
    {
        return [
            [
                'name' => self::LOW_STOCK_INGREDIENTS,
                'description' => 'Consultar insumos de cocina y materias primas con existencias en o bajo el stock mínimo de seguridad (en gramos o mililitros).',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => (object)[],
                ],
            ],
            [
                'name' => self::OUT_OF_STOCK_INGREDIENTS,
                'description' => 'Consultar insumos de cocina completamente agotados (stock 0 en gramos o mililitros).',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => (object)[],
                ],
            ],
            [
                'name' => self::LOW_STOCK_SUPPLEMENTS,
                'description' => 'Consultar suplementos deportivos con stock bajo o igual al mínimo de seguridad (en unidades).',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => (object)[],
                ],
            ],
            [
                'name' => self::OUT_OF_STOCK_SUPPLEMENTS,
                'description' => 'Consultar suplementos deportivos agotados (stock 0 unidades).',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => (object)[],
                ],
            ],
            [
                'name' => self::LOW_STOCK_PRODUCTS,
                'description' => 'Consultar productos generales (insumos o suplementos) con existencias iguales o inferiores al stock mínimo de seguridad.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => (object)[],
                ],
            ],
            [
                'name' => self::OUT_OF_STOCK_PRODUCTS,
                'description' => 'Consultar productos generales (insumos o suplementos) completamente agotados (stock 0).',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => (object)[],
                ],
            ],
            [
                'name' => self::INVENTORY_SUMMARY,
                'description' => 'Obtener la valoración estimada total del inventario, conteo de insumos, suplementos y platos.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => (object)[],
                ],
            ],
            [
                'name' => self::SALES_SUMMARY,
                'description' => 'Obtener el resumen financiero de ventas (total facturado, número de transacciones y ticket promedio) de hoy o del mes actual.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'period' => [
                            'type' => 'STRING',
                            'description' => 'Período: "today" para ventas de hoy o "month" para el mes actual',
                        ],
                    ],
                ],
            ],
            [
                'name' => self::SALES_BY_PERIOD,
                'description' => 'Consultar ventas en un rango de fechas o período específico.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'date_from' => ['type' => 'STRING', 'description' => 'Fecha inicio (YYYY-MM-DD)'],
                        'date_to' => ['type' => 'STRING', 'description' => 'Fecha fin (YYYY-MM-DD)'],
                    ],
                ],
            ],
            [
                'name' => self::SALES_BY_CHANNEL,
                'description' => 'Consultar ventas discriminadas por canal de atención: salón (mesas), domicilios, para llevar o mostrador POS.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'channel' => [
                            'type' => 'STRING',
                            'description' => 'Canal opcional: "table" (salón), "delivery" (domicilios), "takeout" (para llevar) o "all"',
                        ],
                    ],
                ],
            ],
            [
                'name' => self::TOP_SELLING_PRODUCTS,
                'description' => 'Obtener el ranking de platos fitness y suplementos deportivos más vendidos.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'limit' => ['type' => 'INTEGER', 'description' => 'Cantidad de productos en el ranking (por defecto 5)'],
                    ],
                ],
            ],
            [
                'name' => self::ACTIVE_ORDERS,
                'description' => 'Consultar comandas y órdenes abiertas en el salón de mesas y pedidos activos en despacho.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => (object)[],
                ],
            ],
            [
                'name' => self::DAILY_CASH_SUMMARY,
                'description' => 'Obtener el resumen del cuadre de caja de la jornada actual discriminado por métodos de pago.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => (object)[],
                ],
            ],
            [
                'name' => self::LIST_CUSTOMERS,
                'description' => 'Listar clientes registrados en el negocio (excluyendo datos sensibles). Permite buscar por nombre.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'search' => ['type' => 'STRING', 'description' => 'Término de búsqueda de cliente (opcional)'],
                    ],
                ],
            ],
            [
                'name' => self::COUNT_CUSTOMERS,
                'description' => 'Obtener la cantidad total de clientes registrados activos en SaborCaliFit.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => (object)[],
                ],
            ],
            [
                'name' => self::LIST_PRODUCTS,
                'description' => 'Listar productos del catálogo de SaborCaliFit con sus precios y categorías.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'search' => ['type' => 'STRING', 'description' => 'Buscar por nombre o SKU de producto (opcional)'],
                    ],
                ],
            ],
        ];
    }
}
