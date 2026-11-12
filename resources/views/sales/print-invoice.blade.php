<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprobante de venta {{ $sale->invoice_number }} | {{ config('app.name') }}</title>
    @include('partials.brand-head')
    <style>
        /* ── Print-optimized layout for Letter/A4 ── */
        @page {
            size: letter;
            margin: 15mm 20mm;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 14px;
            color: #1a1a1a;
            line-height: 1.5;
            background: #fff;
        }
        .invoice-container { max-width: 800px; margin: 0 auto; padding: 20px; }

        /* ── Header ── */
        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 3px solid #4f46e5;
            padding-bottom: 20px;
            margin-bottom: 25px;
        }
        .business-info h1 { font-size: 24px; color: #4f46e5; margin-bottom: 4px; }
        .business-info p { color: #555; font-size: 13px; line-height: 1.6; }
        .invoice-meta { text-align: right; }
        .invoice-meta h2 { font-size: 28px; color: #4f46e5; text-transform: uppercase; letter-spacing: 2px; margin-bottom: 8px; }
        .invoice-meta p { font-size: 13px; color: #555; }
        .invoice-meta .invoice-number { font-size: 17px; font-weight: 700; color: #1a1a1a; font-family: 'Courier New', monospace; }

        /* ── Client & Sale Info ── */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
        }
        .info-box { background: #f8f9fa; border-radius: 8px; padding: 15px; }
        .info-box h3 { font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: #888; margin-bottom: 8px; }
        .info-box p { font-size: 14px; margin-bottom: 3px; }
        .info-box .value { font-weight: 600; }

        /* ── Products Table ── */
        table { width: 100%; border-collapse: collapse; margin-bottom: 25px; }
        thead { background: #4f46e5; color: #fff; }
        th { padding: 10px 12px; text-align: left; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
        th.text-center { text-align: center; }
        th.text-end { text-align: right; }
        td { padding: 10px 12px; border-bottom: 1px solid #e9ecef; font-size: 14px; }
        td.text-center { text-align: center; }
        td.text-end { text-align: right; }
        tbody tr:nth-child(even) { background: #f8f9fa; }
        .product-name { font-weight: 600; }
        .product-sku { color: #888; font-size: 12px; font-family: 'Courier New', monospace; }

        /* ── Totals ── */
        .totals-section { display: flex; justify-content: flex-end; margin-bottom: 30px; }
        .totals-box { width: 320px; }
        .totals-row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 14px; }
        .totals-row.discount { color: #dc3545; }
        .totals-row.total { font-size: 19px; font-weight: 700; border-top: 2px solid #1a1a1a; padding-top: 10px; margin-top: 6px; }

        /* ── Footer ── */
        .invoice-footer {
            border-top: 1px solid #dee2e6;
            padding-top: 15px;
            text-align: center;
            font-size: 12px;
            color: #888;
        }
        .invoice-footer .legal { margin-bottom: 5px; }

        /* ── No-print elements ── */
        .no-print { text-align: center; margin-bottom: 20px; }
        .no-print button {
            padding: 10px 30px;
            font-size: 14px;
            background: #4f46e5;
            color: #fff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            margin: 0 5px;
        }
        .no-print button:hover { background: #4338ca; }
        .no-print button.secondary { background: #6c757d; }
        .no-print button.secondary:hover { background: #5a6268; }

        @media print {
            .no-print { display: none !important; }
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()">🖨️ Imprimir Factura</button>
        <button class="secondary" onclick="window.close()">Cerrar</button>
    </div>

    <div class="invoice-container">
        {{-- Header --}}
        <div class="invoice-header">
            <div class="business-info">
                <h1>{{ $business->name }}</h1>
                @if($business->nit)
                    <p><strong>NIT:</strong> {{ $business->nit }}</p>
                @endif
                @if($business->address)
                    <p>{{ $business->address }}</p>
                @endif
                @if($business->phone)
                    <p>Tel: {{ $business->phone }}</p>
                @endif
                @if($business->email)
                    <p>{{ $business->email }}</p>
                @endif
            </div>
            <div class="invoice-meta">
                <h2>Comprobante de venta</h2>
                <p class="invoice-number">{{ $sale->invoice_number }}</p>
                <p>Fecha: {{ $sale->sale_date->format('d/m/Y') }}</p>
                <p>Hora: {{ $sale->sale_date->format('H:i') }}</p>
            </div>
        </div>

        {{-- Client & Sale Info --}}
        <div class="info-grid">
            <div class="info-box">
                <h3>Cliente</h3>
                <p class="value">{{ $sale->display_customer_name }}</p>
                <p>Doc/NIT: {{ $sale->display_customer_document }}</p>
                @if($sale->customer && $sale->customer->phone && ($sale->customer->document ?? $sale->customer->identification_number) !== '222222222222')
                    <p>Tel: {{ $sale->customer->phone }}</p>
                @endif
            </div>
            <div class="info-box">
                <h3>Información de Venta</h3>
                <p><strong>Vendedor:</strong> {{ $sale->user->name }}</p>
                <p><strong>Método de pago:</strong>
                    @switch($sale->payment_method)
                        @case('cash') Efectivo @break
                        @case('transfer') Transferencia @break
                        @case('card') Tarjeta @break
                        @case('mixed') Pago mixto @break
                        @default Otro
                    @endswitch
                </p>
                @if($sale->notes)
                    <p><strong>Notas:</strong> {{ $sale->notes }}</p>
                @endif
            </div>
        </div>

        {{-- Products Table --}}
        <table>
            <thead>
                <tr>
                    <th>Producto</th>
                    <th class="text-center">Cantidad</th>
                    <th class="text-end">P. Unitario</th>
                    <th class="text-end">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sale->details as $detail)
                    <tr>
                        <td>
                            <span class="product-name">{{ $detail->product->name }}</span>
                            @if($detail->product->sku)
                                <br><span class="product-sku">{{ $detail->product->sku }}</span>
                            @endif
                        </td>
                        <td class="text-center">{{ $detail->quantity }}</td>
                        <td class="text-end">${{ number_format($detail->unit_price, 0, ',', '.') }}</td>
                        <td class="text-end">${{ number_format($detail->subtotal, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- Totals & Payments --}}
        <div class="totals-section" style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px;">
            <div style="flex: 1; max-width: 420px; padding-right: 20px;">
                @if($sale->payments && $sale->payments->count() > 0)
                    <h3 style="font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #888; margin-bottom: 8px;">Detalle de Pago</h3>
                    <table style="width: 100%; margin-bottom: 0; font-size: 12px; border: 1px solid #e2e8f0; border-radius: 6px; overflow: hidden;">
                        <thead>
                            <tr style="background: #f1f5f9; color: #334155;">
                                <th style="padding: 6px 8px; font-size: 11px;">Método</th>
                                <th style="padding: 6px 8px; font-size: 11px;">Ref.</th>
                                <th style="padding: 6px 8px; font-size: 11px; text-align: right;">Monto</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($sale->payments as $payment)
                                <tr>
                                    <td style="padding: 6px 8px;">{{ $payment->method_label }}</td>
                                    <td style="padding: 6px 8px; font-family: monospace; font-size: 11px; color: #64748b;">{{ $payment->reference ?? '-' }}</td>
                                    <td style="padding: 6px 8px; text-align: right; font-weight: 600;">${{ number_format($payment->amount, 0, ',', '.') }}</td>
                                </tr>
                                @if($payment->cash_received !== null)
                                    <tr style="background: #f8fafc; font-size: 11px; color: #64748b;">
                                        <td colspan="2" style="padding: 4px 8px; border-bottom: none;">
                                            <em>Recibido: ${{ number_format($payment->cash_received, 0, ',', '.') }}</em>
                                        </td>
                                        <td style="padding: 4px 8px; text-align: right; border-bottom: none; color: #16a34a; font-weight: 600;">
                                            <em>Cambio: ${{ number_format($payment->change_given ?? 0, 0, ',', '.') }}</em>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
            <div class="totals-box">
                <div class="totals-row">
                    <span>Subtotal</span>
                    <span>${{ number_format($sale->subtotal, 0, ',', '.') }}</span>
                </div>
                @if($sale->discount > 0)
                    <div class="totals-row discount">
                        <span>Descuento ({{ number_format($sale->discount_percentage, 1) }}%)</span>
                        <span>-${{ number_format($sale->discount, 0, ',', '.') }}</span>
                    </div>
                @endif
                @if($sale->delivery_fee > 0)
                    <div class="totals-row">
                        <span>Domicilio / Flete</span>
                        <span>${{ number_format($sale->delivery_fee, 0, ',', '.') }}</span>
                    </div>
                @endif
                @if(($sale->service_fee ?? 0) > 0)
                    <div class="totals-row">
                        <span>Servicio / Propina</span>
                        <span>${{ number_format($sale->service_fee, 0, ',', '.') }}</span>
                    </div>
                @endif
                @if(($sale->tax_inc ?? 0) > 0)
                    <div class="totals-row">
                        <span>Impuesto al Consumo (INC 8%)</span>
                        <span>${{ number_format($sale->tax_inc, 0, ',', '.') }}</span>
                    </div>
                @endif
                <div class="totals-row total">
                    <span>Total</span>
                    <span>${{ number_format($sale->total, 0, ',', '.') }}</span>
                </div>
            </div>
        </div>

        {{-- Footer --}}
        <div class="invoice-footer">
            <p class="legal"><strong>{{ $business->name }}</strong>@if($business->nit) — NIT: {{ $business->nit }}@endif</p>
            <p>Documento generado el {{ now()->format('d/m/Y H:i') }} — Cali, Colombia</p>
            <p style="margin-top: 6px; font-size: 11px; color: #666; font-style: italic;">{{ config('sales.legal_disclaimer') }}</p>
            <p style="margin-top: 4px;">Gracias por su compra</p>
        </div>
    </div>

    <script>
        // Auto-print when opened in a new tab
        window.addEventListener('load', function() {
            setTimeout(function() { window.print(); }, 500);
        });
    </script>
</body>
</html>
