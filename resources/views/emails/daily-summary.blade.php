<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resumen Operativo Diario - {{ $business->name }}</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #f1f5f9;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            color: #1e293b;
            -webkit-text-size-adjust: 100%;
        }
        table {
            border-collapse: collapse;
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }
        .container {
            max-width: 640px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }
        .header {
            background-color: #0f172a;
            padding: 28px 24px;
            text-align: center;
        }
        .header-brand {
            font-size: 24px;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: -0.5px;
            margin: 0;
        }
        .header-brand span {
            color: #3b82f6;
        }
        .header-subtitle {
            color: #94a3b8;
            font-size: 14px;
            margin-top: 6px;
            margin-bottom: 0;
        }
        .content {
            padding: 24px;
        }
        .kpi-table {
            width: 100%;
            margin-bottom: 24px;
        }
        .kpi-card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px;
            text-align: center;
        }
        .kpi-label {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            color: #64748b;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
        }
        .kpi-value {
            font-size: 20px;
            font-weight: 800;
            color: #0f172a;
            margin: 0;
        }
        .badge {
            display: inline-block;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 12px;
            margin-top: 6px;
        }
        .badge-positive {
            background-color: #dcfce7;
            color: #15803d;
        }
        .badge-negative {
            background-color: #fee2e2;
            color: #b91c1c;
        }
        .badge-neutral {
            background-color: #f1f5f9;
            color: #475569;
        }
        .section-title {
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
            margin: 24px 0 12px 0;
            display: flex;
            align-items: center;
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 8px;
        }
        .data-table {
            width: 100%;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
            margin-bottom: 20px;
        }
        .data-table th {
            background-color: #f8fafc;
            color: #475569;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }
        .data-table td {
            padding: 10px 12px;
            font-size: 13px;
            color: #334155;
            border-bottom: 1px solid #f1f5f9;
        }
        .data-table tr:last-child td {
            border-bottom: none;
        }
        .ai-box {
            background-color: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: 18px;
            margin: 24px 0;
        }
        .ai-box-title {
            font-size: 13px;
            font-weight: 700;
            color: #1d4ed8;
            margin-bottom: 8px;
        }
        .ai-box-text {
            font-size: 13px;
            line-height: 1.6;
            color: #1e3a8a;
            margin: 0;
        }
        .cta-button {
            display: block;
            background-color: #2563eb;
            color: #ffffff !important;
            text-decoration: none;
            text-align: center;
            padding: 14px 24px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 14px;
            margin: 28px 0 16px 0;
        }
        .footer {
            background-color: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 24px;
            text-align: center;
            font-size: 12px;
            color: #94a3b8;
            line-height: 1.5;
        }
        .footer a {
            color: #2563eb;
            text-decoration: none;
        }
    </style>
</head>
<body style="padding: 24px 12px; background-color: #f1f5f9;">

<div class="container">
    {{-- Header con Logo Oficial --}}
    @include('emails.partials.header-logo', [
        'subtitle' => 'Resumen Operativo Diario • ' . $business->name . ' • ' . $summary['date_human']
    ])

    {{-- Content --}}
    <div class="content">
        {{-- KPI Cards --}}
        <table class="kpi-table" cellpadding="6" cellspacing="0">
            <tr>
                <td width="50%" valign="top">
                    <div class="kpi-card">
                        <div class="kpi-label">Total Facturado Hoy</div>
                        <div class="kpi-value" style="color: #2563eb;">{{ $summary['revenue_today_formatted'] }}</div>
                        @php
                            $isPos = str_starts_with($summary['percentage_variation'], '+') && $summary['percentage_variation'] !== '+0.0%';
                            $isNeg = str_starts_with($summary['percentage_variation'], '-');
                        @endphp
                        <span class="badge {{ $isPos ? 'badge-positive' : ($isNeg ? 'badge-negative' : 'badge-neutral') }}">
                            {{ $summary['percentage_variation'] }} vs ayer
                        </span>
                    </div>
                </td>
                <td width="50%" valign="top">
                    <div class="kpi-card">
                        <div class="kpi-label">Transacciones / Ventas</div>
                        <div class="kpi-value">{{ $summary['sales_count'] }}</div>
                        <div style="font-size: 12px; color: #64748b; margin-top: 6px;">
                            Ticket prom: <strong>{{ $summary['average_ticket_formatted'] }}</strong>
                        </div>
                    </div>
                </td>
            </tr>
        </table>

        {{-- Desglose por Método de Pago --}}
        @if (!empty($summary['payment_breakdown']))
            <h3 class="section-title">💳 Desglose por Método de Recaudo</h3>
            <table class="data-table" cellpadding="0" cellspacing="0">
                <thead>
                    <tr>
                        <th>Método</th>
                        <th style="text-align: right;">Total</th>
                        <th style="text-align: right;">% Part.</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($summary['payment_breakdown'] as $pm)
                        <tr>
                            <td><strong>{{ $pm['label'] }}</strong></td>
                            <td style="text-align: right; font-weight: 600;">{{ $pm['amount_formatted'] }}</td>
                            <td style="text-align: right; color: #64748b;">{{ $pm['percentage'] }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        {{-- Top 5 Productos Más Vendidos --}}
        @if (!empty($summary['top_products']))
            <h3 class="section-title">🏆 Top Productos Más Vendidos del Día</h3>
            <table class="data-table" cellpadding="0" cellspacing="0">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th style="text-align: center;">Cant.</th>
                        <th style="text-align: right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($summary['top_products'] as $item)
                        <tr>
                            <td>
                                <strong>{{ $item['name'] }}</strong>
                                <div style="font-size: 11px; color: #94a3b8;">SKU: {{ $item['sku'] }}</div>
                            </td>
                            <td style="text-align: center; font-weight: 700;">{{ $item['quantity'] }}</td>
                            <td style="text-align: right; font-weight: 600; color: #0f172a;">{{ $item['revenue_formatted'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        {{-- Alerta de Stock Crítico / Reposición --}}
        @if (!empty($summary['critical_stock']))
            <h3 class="section-title" style="color: #b45309;">⚠️ Alerta de Inventario Crítico para Abastecimiento</h3>
            <table class="data-table" cellpadding="0" cellspacing="0">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th style="text-align: center;">Stock</th>
                        <th style="text-align: center;">Mínimo</th>
                        <th style="text-align: right;">Sugerido</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($summary['critical_stock'] as $p)
                        <tr>
                            <td><strong>{{ $p['name'] }}</strong></td>
                            <td style="text-align: center; color: #dc2626; font-weight: 700;">{{ $p['stock'] }}</td>
                            <td style="text-align: center; color: #64748b;">{{ $p['min_stock'] }}</td>
                            <td style="text-align: right; color: #2563eb; font-weight: 700;">+{{ $p['deficit'] }} unid.</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        {{-- Conclusión Inteligente del Asistente IA --}}
        <div class="ai-box">
            <div class="ai-box-title">🤖 Conclusión Ejecutiva del Asistente IA</div>
            <p class="ai-box-text">{{ $summary['ai_conclusion'] }}</p>
        </div>

        {{-- Botón CTA --}}
        <a href="{{ url('/login') }}" class="cta-button">
            Acceder a mi Panel en PuntoStock POS &rarr;
        </a>
    </div>

    {{-- Footer con Enlaces Legales --}}
    @include('emails.partials.footer', [
        'footerNote' => 'Este es un resumen operativo generado automáticamente por PuntoStock POS para la gerencia de ' . $business->name . '.'
    ])
</div>

</body>
</html>
