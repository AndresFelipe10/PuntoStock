@extends('layouts.app')

@section('title', 'Venta ' . $sale->invoice_number)

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div class="d-flex align-items-center gap-3">
        @if($sale->restaurant_order_id)
            <a href="{{ route('restaurant.orders.show', $sale->restaurant_order_id) }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3" title="Volver a la comanda de restaurante">
                <i class="bi bi-arrow-left me-1"></i> Volver a Comanda
            </a>
        @else
            <a href="{{ route('sales.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                <i class="bi bi-arrow-left me-1"></i> Volver
            </a>
        @endif
        <div>
            <h3 class="fw-bold mb-0">Comprobante de Venta</h3>
            <span class="font-monospace text-primary fw-bold">{{ $sale->invoice_number }}</span>
            @if($sale->order_type && $sale->order_type !== 'retail')
                <span class="badge bg-light text-dark border ms-2">
                    <i class="bi bi-egg-fried me-1 text-warning"></i>
                    @if($sale->order_type === 'table') Salón / Mesa @elseif($sale->order_type === 'delivery') Domicilio @else Para Llevar @endif
                </span>
            @endif
        </div>
    </div>

    <div class="d-flex align-items-center gap-2">
        @if ($sale->status === 'completed')
            <a href="{{ route('sales.print.invoice', $sale) }}" target="_blank"
               class="btn btn-outline-primary btn-sm rounded-pill px-3" title="Imprimir comprobante tamaño carta/A4">
                <i class="bi bi-printer me-1"></i> Imprimir comprobante (carta)
            </a>
            <a href="{{ route('sales.print.receipt', $sale) }}" target="_blank"
               class="btn btn-outline-secondary btn-sm rounded-pill px-3" title="Imprimir comprobante para impresora térmica">
                <i class="bi bi-receipt me-1"></i> Imprimir comprobante (ticket)
            </a>
            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-3 py-2">
                <i class="bi bi-check-circle-fill me-1"></i> Completada
            </span>
            @can('delete', $sale)
                <button type="button" class="btn btn-outline-danger btn-sm rounded-pill px-3"
                        data-bs-toggle="modal" data-bs-target="#cancelSaleModal">
                    <i class="bi bi-x-circle me-1"></i> Anular Venta
                </button>
            @endcan
        @else
            <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-3 py-2">
                <i class="bi bi-x-circle-fill me-1"></i> Anulada
            </span>
        @endif
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-lg-9">
        {{-- Cabecera --}}
        <div class="card card-custom p-4 mb-4">
            <div class="row g-3">
                <div class="col-sm-6 col-md-3">
                    <span class="text-muted small d-block">Fecha</span>
                    <span class="fw-semibold">{{ $sale->sale_date->format('d/m/Y H:i') }}</span>
                </div>
                <div class="col-sm-6 col-md-3">
                    <span class="text-muted small d-block">Cliente</span>
                    <span class="fw-semibold">
                        @if ($sale->customer && ($sale->customer->document ?? $sale->customer->identification_number) !== '222222222222')
                            <a href="{{ route('customers.show', $sale->customer) }}" class="text-decoration-none">
                                {{ $sale->display_customer_name }}
                            </a>
                        @else
                            <span>{{ $sale->display_customer_name }}</span>
                        @endif
                    </span>
                    @if ($sale->display_customer_document)
                        <span class="text-muted small d-block font-monospace">Doc: {{ $sale->display_customer_document }}</span>
                    @endif
                </div>
                <div class="col-sm-6 col-md-3">
                    <span class="text-muted small d-block">Vendedor</span>
                    <span class="fw-semibold">{{ $sale->user->name }}</span>
                </div>
                <div class="col-sm-6 col-md-3">
                    <span class="text-muted small d-block">Método de Pago</span>
                    <span class="fw-semibold">
                        @switch($sale->payment_method)
                            @case('cash') <i class="bi bi-cash text-success me-1"></i> Efectivo @break
                            @case('transfer') <i class="bi bi-bank text-info me-1"></i> Transferencia @break
                            @case('card') <i class="bi bi-credit-card text-primary me-1"></i> Tarjeta @break
                            @case('mixed') <i class="bi bi-wallet2 text-warning me-1"></i> Pago Mixto @break
                            @default <i class="bi bi-three-dots text-secondary me-1"></i> Otro
                        @endswitch
                    </span>
                </div>
            </div>

            @if ($sale->notes)
                <div class="mt-3 pt-3 border-top">
                    <span class="text-muted small d-block">Notas</span>
                    <span>{{ $sale->notes }}</span>
                </div>
            @endif
        </div>

        {{-- Detalle de productos --}}
        <div class="card card-custom mb-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Producto</th>
                            <th class="text-center">Cantidad</th>
                            <th class="text-end">P. Unitario</th>
                            <th class="text-end pe-4">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sale->details as $detail)
                            <tr>
                                <td class="ps-4">
                                    <span class="fw-semibold">{{ $detail->product->name }}</span>
                                    @if ($detail->product->sku)
                                        <small class="text-muted font-monospace ms-2">{{ $detail->product->sku }}</small>
                                    @endif
                                </td>
                                <td class="text-center">{{ $detail->quantity }}</td>
                                <td class="text-end">${{ number_format($detail->unit_price, 0, ',', '.') }}</td>
                                <td class="text-end pe-4 fw-semibold">${{ number_format($detail->subtotal, 0, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Métodos de Pago Aplicados --}}
        @if ($sale->payments->isNotEmpty())
            <div class="card card-custom p-4 mb-4">
                <h6 class="fw-bold mb-3"><i class="bi bi-wallet2 text-primary me-2"></i>Distribución de Pagos</h6>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-3">Método</th>
                                <th>Referencia</th>
                                <th class="text-end">Monto Aplicado</th>
                                <th class="text-end">Efectivo Recibido</th>
                                <th class="text-end pe-3">Vuelto / Cambio</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sale->payments as $payment)
                                <tr>
                                    <td class="ps-3">
                                        <span class="fw-semibold">
                                            @switch($payment->method instanceof \App\Enums\PaymentMethod ? $payment->method->value : $payment->method)
                                                @case('cash') <i class="bi bi-cash text-success me-1"></i> Efectivo @break
                                                @case('transfer') <i class="bi bi-bank text-info me-1"></i> Transferencia @break
                                                @case('card') <i class="bi bi-credit-card text-primary me-1"></i> Tarjeta @break
                                                @default <i class="bi bi-three-dots text-secondary me-1"></i> Otro
                                            @endswitch
                                        </span>
                                    </td>
                                    <td>
                                        <span class="text-muted small">{{ $payment->reference ?: '—' }}</span>
                                    </td>
                                    <td class="text-end fw-semibold">${{ number_format($payment->amount, 0, ',', '.') }}</td>
                                    <td class="text-end text-muted">
                                        {{ $payment->cash_received !== null ? '$' . number_format($payment->cash_received, 0, ',', '.') : '—' }}
                                    </td>
                                    <td class="text-end pe-3 text-muted">
                                        {{ $payment->change_given !== null ? '$' . number_format($payment->change_given, 0, ',', '.') : '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Totales --}}
        <div class="card card-custom p-4">
            <div class="row justify-content-end">
                <div class="col-md-5">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Subtotal</span>
                        <span class="fw-semibold">${{ number_format($sale->subtotal, 0, ',', '.') }}</span>
                    </div>
                    @if ($sale->discount > 0)
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Descuento</span>
                            <span class="fw-semibold text-danger">
                                {{ number_format($sale->discount_percentage, 1) }}% (-${{ number_format($sale->discount, 0, ',', '.') }})
                            </span>
                        </div>
                    @endif
                    @if ($sale->delivery_fee > 0)
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Domicilio / Flete</span>
                            <span class="fw-semibold text-dark">
                                ${{ number_format($sale->delivery_fee, 0, ',', '.') }}
                            </span>
                        </div>
                    @endif
                    @if (($sale->service_fee ?? 0) > 0)
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Servicio / Propina</span>
                            <span class="fw-semibold text-dark">
                                ${{ number_format($sale->service_fee, 0, ',', '.') }}
                            </span>
                        </div>
                    @endif
                    @if (($sale->tax_inc ?? 0) > 0)
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted">Impuesto al Consumo (INC 8%)</span>
                            <span class="fw-semibold text-dark">
                                ${{ number_format($sale->tax_inc, 0, ',', '.') }}
                            </span>
                        </div>
                    @endif
                    <hr>
                    <div class="d-flex justify-content-between">
                        <span class="fw-bold fs-5">Total</span>
                        <span class="fw-bold fs-5 text-primary">${{ number_format($sale->total, 0, ',', '.') }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@can('delete', $sale)
{{-- Modal Corporativo de Anulación de Venta --}}
<div class="modal fade" id="cancelSaleModal" tabindex="-1" aria-labelledby="cancelSaleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
            <div class="modal-header bg-danger text-white py-3 px-4">
                <h5 class="modal-title fw-bold d-flex align-items-center" id="cancelSaleModalLabel">
                    <i class="bi bi-x-circle-fill me-2 fs-5"></i> Anular Venta
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <div class="text-danger mb-3">
                    <i class="bi bi-exclamation-triangle-fill display-4"></i>
                </div>
                <h5 class="fw-bold text-dark mb-2">¿Estás seguro de anular esta venta?</h5>
                <div class="alert alert-light border rounded-3 p-3 text-start small mb-3">
                    <div class="mb-1">
                        <span class="text-muted">Comprobante:</span>
                        <strong class="text-dark">{{ $sale->invoice_number }}</strong>
                    </div>
                    <div class="mb-1">
                        <span class="text-muted">Total:</span>
                        <strong class="text-dark">${{ number_format($sale->total, 0, ',', '.') }}</strong>
                    </div>
                    <div>
                        <span class="text-muted">Cliente:</span>
                        <strong class="text-dark">{{ $sale->display_customer_name }}</strong>
                    </div>
                </div>
                <div class="alert alert-warning border-0 small mb-0 text-start">
                    <i class="bi bi-info-circle me-1"></i>
                    El stock de los productos vendidos será reintegrado automáticamente al inventario. Esta acción no se puede deshacer.
                </div>
            </div>
            <div class="modal-footer bg-light px-4 py-3 justify-content-end gap-2 border-0">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">
                    Cancelar
                </button>
                <form method="POST" action="{{ route('sales.destroy', $sale) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger rounded-pill px-4 fw-bold">
                        <i class="bi bi-x-circle me-1"></i> Sí, Anular Venta
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endcan
@endsection
