<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pre-cuenta - {{ $order->table ? $order->table->name : $order->order_number }}</title>
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
            font-size: 13px !important;
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

        .business-name {
            text-align: center;
            font-size: 17px;
            font-weight: 900 !important;
            text-transform: uppercase;
            color: #000000 !important;
        }

        .business-nit {
            text-align: center;
            font-size: 12px;
            margin-bottom: 6px;
            font-weight: 600 !important;
            color: #000000 !important;
        }

        .ticket-title {
            text-align: center;
            font-size: 15px;
            font-weight: 900 !important;
            border-top: 1.5px dashed #000;
            border-bottom: 1.5px dashed #000;
            padding: 4px 0;
            margin: 6px 0;
            letter-spacing: 0.5px;
            color: #000000 !important;
        }

        .meta-info {
            font-size: 12px;
            margin-bottom: 8px;
            border-bottom: 1.5px solid #000;
            padding-bottom: 6px;
            font-weight: 600 !important;
            color: #000000 !important;
        }

        .meta-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2px;
            color: #000000 !important;
        }

        /* Tabla de consumo */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            margin-bottom: 8px;
            color: #000000 !important;
        }

        .items-table th {
            border-bottom: 1.5px dashed #000;
            text-align: left;
            padding-bottom: 3px;
            font-weight: 900 !important;
            color: #000000 !important;
        }

        .items-table td {
            padding: 4px 0;
            vertical-align: top;
            font-weight: 600 !important;
            color: #000000 !important;
        }

        .text-end {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        /* Totales */
        .totals-section {
            border-top: 1.5px dashed #000;
            padding-top: 6px;
            font-size: 13px;
            font-weight: 600 !important;
            color: #000000 !important;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
            color: #000000 !important;
        }

        .grand-total {
            font-size: 17px;
            font-weight: 900 !important;
            border-top: 1.5px solid #000;
            border-bottom: 2px solid #000;
            padding: 6px 0;
            margin-top: 4px;
            color: #000000 !important;
        }

        .tip-box {
            border: 1px dashed #000;
            padding: 6px;
            margin-top: 8px;
            background: #fff;
            color: #000000 !important;
            font-size: 12px;
        }

        /* Leyenda legal obligatoria */
        .legal-notice {
            margin-top: 10px;
            padding: 6px 4px;
            border: 2px solid #000;
            text-align: center;
            font-weight: 900 !important;
            font-size: 11px;
            line-height: 1.25;
            text-transform: uppercase;
            color: #000000 !important;
        }

        .footer-note {
            text-align: center;
            font-size: 11px;
            margin-top: 10px;
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
        <button class="btn-print" onclick="window.print()">Imprimir Pre-cuenta</button>
        <a href="{{ route('restaurant.orders.show', $order) }}" class="btn-back">&larr; Volver a la Comanda</a>
    </div>

    <div class="ticket-container">
        <div class="business-name">
            {{ $order->business->name ?? 'RESTAURANTE' }}
        </div>
        @if($order->business && $order->business->nit)
            <div class="business-nit">
                NIT: {{ $order->business->nit }}
            </div>
        @endif

        <div class="ticket-title">
            ESTADO DE CUENTA
        </div>

        <div class="meta-info">
            <div class="meta-row">
                <span>UBICACIÓN:</span>
                <strong>{{ $order->table ? $order->table->name : 'SALÓN' }}</strong>
            </div>
            <div class="meta-row">
                <span>COMANDA:</span>
                <strong>{{ $order->order_number }}</strong>
            </div>
            <div class="meta-row">
                <span>ATENDIDO POR:</span>
                <span>{{ ($order->order_type === 'table' && !empty($order->customer_name)) ? $order->customer_name : ($order->user->name ?? 'Usuario') }}</span>
            </div>
            <div class="meta-row">
                <span>FECHA:</span>
                <span>{{ now()->format('d/m/Y H:i') }}</span>
            </div>
            <div class="meta-row">
                <span>CLIENTE:</span>
                <span>{{ $order->customer?->name ?? ($order->order_type !== 'table' && !empty($order->customer_name) ? $order->customer_name : 'Consumidor Final') }}</span>
            </div>
            @if($order->guest_count)
            <div class="meta-row">
                <span>PERSONAS:</span>
                <strong>{{ $order->guest_count }}</strong>
            </div>
            @endif
        </div>

        {{-- Detalle de ítems --}}
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 15%;">CANT</th>
                    <th style="width: 55%;">DESCRIPCIÓN</th>
                    <th style="width: 30%;" class="text-end">SUBTOTAL</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $item)
                    <tr>
                        <td class="text-center">
                            {{ (float) $item->quantity == (int) $item->quantity ? (int) $item->quantity : $item->quantity }}
                        </td>
                        <td>
                            {{ $item->product->name }}
                        </td>
                        <td class="text-end">
                            ${{ number_format($item->subtotal, 0, ',', '.') }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if(!empty($order->clean_notes))
            <div style="border-top: 1px dashed #000; padding: 6px 0; margin-top: 4px; font-size: 12px;">
                <strong>Notas de la Orden:</strong><br>
                {{ $order->clean_notes }}
            </div>
        @endif

        {{-- Totales --}}
        @php
            $itemsSubtotal = $order->itemsSubtotal();
            $deliveryFee = $order->deliveryFeeAmount();
            $orderTotal = round($itemsSubtotal + $deliveryFee, 2);
            $suggestedTip = round($itemsSubtotal * 0.10);
            $totalWithTip = round($orderTotal + $suggestedTip);
        @endphp

        <div class="totals-section">
            <div class="total-row">
                <span>Subtotal Consumo:</span>
                <strong>${{ number_format($itemsSubtotal, 0, ',', '.') }}</strong>
            </div>

            @if($deliveryFee > 0)
                <div class="total-row">
                    <span>Servicio Entrega / Domicilio:</span>
                    <strong>${{ number_format($deliveryFee, 0, ',', '.') }}</strong>
                </div>
            @endif

            <div class="total-row grand-total">
                <span>SUBTOTAL A PAGAR:</span>
                <span>${{ number_format($orderTotal, 0, ',', '.') }}</span>
            </div>

            {{-- Desglose sugerido de propina voluntaria (10%) --}}
            <div class="tip-box">
                <div class="total-row" style="margin-bottom: 2px;">
                    <span>+ Propina Sugerida (10%):</span>
                    <strong>${{ number_format($suggestedTip, 0, ',', '.') }}</strong>
                </div>
                <div class="total-row" style="font-weight: 900 !important; font-size: 14px; border-top: 1px dashed #000; padding-top: 3px; margin-top: 3px;">
                    <span>TOTAL CON PROPINA:</span>
                    <span>${{ number_format($totalWithTip, 0, ',', '.') }}</span>
                </div>
                <div style="font-size: 10px; margin-top: 4px; line-height: 1.2; text-align: justify;">
                    * La propina es voluntaria y sugerida (10%). Indique al cajero si desea modificar su valor o retirarla antes del pago.
                </div>
            </div>
        </div>

        {{-- Leyenda legal obligatoria requerida por DIAN y normativa colombiana --}}
        <div class="legal-notice">
            ESTADO DE CONSUMO / PRE-CUENTA — DOCUMENTO INTERNO NO VÁLIDO COMO FACTURA O COMPROBANTE DE VENTA
        </div>

        <div class="footer-note">
            ¡Muchas gracias por su visita!
        </div>
    </div>

    {{-- Auto-impresión térmica inmediata --}}
    <script>
        window.addEventListener('DOMContentLoaded', () => {
            window.print();
        });
    </script>
</body>
</html>
