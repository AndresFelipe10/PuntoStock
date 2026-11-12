<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tirilla de Despacho - {{ $order->order_number }}</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            color: #000000 !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            text-shadow: none !important;
        }

        body {
            font-family: 'Courier New', Courier, Consolas, monospace !important;
            background-color: #fff;
            color: #000000 !important;
            font-size: 14px !important;
            line-height: 1.3 !important;
            font-weight: 600 !important;
            padding: 10px;
        }

        .ticket-container {
            max-width: 80mm;
            width: 100%;
            margin: 0 auto;
            background: #fff;
            padding: 12px 6px;
            border: 1.5px dashed #000;
        }

        .header-title {
            text-align: center;
            font-size: 18px;
            font-weight: 900 !important;
            letter-spacing: 1px;
            text-transform: uppercase;
            color: #000000 !important;
        }

        .header-business {
            text-align: center;
            font-size: 15px;
            font-weight: 900 !important;
            margin-bottom: 2px;
            color: #000000 !important;
        }

        .header-phone {
            text-align: center;
            font-size: 13px;
            margin-bottom: 8px;
            border-bottom: 2px dashed #000;
            padding-bottom: 6px;
            font-weight: 600 !important;
            color: #000000 !important;
        }

        .order-meta {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            font-weight: 900 !important;
            margin-bottom: 8px;
            border-bottom: 1.5px dashed #000;
            padding-bottom: 6px;
            color: #000000 !important;
        }

        /* Marco Destacado de Entrega */
        .delivery-box {
            border: 2px solid #000;
            padding: 8px;
            margin: 8px 0;
            background-color: #fff;
            color: #000000 !important;
        }

        .delivery-title {
            font-size: 15px;
            font-weight: 900 !important;
            text-align: center;
            text-transform: uppercase;
            border-bottom: 1.5px solid #000;
            padding-bottom: 4px;
            margin-bottom: 6px;
            color: #000000 !important;
        }

        .delivery-field {
            margin-bottom: 4px;
            font-size: 13px;
            color: #000000 !important;
        }

        .delivery-label {
            font-weight: 900 !important;
            display: inline-block;
            color: #000000 !important;
        }

        .delivery-val {
            font-weight: 900 !important;
            font-size: 14px;
            color: #000000 !important;
        }

        .delivery-val-highlight {
            font-size: 15px;
            font-weight: 900 !important;
            text-transform: uppercase;
            word-break: break-word;
            color: #000000 !important;
        }

        /* Detalle de Platos */
        .items-section {
            margin-top: 8px;
            border-bottom: 1.5px dashed #000;
            padding-bottom: 8px;
        }

        .items-title {
            font-weight: 900 !important;
            font-size: 13px;
            margin-bottom: 4px;
            text-transform: uppercase;
            color: #000000 !important;
        }

        .item-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 4px 0;
            font-size: 13px;
            font-weight: 600 !important;
            color: #000000 !important;
        }

        .item-qty-name {
            font-weight: 900 !important;
            flex-grow: 1;
            color: #000000 !important;
        }

        .item-notes {
            margin-left: 15px;
            font-size: 12px;
            font-style: italic;
            font-weight: 600 !important;
            color: #000000 !important;
        }

        /* Totales y Liquidación Contraentrega */
        .totals-section {
            margin-top: 8px;
            padding-top: 4px;
        }

        .total-line {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            margin-bottom: 3px;
            font-weight: 600 !important;
            color: #000000 !important;
        }

        .cod-box {
            border: 2px solid #000;
            padding: 8px;
            margin-top: 8px;
            text-align: center;
            background-color: #fff;
            color: #000000 !important;
        }

        .cod-label {
            font-size: 13px;
            font-weight: 900 !important;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #000000 !important;
        }

        .cod-amount {
            font-size: 22px;
            font-weight: 900 !important;
            margin-top: 3px;
            color: #000000 !important;
        }

        .footer-note {
            text-align: center;
            font-size: 11px;
            margin-top: 14px;
            border-top: 1.5px dashed #000;
            padding-top: 6px;
            font-weight: 600 !important;
            color: #000000 !important;
        }

        .no-print {
            text-align: center;
            margin-bottom: 15px;
        }

        .btn-print {
            background-color: #1B2A49;
            color: #fff !important;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            margin-right: 8px;
        }
        .btn-print:hover {
            background-color: #E8A317;
            color: #1B2A49 !important;
        }

        .btn-back {
            background-color: #6c757d;
            color: #fff !important;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            display: inline-block;
        }

        @media print {
            * {
                color: #000000 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                text-shadow: none !important;
            }

            body {
                font-family: 'Courier New', Courier, Consolas, monospace !important;
                font-size: 13px !important;
                line-height: 1.25 !important;
                font-weight: 600 !important;
                background-color: #fff;
                padding: 0;
                margin: 0;
            }

            h1, h2, h3, h4, h5, .fw-bold, strong, b, .total-line {
                font-weight: 900 !important;
            }

            .text-muted, .text-secondary {
                color: #000000 !important;
            }

            table, th, td {
                color: #000000 !important;
                font-weight: 600 !important;
            }

            hr, .border-top, .border-bottom {
                border-color: #000000 !important;
                border-width: 1.5px !important;
            }

            .ticket-container {
                max-width: 80mm;
                width: 80mm;
                border: none;
                padding: 4px;
                margin: 0;
            }

            .no-print {
                display: none !important;
            }

            @page {
                size: 80mm auto;
                margin: 0;
            }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="btn-print" onclick="window.print()">Imprimir Tirilla Despacho</button>
        <a href="{{ route('restaurant.orders.deliveries') }}" class="btn-back">&larr; Volver al Tablero</a>
    </div>

    <div class="ticket-container">
        <div class="header-business">
            {{ $order->business->name ?? 'RESTAURANTE' }}
        </div>
        <div class="header-title">
            TIRILLA DE DESPACHO
        </div>
        @if($order->business && $order->business->phone)
            <div class="header-phone">
                Tel: {{ $order->business->phone }}
            </div>
        @else
            <div class="header-phone">
                --- PEDIDO PARA REPARTO ---
            </div>
        @endif

        <div class="order-meta">
            <span>PEDIDO: {{ $order->order_number }}</span>
            <span>{{ now()->format('d/m/Y H:i') }}</span>
        </div>

        {{-- Marco Destacado de Entrega --}}
        <div class="delivery-box">
            <div class="delivery-title">
                {{ $order->order_type === 'delivery' ? 'DATOS DE ENTREGA' : 'PEDIDO PARA LLEVAR' }}
            </div>

            <div class="delivery-field">
                <span class="delivery-label">Cliente:</span>
                <span class="delivery-val">{{ $order->client_display_name }}</span>
            </div>

            <div class="delivery-field">
                <span class="delivery-label">Atendido por:</span>
                <span class="delivery-val">{{ $order->user->name ?? 'Usuario' }}</span>
            </div>

            @if($order->delivery_phone)
                <div class="delivery-field">
                    <span class="delivery-label">Teléfono:</span>
                    <span class="delivery-val">{{ $order->delivery_phone }}</span>
                </div>
            @endif

            @if($order->order_type === 'delivery' && $order->delivery_address)
                <div class="delivery-field">
                    <span class="delivery-label">Dirección:</span>
                    <div class="delivery-val-highlight">{{ $order->delivery_address }}</div>
                </div>
            @endif

            @if($order->delivery_notes)
                <div class="delivery-field" style="margin-top: 4px; border-top: 1px dotted #999; padding-top: 3px;">
                    <span class="delivery-label">Indicaciones:</span>
                    <div>{{ $order->delivery_notes }}</div>
                </div>
            @endif
        </div>

        {{-- Detalle de Platos --}}
        <div class="items-section">
            <div class="items-title">Detalle del Pedido:</div>
            @foreach($order->items as $item)
                <div class="item-block">
                    <div class="item-row">
                        <span class="item-qty-name">
                            [{{ (float) $item->quantity == (int) $item->quantity ? (int) $item->quantity : $item->quantity }}] {{ $item->product->name }}
                        </span>
                        <span>${{ number_format($item->subtotal, 2) }}</span>
                    </div>
                    @if($item->notes)
                        <div class="item-notes">
                            * {{ $item->notes }}
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        @if(!empty($order->clean_notes))
            <div style="border-top: 1px dashed #000; padding: 6px 0; margin-top: 4px; font-size: 12px;">
                <strong>Notas de la Orden:</strong><br>
                {{ $order->clean_notes }}
            </div>
        @endif

        {{-- Liquidación Contraentrega --}}
        <div class="totals-section">
            <div class="total-line">
                <span>Subtotal Consumo:</span>
                <strong>${{ number_format($order->itemsSubtotal(), 2) }}</strong>
            </div>

            @if($order->order_type === 'delivery' && $order->deliveryFeeAmount() > 0)
                <div class="total-line">
                    <span>Costo de Domicilio:</span>
                    <strong>${{ number_format($order->deliveryFeeAmount(), 2) }}</strong>
                </div>
            @endif

            <div class="cod-box">
                @if($order->isClosedOrBilled() || $order->pendingBalance() <= 0)
                    <div class="cod-label">TOTAL PEDIDO:</div>
                @elseif($order->order_type === 'delivery')
                    <div class="cod-label">TOTAL A COBRAR EN DESTINO:</div>
                @else
                    <div class="cod-label">TOTAL A COBRAR:</div>
                @endif
                <div class="cod-amount">${{ number_format($order->totalAmount(), 2) }}</div>
            </div>
        </div>

        <div class="footer-note">
            Comprobante de despacho y entrega a domicilio.<br>
            Documento interno de control operativo.
        </div>
    </div>

    <script>
        window.addEventListener('DOMContentLoaded', () => {
            window.print();
        });
    </script>
</body>
</html>
