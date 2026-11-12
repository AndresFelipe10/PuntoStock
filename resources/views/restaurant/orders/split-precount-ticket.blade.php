<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pre-cuenta Parcial - {{ $order->table ? $order->table->name : $order->order_number }}</title>
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
            background-color: #198754;
            color: #fff !important;
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            margin-right: 8px;
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
                margin: 0;
                size: 80mm auto;
            }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="btn-print" onclick="window.print()">Imprimir Pre-cuenta Parcial</button>
        <button class="btn-back" onclick="window.close()">Cerrar Pestaña</button>
    </div>

    <div class="ticket-container">
        <div class="business-name">{{ $order->business->name ?? 'PUNTOPOS RESTAURANTE' }}</div>
        @if(!empty($order->business->tax_id ?? $order->business->nit))
            <div class="business-nit">NIT: {{ $order->business->tax_id ?? $order->business->nit }}</div>
        @endif

        <div class="ticket-title">PRE-CUENTA PARCIAL ({{ $order->table ? $order->table->name : 'Mesa' }})</div>

        <div class="meta-info">
            <div class="meta-row">
                <span>Comanda:</span>
                <strong>{{ $order->order_number }}</strong>
            </div>
            @if($order->table)
                <div class="meta-row">
                    <span>Mesa / Área:</span>
                    <strong>{{ $order->table->name }}</strong>
                </div>
            @endif
            @if(!empty($customer))
                <div class="meta-row">
                    <span>Cliente:</span>
                    <strong>{{ $customer->name }} (Doc: {{ $customer->document ?? 'N/A' }})</strong>
                </div>
            @endif
            <div class="meta-row">
                <span>Atendido por:</span>
                <span>{{ $order->customer_name ?? $order->user->name ?? 'Personal' }}</span>
            </div>
            <div class="meta-row">
                <span>Fecha / Hora:</span>
                <span>{{ now('America/Bogota')->format('d/m/Y H:i:s') }}</span>
            </div>
            <div class="meta-row">
                <span>Platos a liquidar:</span>
                <span>{{ $items->count() }} ítem(s)</span>
            </div>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 15%;">Cant.</th>
                    <th style="width: 55%;">Descripción</th>
                    <th style="width: 30%;" class="text-end">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $item)
                    <tr>
                        <td class="text-center">{{ (float)$item->quantity == (int)$item->quantity ? (int)$item->quantity : number_format($item->quantity, 1) }}</td>
                        <td>
                            <strong>{{ $item->product?->name ?? 'Plato / Consumo' }}</strong>
                            @if(!empty($item->notes))
                                <br><small>({{ $item->notes }})</small>
                            @endif
                        </td>
                        <td class="text-end">${{ number_format($item->subtotal, 0, ',', '.') }}</td>
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

        <div class="totals-section">
            <div class="total-row">
                <span>Subtotal Platos:</span>
                <span>${{ number_format($splitTotal, 0, ',', '.') }}</span>
            </div>
            @if(isset($discountAmount) && $discountAmount > 0)
                <div class="total-row">
                    <span>Descuento ({{ $discountPct }}%):</span>
                    <span>-${{ number_format($discountAmount, 0, ',', '.') }}</span>
                </div>
            @endif
            @if(isset($tipAmount) && $tipAmount > 0)
                <div class="total-row">
                    <span>Propina Sugerida (10%):</span>
                    <span>+${{ number_format($tipAmount, 0, ',', '.') }}</span>
                </div>
            @endif
            @if(isset($taxAmount) && $taxAmount > 0)
                <div class="total-row">
                    <span>Impuesto al Consumo (INC 8%):</span>
                    <span>+${{ number_format($taxAmount, 0, ',', '.') }}</span>
                </div>
            @endif
            <div class="total-row grand-total">
                <span>TOTAL A PAGAR:</span>
                <span>${{ number_format($finalTotal ?? $splitTotal, 0, ',', '.') }}</span>
            </div>
        </div>

        <div class="legal-notice">
            DOCUMENTO INTERNO NO VÁLIDO COMO FACTURA O COMPROBANTE DE VENTA
        </div>

        <div class="footer-note">
            Pre-cuenta parcial generada para comensal individual.<br>
            Gracias por su visita.
        </div>
    </div>
</body>
</html>
