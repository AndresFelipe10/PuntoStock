<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comanda de Cocina - {{ $order->order_number }}</title>
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
            font-size: 20px;
            font-weight: 900 !important;
            letter-spacing: 1px;
            border-bottom: 2px dashed #000;
            padding-bottom: 8px;
            margin-bottom: 8px;
            color: #000000 !important;
        }

        .reprint-badge {
            text-align: center;
            font-weight: 900 !important;
            font-size: 13px;
            margin-bottom: 6px;
            color: #000000 !important;
        }

        .info-section {
            border-bottom: 1.5px dashed #000;
            padding-bottom: 8px;
            margin-bottom: 10px;
            font-size: 13px;
            font-weight: 600 !important;
            color: #000000 !important;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
            color: #000000 !important;
        }

        .mesa-destacada {
            font-size: 22px;
            font-weight: 900 !important;
            text-align: center;
            padding: 4px 0;
            border: 2px solid #000;
            margin: 6px 0;
            text-transform: uppercase;
            color: #000000 !important;
        }

        .items-list {
            margin-top: 8px;
        }

        .item-block {
            padding: 6px 0;
            border-bottom: 1.5px dotted #000;
            color: #000000 !important;
        }

        .item-main {
            font-size: 16px;
            font-weight: 900 !important;
            display: flex;
            align-items: flex-start;
            color: #000000 !important;
        }

        .item-qty {
            min-width: 38px;
            display: inline-block;
            font-weight: 900 !important;
        }

        .item-name {
            flex-grow: 1;
            text-transform: uppercase;
            font-weight: 900 !important;
        }

        .item-notes {
            margin-top: 4px;
            margin-left: 20px;
            padding: 3px 6px;
            background: #fff;
            border-left: 3px solid #000;
            font-size: 13px;
            font-weight: 900 !important;
            text-transform: uppercase;
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
        <button class="btn-print" onclick="window.print()">Imprimir Ticket Cocina</button>
        <a href="{{ route('restaurant.orders.show', $order) }}" class="btn-back">Volver a la Comanda</a>
    </div>

    <div class="ticket-container">
        <div class="header-title">
            COMANDA DE COCINA
        </div>

        @if($isReprint)
            <div class="reprint-badge">
                *** REIMPRESIÓN ***
            </div>
        @endif

        <div class="mesa-destacada">
            {{ $order->table ? $order->table->name : ($order->order_type === 'delivery' ? 'DOMICILIO' : 'PARA LLEVAR') }}
        </div>

        <div class="info-section">
            <div class="info-row">
                <span>Comanda:</span>
                <strong>{{ $order->order_number }}</strong>
            </div>
            <div class="info-row">
                <span>Mesero / Atendido por:</span>
                <strong>{{ ($order->order_type === 'table' && !empty($order->customer_name)) ? $order->customer_name : ($order->user->name ?? 'Usuario') }}</strong>
            </div>
            <div class="info-row">
                <span>Fecha/Hora:</span>
                <strong>{{ now()->format('d/m/Y H:i:s') }}</strong>
            </div>
            <div class="info-row">
                <span>Cliente:</span>
                <strong>{{ $order->customer?->name ?? ($order->order_type !== 'table' && !empty($order->customer_name) ? $order->customer_name : 'Consumidor Final') }}</strong>
            </div>
            @if($order->guest_count)
            <div class="info-row">
                <span>Personas:</span>
                <strong>{{ $order->guest_count }}</strong>
            </div>
            @endif
        </div>

        <div class="items-list">
            @forelse($itemsToPrint as $item)
                <div class="item-block">
                    <div class="item-main">
                        <span class="item-qty">[{{ (float) $item->quantity == (int) $item->quantity ? (int) $item->quantity : $item->quantity }}]</span>
                        <span class="item-name">{{ $item->product->name }}</span>
                    </div>
                    @if($item->notes)
                        <div class="item-notes">
                            *** {{ $item->notes }} ***
                        </div>
                    @endif
                </div>
            @empty
                <div style="text-align: center; padding: 10px;">
                    No hay platos pendientes para cocina.
                </div>
            @endforelse
        </div>

        @if(!empty($order->clean_notes))
            <div style="border-top: 1px dashed #000; padding: 6px 0; margin-top: 4px; font-size: 12px;">
                <strong>Notas de la Orden:</strong><br>
                {{ $order->clean_notes }}
            </div>
        @endif

        <div class="footer-note">
            PUNTO DE PREPARACIÓN / COCINA
        </div>
    </div>

    <script>
        window.addEventListener('load', function () {
            window.print();
        });
    </script>
</body>
</html>
