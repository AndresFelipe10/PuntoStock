<?php

namespace App\AI\Enums;

class AiIntent
{
    public const LIST_CUSTOMERS = 'list_customers';
    public const COUNT_CUSTOMERS = 'count_customers';
    public const LIST_PRODUCTS = 'list_products';
    public const LOW_STOCK_PRODUCTS = 'low_stock_products';
    public const OUT_OF_STOCK_PRODUCTS = 'out_of_stock_products';
    public const TOP_SELLING_PRODUCTS = 'top_selling_products';
    public const SALES_SUMMARY = 'sales_summary';
    public const SALES_BY_PERIOD = 'sales_by_period';
    public const INVENTORY_SUMMARY = 'inventory_summary';
    public const LIST_SUPPLIERS = 'list_suppliers';
    public const EXPENSES_SUMMARY = 'expenses_summary';
    public const RESTAURANT_TABLES_STATUS = 'restaurant_tables_status';
    public const RESTAURANT_ORDERS_OPEN = 'restaurant_orders_open';
    public const RESTAURANT_DELIVERIES_TODAY = 'restaurant_deliveries_today';
    public const RESTAURANT_TIPS_COLLECTED = 'restaurant_tips_collected';
    public const SALES_COMPARISON = 'sales_comparison';
    public const PRODUCT_MARGINS = 'product_margins';

    /**
     * Complete immutable whitelist of authorized intents.
     */
    public const ALL = [
        self::LIST_CUSTOMERS,
        self::COUNT_CUSTOMERS,
        self::LIST_PRODUCTS,
        self::LOW_STOCK_PRODUCTS,
        self::OUT_OF_STOCK_PRODUCTS,
        self::TOP_SELLING_PRODUCTS,
        self::SALES_SUMMARY,
        self::SALES_BY_PERIOD,
        self::INVENTORY_SUMMARY,
        self::LIST_SUPPLIERS,
        self::EXPENSES_SUMMARY,
        self::RESTAURANT_TABLES_STATUS,
        self::RESTAURANT_ORDERS_OPEN,
        self::RESTAURANT_DELIVERIES_TODAY,
        self::RESTAURANT_TIPS_COLLECTED,
        self::SALES_COMPARISON,
        self::PRODUCT_MARGINS,
    ];

    /**
     * Check if an intent is in the authorized whitelist.
     */
    public static function isValid(?string $intent): bool
    {
        return in_array($intent, self::ALL, true);
    }

    /**
     * Function declarations and schemas for LLM tool use / function calling.
     */
    public static function getFunctionDeclarations(): array
    {
        return [
            [
                'name' => self::LIST_CUSTOMERS,
                'description' => 'Listar clientes registrados en el negocio. Permite buscar por nombre.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'search' => ['type' => 'STRING', 'description' => 'Término de búsqueda de cliente (opcional)'],
                    ],
                ],
            ],
            [
                'name' => self::COUNT_CUSTOMERS,
                'description' => 'Obtener la cantidad total de clientes registrados en el emprendimiento.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => (object)[],
                ],
            ],
            [
                'name' => self::LIST_PRODUCTS,
                'description' => 'Listar productos del catálogo, precios y stock.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'search' => ['type' => 'STRING', 'description' => 'Buscar por nombre o SKU de producto (opcional)'],
                    ],
                ],
            ],
            [
                'name' => self::LOW_STOCK_PRODUCTS,
                'description' => 'Listar productos que tienen stock bajo o igual al stock mínimo de seguridad.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => (object)[],
                ],
            ],
            [
                'name' => self::OUT_OF_STOCK_PRODUCTS,
                'description' => 'Listar productos que están completamente agotados (stock 0 o menor).',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => (object)[],
                ],
            ],
            [
                'name' => self::TOP_SELLING_PRODUCTS,
                'description' => 'Obtener el ranking de los productos más vendidos y su volumen.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'limit' => ['type' => 'INTEGER', 'description' => 'Cantidad de productos en el top (por defecto 5)'],
                    ],
                ],
            ],
            [
                'name' => self::SALES_SUMMARY,
                'description' => 'Obtener el resumen financiero de ventas de hoy o del mes actual.',
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
                'name' => self::INVENTORY_SUMMARY,
                'description' => 'Obtener el valor total del inventario al costo, precio de venta y total de unidades.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => (object)[],
                ],
            ],
            [
                'name' => self::LIST_SUPPLIERS,
                'description' => 'Listar proveedores registrados en el negocio.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'search' => ['type' => 'STRING', 'description' => 'Buscar por nombre de proveedor (opcional)'],
                    ],
                ],
            ],
            [
                'name' => self::EXPENSES_SUMMARY,
                'description' => 'Consultar los gastos recientes registrados en el negocio.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => (object)[],
                ],
            ],
            [
                'name' => self::RESTAURANT_TABLES_STATUS,
                'description' => 'Consultar el estado actual de las mesas del salón en un restaurante (libres vs ocupadas).',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => (object)[],
                ],
            ],
            [
                'name' => self::RESTAURANT_ORDERS_OPEN,
                'description' => 'Consultar las comandas y pedidos actualmente abiertos en el restaurante.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => (object)[],
                ],
            ],
            [
                'name' => self::RESTAURANT_DELIVERIES_TODAY,
                'description' => 'Consultar los pedidos a domicilio registrados el día de hoy en el restaurante.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => (object)[],
                ],
            ],
            [
                'name' => self::RESTAURANT_TIPS_COLLECTED,
                'description' => 'Consultar el total de propinas o servicio voluntario recaudado hoy en el restaurante.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => (object)[],
                ],
            ],
            [
                'name' => self::SALES_COMPARISON,
                'description' => 'Comparar ventas entre franjas horarias (turnos mañana, tarde, noche) o períodos relativos (hoy vs ayer, este mes vs mes pasado, turno tarde vs martes pasado).',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'shift' => [
                            'type' => 'STRING',
                            'description' => 'Turno o franja horaria: "morning" (06:00-11:59), "afternoon" (12:00-17:59), "night" (18:00-23:59), o "all" para día completo',
                        ],
                        'target_date' => [
                            'type' => 'STRING',
                            'description' => 'Fecha o período actual de evaluación: "today", "this_week", "this_month", o fecha YYYY-MM-DD',
                        ],
                        'compare_to' => [
                            'type' => 'STRING',
                            'description' => 'Período o fecha de comparación: "yesterday", "last_week", "last_month", "previous_tuesday", o fecha YYYY-MM-DD',
                        ],
                    ],
                ],
            ],
            [
                'name' => self::PRODUCT_MARGINS,
                'description' => 'Consultar la rentabilidad o margen de ganancia de productos o platos (unitario en $ y porcentual %), ordenados por menor o mayor margen con límite específico.',
                'parameters' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'order' => [
                            'type' => 'STRING',
                            'description' => 'Orden de rentabilidad: "asc" para menor margen / menos rentables, "desc" para mayor margen / más rentables',
                        ],
                        'limit' => [
                            'type' => 'INTEGER',
                            'description' => 'Cantidad exacta de productos a listar (por ejemplo 3 o 5)',
                        ],
                        'product_type' => [
                            'type' => 'STRING',
                            'description' => 'Filtrar por tipo: "dish" para platos de restaurante, o dejar vacío para todos',
                        ],
                    ],
                ],
            ],
        ];
    }
}
