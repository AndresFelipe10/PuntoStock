<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprobante de venta {{ $sale->invoice_number }} | {{ config('app.name') }}</title>
    @include('partials.brand-head')
    <style>
        /* ── Thermal receipt: 80mm width ── */
        @page {
            size: 80mm auto;
            margin: 0;
        }
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
            font-size: 13px !important;
            color: #000000 !important;
            line-height: 1.35 !important;
            font-weight: 600 !important;
            background: #fff;
            width: 80mm;
            max-width: 302px;
            margin: 0 auto;
            padding: 5mm 3mm;
        }

        /* ── Header ── */
        .receipt-header {
            text-align: center;
            border-bottom: 1.5px dashed #000;
            padding-bottom: 8px;
            margin-bottom: 8px;
        }
        .receipt-header .business-name {
            font-size: 17px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #000000 !important;
        }
        .receipt-header .business-detail {
            font-size: 11px;
            color: #000000 !important;
            font-weight: 600;
        }

        /* ── Invoice Info ── */
        .receipt-info {
            border-bottom: 1.5px dashed #000;
            padding-bottom: 8px;
            margin-bottom: 8px;
            font-size: 12px;
            font-weight: 600;
            color: #000000 !important;
        }
        .receipt-info .row {
            display: flex;
            justify-content: space-between;
        }

        /* ── Items ── */
        .items-header {
            display: flex;
            justify-content: space-between;
            font-weight: 900;
            font-size: 12px;
            border-bottom: 1.5px solid #000;
            padding-bottom: 3px;
            margin-bottom: 5px;
            color: #000000 !important;
        }
        .item-line {
            margin-bottom: 4px;
            font-size: 12px;
            font-weight: 600;
            color: #000000 !important;
        }
        .item-name {
            font-weight: 900;
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            color: #000000 !important;
        }
        .item-detail {
            display: flex;
            justify-content: space-between;
            color: #000000 !important;
            padding-left: 8px;
            font-weight: 600;
        }

        /* ── Totals ── */
        .totals {
            border-top: 1.5px dashed #000;
            margin-top: 8px;
            padding-top: 8px;
            color: #000000 !important;
        }
        .totals .row {
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            margin-bottom: 2px;
            font-weight: 600;
            color: #000000 !important;
        }
        .totals .discount { color: #000000 !important; }
        .totals .total-row {
            font-size: 17px;
            font-weight: 900;
            border-top: 1.5px solid #000;
            padding-top: 5px;
            margin-top: 5px;
            color: #000000 !important;
        }

        /* ── Footer ── */
        .receipt-footer {
            border-top: 1.5px dashed #000;
            margin-top: 10px;
            padding-top: 8px;
            text-align: center;
            font-size: 11px;
            color: #000000 !important;
            font-weight: 600;
        }
        .receipt-footer .thanks {
            font-size: 14px;
            font-weight: 900;
            color: #000000 !important;
            margin-bottom: 4px;
        }

        /* ── No-print elements ── */
        .no-print {
            text-align: center;
            margin-bottom: 10px;
            width: 80mm;
            max-width: 302px;
        }
        .no-print button {
            padding: 8px 20px;
            font-size: 12px;
            background: #1B2A49;
            color: #fff !important;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin: 2px;
            font-weight: bold;
        }
        .no-print button:hover { background: #E8A317; color: #1B2A49 !important; }

        @media print {
            .no-print { display: none !important; }
            body { padding: 2mm; }
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
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()">🧾 Imprimir comprobante (ticket)</button>
        <button onclick="window.close()">Cerrar</button>
    </div>

    {{-- Header --}}
    <div class="receipt-header">
        <div class="business-name">{{ $business->name }}</div>
        @if($business->nit)
            <div class="business-detail">NIT: {{ $business->nit }}</div>
        @endif
        @if($business->address)
            <div class="business-detail">{{ $business->address }}</div>
        @endif
        @if($business->phone)
            <div class="business-detail">Tel: {{ $business->phone }}</div>
        @endif
    </div>

    {{-- Invoice Info --}}
    <div class="receipt-info">
        <div class="row">
            <span>Comprobante:</span>
            <span><strong>{{ $sale->invoice_number }}</strong></span>
        </div>
        <div class="row">
            <span>Fecha:</span>
            <span>{{ $sale->sale_date->format('d/m/Y H:i') }}</span>
        </div>
        <div class="row">
            <span>Cliente:</span>
            <span>{{ $sale->display_customer_name }}</span>
        </div>
        <div class="row">
            <span>Doc/NIT:</span>
            <span>{{ $sale->display_customer_document }}</span>
        </div>
        <div class="row">
            <span>Vendedor:</span>
            <span>{{ $sale->user->name }}</span>
        </div>
        <div class="row">
            <span>Pago:</span>
            <span>
                @switch($sale->payment_method)
                    @case('cash') Efectivo @break
                    @case('transfer') Transferencia @break
                    @case('card') Tarjeta @break
                    @case('mixed') Mixto @break
                    @default Otro
                @endswitch
            </span>
        </div>
        @if($sale->notes)
            <div class="row" style="align-items: flex-start; margin-top: 3px; border-top: 1px dotted #ccc; padding-top: 3px;">
                <span style="font-weight: bold;">Obs / Notas:</span>
                <span style="text-align: right; max-width: 75%; word-break: break-word;">{{ $sale->notes }}</span>
            </div>
        @endif
    </div>

    {{-- Items --}}
    <div class="items-header">
        <span>PRODUCTO</span>
        <span>TOTAL</span>
    </div>

    @foreach($sale->details as $detail)
        <div class="item-line">
            <span class="item-name">{{ $detail->product->name }}</span>
            <div class="item-detail">
                <span>{{ $detail->quantity }} x ${{ number_format($detail->unit_price, 0, ',', '.') }}</span>
                <span>${{ number_format($detail->subtotal, 0, ',', '.') }}</span>
            </div>
        </div>
    @endforeach

    {{-- Totals --}}
    <div class="totals">
        <div class="row">
            <span>Subtotal:</span>
            <span>${{ number_format($sale->subtotal, 0, ',', '.') }}</span>
        </div>
        @if($sale->discount > 0)
            <div class="row discount">
                <span>Desc. ({{ number_format($sale->discount_percentage, 1) }}%):</span>
                <span>-${{ number_format($sale->discount, 0, ',', '.') }}</span>
            </div>
        @endif
        @if($sale->delivery_fee > 0)
            <div class="row">
                <span>Domicilio / Flete:</span>
                <span>${{ number_format($sale->delivery_fee, 0, ',', '.') }}</span>
            </div>
        @endif
        @if(($sale->service_fee ?? 0) > 0)
            <div class="row">
                <span>Servicio / Propina:</span>
                <span>${{ number_format($sale->service_fee, 0, ',', '.') }}</span>
            </div>
        @endif
        @if(($sale->tax_inc ?? 0) > 0)
            <div class="row">
                <span>Impuesto al Consumo (INC 8%):</span>
                <span>${{ number_format($sale->tax_inc, 0, ',', '.') }}</span>
            </div>
        @endif
        <div class="row total-row">
            <span>TOTAL:</span>
            <span>${{ number_format($sale->total, 0, ',', '.') }}</span>
        </div>
    </div>

    {{-- Formas de pago aplicadas --}}
    @if($sale->payments->isNotEmpty())
        <div class="totals" style="border-top: 1.5px dashed #000; margin-top: 6px; padding-top: 4px; color: #000000 !important;">
            <div style="font-weight: 900; font-size: 12px; margin-bottom: 3px; text-transform: uppercase; color: #000000 !important;">Formas de pago:</div>
            @foreach($sale->payments as $payment)
                <div class="row" style="color: #000000 !important; font-weight: 600;">
                    <span>{{ $payment->method_label }}{{ $payment->reference ? ' (' . $payment->reference . ')' : '' }}:</span>
                    <span>${{ number_format($payment->amount, 0, ',', '.') }}</span>
                </div>
                @if(($payment->method instanceof \App\Enums\PaymentMethod ? $payment->method->value : $payment->method) === 'cash')
                    @if($payment->cash_received !== null)
                        <div class="row" style="color: #000000 !important; font-size: 11px; font-weight: 600;">
                            <span>  Efectivo recibido:</span>
                            <span>${{ number_format($payment->cash_received, 0, ',', '.') }}</span>
                        </div>
                        <div class="row" style="color: #000000 !important; font-size: 11px; font-weight: 600;">
                            <span>  Vueltos:</span>
                            <span>${{ number_format($payment->change_given, 0, ',', '.') }}</span>
                        </div>
                    @endif
                @endif
            @endforeach
        </div>
    @endif

    {{-- Footer --}}
    <div class="receipt-footer">
        <div class="thanks">¡Gracias por su compra!</div>
        <div>{{ $business->name }}</div>
        <div>{{ now()->format('d/m/Y H:i') }}</div>
        <div>Cali, Colombia</div>
        <div style="margin-top: 8px; font-size: 10px; line-height: 1.3; color: #000000 !important; font-weight: 600;">{{ config('sales.legal_disclaimer') }}</div>
    </div>

    <script>
        // Auto-print when opened in a new tab
        window.addEventListener('load', function() {
            setTimeout(function() { window.print(); }, 500);
        });
    </script>
</body>
</html>
