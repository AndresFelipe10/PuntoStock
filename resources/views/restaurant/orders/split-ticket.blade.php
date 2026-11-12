<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprobante de Pago - {{ $payment->invoice_number }}</title>
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
            font-size: 14px;
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
            font-size: 16px;
            font-weight: 900 !important;
            border-top: 1.5px solid #000;
            border-bottom: 2px solid #000;
            padding: 5px 0;
            margin-top: 4px;
            color: #000000 !important;
        }

        .balance-box {
            border: 1.5px dashed #000;
            padding: 6px;
            margin-top: 8px;
            background: #fff;
            color: #000000 !important;
            font-size: 12px;
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
        <button class="btn-print" onclick="window.print()">Imprimir Comprobante</button>
        <button class="btn-back" onclick="window.close()">Cerrar Pestaña</button>
    </div>

    <div class="ticket-container">
        <div class="business-name">{{ $order->business->name ?? 'PUNTOPOS RESTAURANTE' }}</div>
        @if(!empty($order->business->tax_id ?? $order->business->nit))
            <div class="business-nit">NIT: {{ $order->business->tax_id ?? $order->business->nit }}</div>
        @endif

        <div class="ticket-title">COMPROBANTE DE PAGO PARCIAL</div>

        <div class="meta-info">
            <div class="meta-row">
                <span>Factura / Recibo:</span>
                <strong>{{ $payment->invoice_number }}</strong>
            </div>
            <div class="meta-row">
                <span>Comanda:</span>
                <strong>{{ $order->order_number }}</strong>
            </div>
            @if($order->table)
                <div class="meta-row">
                    <span>Mesa:</span>
                    <strong>{{ $order->table->name }}</strong>
                </div>
            @endif
            <div class="meta-row">
                <span>Cliente:</span>
                <strong>{{ $payment->customer_name ?? 'Consumidor Final' }}</strong>
            </div>
            @if(!empty($payment->customer_document))
                <div class="meta-row">
                    <span>NIT / Cédula:</span>
                    <span>{{ $payment->customer_document }}</span>
                </div>
            @endif
            <div class="meta-row">
                <span>Cajero:</span>
                <span>{{ $payment->user?->name ?? 'Cajero' }}</span>
            </div>
            <div class="meta-row">
                <span>Fecha / Hora:</span>
                <span>{{ $payment->sale_date ? $payment->sale_date->format('d/m/Y H:i:s') : now('America/Bogota')->format('d/m/Y H:i:s') }}</span>
            </div>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 15%;">Cant.</th>
                    <th style="width: 55%;">Plato / Consumo</th>
                    <th style="width: 30%;" class="text-end">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($payment->details as $detail)
                    <tr>
                        <td class="text-center">{{ (float)$detail->quantity == (int)$detail->quantity ? (int)$detail->quantity : number_format($detail->quantity, 1) }}</td>
                        <td>
                            <strong>{{ $detail->product?->name ?? 'Plato / Consumo' }}</strong>
                        </td>
                        <td class="text-end">${{ number_format($detail->subtotal, 0, ',', '.') }}</td>
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
                <span>${{ number_format($payment->subtotal, 0, ',', '.') }}</span>
            </div>
            @if((float)$payment->discount > 0)
                <div class="total-row">
                    <span>Descuento ({{ (float)$payment->discount_percentage }}%):</span>
                    <span>-${{ number_format($payment->discount, 0, ',', '.') }}</span>
                </div>
            @endif
            @if((float)$payment->service_fee > 0)
                <div class="total-row">
                    <span>Servicio / Propina (10%):</span>
                    <span>+${{ number_format($payment->service_fee, 0, ',', '.') }}</span>
                </div>
            @endif
            @if((float)$payment->tax_inc > 0)
                <div class="total-row">
                    <span>Impuesto al Consumo (INC 8%):</span>
                    <span>+${{ number_format($payment->tax_inc, 0, ',', '.') }}</span>
                </div>
            @endif

            <div class="total-row grand-total">
                <span>TOTAL PAGADO:</span>
                <span>${{ number_format($payment->total, 0, ',', '.') }}</span>
            </div>

            @foreach($payment->payments as $idx => $p)
                <div class="total-row" style="margin-top: 5px; border-top: 1px dotted #000; padding-top: 3px;">
                    <span>Método {{ count($payment->payments) > 1 ? '#' . ($idx + 1) . ' (' . $p->method_label . ')' : $p->method_label }}:</span>
                    <strong>${{ number_format($p->amount, 0, ',', '.') }}</strong>
                </div>
                @if($p->method === \App\Enums\PaymentMethod::Cash || (is_string($p->method) && $p->method === 'cash'))
                    <div class="total-row">
                        <span>Efectivo Recibido:</span>
                        <span>${{ number_format($p->cash_received, 0, ',', '.') }}</span>
                    </div>
                    <div class="total-row">
                        <span>Cambio / Vueltos:</span>
                        <span>${{ number_format($p->change_given, 0, ',', '.') }}</span>
                    </div>
                @endif
                @if(!empty($p->reference))
                    <div class="total-row">
                        <span>Ref / Voucher:</span>
                        <span>{{ $p->reference }}</span>
                    </div>
                @endif
            @endforeach

            @if(!empty($payment->notes))
                <div class="total-row" style="font-size: 11px; margin-top: 5px; border-top: 1px dotted #000; padding-top: 3px;">
                    <span>Notas:</span>
                    <span>{{ $payment->notes }}</span>
                </div>
            @endif
        </div>

        @php
            $pendingBal = $order->pendingBalance();
            $isOrderClosed = in_array($order->status, ['billed', 'closed']) && $order->isFullyPaid();
        @endphp
        <div class="balance-box">
            <div class="meta-row">
                <span>Saldo Pendiente Comanda:</span>
                <strong>${{ number_format($pendingBal, 0, ',', '.') }}</strong>
            </div>
            <div class="meta-row">
                <span>Estado de la Mesa:</span>
                <strong>{{ $isOrderClosed ? 'LIBERADA (Comanda saldada)' : 'OCUPADA (Consumos pendientes)' }}</strong>
            </div>
        </div>

        <div class="footer-note">
            Pago individual de platos registrado exitosamente.<br>
            ¡Gracias por su visita!
        </div>
    </div>
</body>
</html>
