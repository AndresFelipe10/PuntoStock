@extends('layouts.app')

@section('title', "Comanda {$order->order_number}")

@section('content')
@php
    $groupedProducts = $groupedProducts ?? $products->groupBy(fn($p) => $p->category?->name ?? 'General / Sin Categoría');
    $waiterName = $order->waiter_name;
    $clientName = $order->client_display_name;
@endphp
<style>
    .btn-action-icon-danger {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border-radius: 6px;
        background-color: #fee2e2;
        color: #dc3545;
        border: 1px solid rgba(220, 53, 69, 0.25);
        transition: all 0.2s ease-in-out;
        padding: 0;
        line-height: 1;
        cursor: pointer;
    }
    .btn-action-icon-danger i,
    .btn-action-icon-danger svg {
        color: inherit !important;
        fill: currentColor !important;
        font-size: 0.95rem;
        transition: color 0.2s ease-in-out;
    }
    .btn-action-icon-danger:hover,
    .btn-action-icon-danger:focus {
        background-color: #dc3545 !important;
        color: #ffffff !important;
        border-color: #dc3545 !important;
        box-shadow: 0 2px 6px rgba(220, 53, 69, 0.35);
        transform: translateY(-1px);
    }
</style>
<div class="container-fluid py-3" style="max-width: 1200px;">
    {{-- Header de la Comanda --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div class="d-flex align-items-center">
            @if($order->order_type === 'table')
                <a href="{{ route('restaurant.tables.index') }}" class="btn btn-outline-secondary btn-sm me-3">
                    <i class="bi bi-arrow-left"></i> Salón
                </a>
            @else
                <a href="{{ route('restaurant.orders.deliveries') }}" class="btn btn-outline-secondary btn-sm me-3">
                    <i class="bi bi-arrow-left"></i> Domicilios
                </a>
            @endif
            <div>
                <h1 class="h3 fw-bold mb-0 text-dark">
                    Comanda <span class="text-primary">{{ $order->order_number }}</span>
                    @if($order->table)
                        <span class="badge bg-light text-dark border ms-2">
                            <i class="bi bi-aspect-ratio me-1"></i>{{ $order->table->name }}
                        </span>
                        @if($order->canBeModified() && !in_array($order->status, ['billed', 'closed', 'cancelled']))
                            <button type="button" class="btn btn-sm btn-outline-primary ms-2" data-bs-toggle="modal" data-bs-target="#changeTableModal">
                                <i class="bi bi-arrow-left-right me-1"></i> Cambiar Mesa
                            </button>
                        @endif
                    @elseif($order->order_type === 'delivery')
                        <span class="badge bg-info-subtle text-info-emphasis border ms-2">
                            <i class="bi bi-bicycle me-1"></i>Domicilio
                        </span>
                    @elseif($order->order_type === 'takeout')
                        <span class="badge bg-secondary-subtle text-secondary-emphasis border ms-2">
                            <i class="bi bi-bag me-1"></i>Para Llevar
                        </span>
                    @endif
                </h1>
                <p class="text-muted small mb-0">
                    Mesero / Atendido por: <strong>{{ $waiterName }}</strong> &bull;
                    Apertura: {{ $order->created_at->format('d/m/Y H:i') }} ({{ $order->created_at->diffForHumans() }})
                    &bull; Cliente: <strong>{{ $clientName }}</strong>
                    @if($order->guest_count)
                        &bull; Personas: <strong>{{ $order->guest_count }}</strong>
                    @endif
                </p>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2">
            {{-- Acceso a Configuración de Impresión de Comandas --}}
            <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#kitchenConfigModal" title="Configuración de Comandas">
                <i class="bi bi-gear-fill me-1"></i> Configuración
            </button>

            {{-- Botón Enviar a Cocina / Imprimir Ticket --}}
            @php
                $hasPendingKitchen = $order->items->where('printed_to_kitchen', false)->where('status', '!=', 'cancelled')->count() > 0;
                $activeItems = $order->items->where('status', '!=', 'cancelled');
                $dishSubtotal = 0.0;
                foreach ($activeItems as $item) {
                    if ($item->product && $item->product->isDish()) {
                        $dishSubtotal += (float)$item->quantity * (float)$item->unit_price;
                    }
                }
            @endphp

            @if(!in_array($order->status, ['closed', 'cancelled', 'billed']))
                <form action="{{ route('restaurant.orders.kitchen-ticket', ['order' => $order] + ($hasPendingKitchen ? [] : ['reprint' => 1])) }}"
                      method="POST"
                      target="_blank"
                      class="d-inline"
                      data-no-disable="true"
                      data-no-spinner="true">
                    @csrf
                    @if(!$hasPendingKitchen)
                        <input type="hidden" name="reprint" value="1">
                    @endif
                    <button type="submit"
                            class="btn {{ $hasPendingKitchen ? 'btn-warning text-dark fw-bold animate-pulse' : 'btn-outline-secondary' }}"
                            data-no-disable="true"
                            data-no-spinner="true">
                        <i class="bi bi-printer me-1"></i>
                        {{ $hasPendingKitchen ? 'Enviar a Cocina' : 'Reimprimir en cocina' }}
                    </button>
                </form>
            @endif

            {{-- Botón Pre-cuenta (Mesas) --}}
            @if($order->order_type === 'table' && !in_array($order->status, ['closed', 'cancelled', 'billed']))
                <a href="{{ route('restaurant.orders.prebill', $order) }}" target="_blank" class="btn btn-outline-warning text-dark fw-bold" data-no-disable="true">
                    <i class="bi bi-file-earmark-text me-1"></i> Pre-cuenta
                </a>
            @endif

            {{-- Botón Dividir Cuenta por Platos (Mesas) --}}
            @if($order->order_type === 'table' && $order->canBeModified() && !in_array($order->status, ['closed', 'cancelled', 'billed']) && $order->unpaidItems()->isNotEmpty())
                <button type="button" class="btn btn-outline-success fw-semibold rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#splitBillModal">
                    <i class="bi bi-pie-chart me-1"></i> Dividir Cuenta por Platos
                </button>
            @endif

            {{-- Botón Cancelar Comanda Vacía --}}
            @if($order->status === 'open' && $order->items->isEmpty())
                <button type="button" class="btn btn-outline-danger fw-semibold" data-bs-toggle="modal" data-bs-target="#cancelEmptyOrderModal">
                    <i class="bi bi-trash3 me-1"></i> Cancelar Comanda Vacía
                </button>
            @endif

            {{-- Botón Cobrar / Facturar --}}
            @if(!in_array($order->status, ['closed', 'cancelled', 'billed']) && !$order->isFullyPaid())
                <button type="button" class="btn btn-success fw-bold text-white shadow-sm" data-bs-toggle="modal" data-bs-target="#settlementModal" {{ $activeItems->isEmpty() ? 'disabled' : '' }}>
                    <i class="bi bi-cash-coin me-1"></i> {{ $order->hasPartialPayments() ? 'Cobrar Saldo Restante ($' . number_format($order->pendingBalance(), 0, ',', '.') . ')' : 'Cobrar / Facturar' }}
                </button>
            @elseif(($order->status === 'closed' || $order->isFullyPaid() || $order->status === 'billed') && ($order->sale_id || ($order->relationLoaded('sales') && $order->sales->isNotEmpty())))
                @php
                    $headerSaleId = $order->sale_id ?? ($order->relationLoaded('sales') && $order->sales->isNotEmpty() ? $order->sales->last()->id : null);
                @endphp
                @if($headerSaleId)
                    <a href="{{ route('sales.show', $headerSaleId) }}" class="btn btn-outline-primary fw-bold">
                        <i class="bi bi-receipt me-1"></i> Factura de Venta
                    </a>
                    <a href="{{ route('sales.print.receipt', $headerSaleId) }}" target="_blank" class="btn btn-outline-secondary" data-no-disable="true">
                        <i class="bi bi-printer me-1"></i> Ticket Fiscal
                    </a>
                @endif
            @endif
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show rounded-3" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <strong>Error al procesar:</strong>
            <ul class="mb-0 mt-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="row g-4">
        {{-- Columna Izquierda: Ítems por Tandas --}}
        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 mb-4">
                <div class="card-header bg-white border-0 pt-4 pb-2 px-4 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0">
                        <i class="bi bi-list-check me-2 text-primary"></i>Platos y Productos Ordenados
                    </h5>
                    <span class="badge bg-light text-dark border">
                        Total Ítems: {{ $order->items->count() }}
                    </span>
                </div>

                <div class="card-body p-4">
                    @if($order->items->isEmpty())
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-cart-x fs-1 d-block mb-2 text-secondary"></i>
                            <p class="mb-2 fw-semibold">No hay platos agregados a esta comanda aún.</p>
                            @if($order->status === 'open')
                                <p class="small text-muted mb-3">Si esta comanda fue abierta por error, puedes anularla y liberar la mesa inmediatamente.</p>
                                <button type="button" class="btn btn-outline-danger btn-sm px-3" data-bs-toggle="modal" data-bs-target="#cancelEmptyOrderModal">
                                    <i class="bi bi-trash3 me-1"></i> Cancelar Comanda Vacía
                                </button>
                            @endif
                        </div>
                    @else
                        @foreach($batches as $batchNumber => $items)
                            <div class="border rounded-3 mb-4 overflow-hidden">
                                <div class="bg-light px-3 py-2 border-bottom d-flex justify-content-between align-items-center">
                                    <span class="fw-bold text-dark">
                                        <i class="bi bi-clock-history me-1 text-primary"></i> Tanda #{{ $batchNumber }}
                                    </span>
                                    @php
                                        $allPrinted = $items->every(fn($i) => $i->printed_to_kitchen);
                                    @endphp
                                    <span class="badge {{ $allPrinted ? 'bg-success' : 'bg-warning text-dark' }}">
                                        {{ $allPrinted ? 'Enviado a Cocina' : 'Pendiente de Cocina' }}
                                    </span>
                                </div>

                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light small">
                                            <tr>
                                                <th class="ps-3">Cant.</th>
                                                <th>Plato / Producto</th>
                                                <th>Observaciones</th>
                                                <th class="text-end">P. Unit</th>
                                                <th class="text-end pe-3">Subtotal</th>
                                                @if(auth()->check() && auth()->user()->hasRole('admin') && $order->canBeModified())
                                                    <th class="text-center" style="width: 60px;">Acción</th>
                                                @endif
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($items as $item)
                                                <tr class="{{ $item->isCancelled() ? 'opacity-75 bg-light' : '' }}">
                                                    <td class="ps-3 fw-bold {{ $item->isCancelled() ? 'text-decoration-line-through text-muted' : 'text-primary' }}" style="width: 70px;">
                                                        {{ (float) $item->quantity == (int) $item->quantity ? (int) $item->quantity : $item->quantity }}
                                                    </td>
                                                    <td>
                                                        <span class="fw-semibold {{ $item->isCancelled() ? 'text-decoration-line-through text-muted' : 'text-dark' }}">{{ $item->product->name }}</span>
                                                        @if($item->isCancelled())
                                                            <span class="badge bg-danger-subtle text-danger border ms-1" title="Cancelado por {{ $item->cancelledByUser?->name ?? 'Admin' }}: {{ $item->cancellation_reason }}">
                                                                <i class="bi bi-x-circle me-1"></i>Cancelado
                                                            </span>
                                                        @elseif($item->isPaid())
                                                            <span class="badge bg-success-subtle text-success border border-success-subtle ms-1">
                                                                <i class="bi bi-check2-circle me-1"></i>Pagado
                                                            </span>
                                                        @elseif($item->printed_to_kitchen)
                                                            <i class="bi bi-check2-all text-success ms-1" title="Impreso en cocina"></i>
                                                        @else
                                                            <span class="badge bg-warning text-dark ms-1" style="font-size: 0.7rem;">Sin imprimir</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if($item->isCancelled())
                                                            <span class="small text-danger fst-italic">
                                                                <i class="bi bi-info-circle me-1"></i>{{ $item->cancellation_reason }}
                                                            </span>
                                                        @elseif($item->notes)
                                                            <span class="badge bg-light text-dark border fw-normal text-wrap text-start">
                                                                <i class="bi bi-chat-left-text me-1 text-muted"></i>{{ $item->notes }}
                                                            </span>
                                                        @else
                                                            <span class="text-muted small">—</span>
                                                        @endif
                                                    </td>
                                                    <td class="text-end text-muted small">
                                                        ${{ number_format($item->unit_price, 2) }}
                                                    </td>
                                                    <td class="text-end pe-3 fw-bold {{ $item->isCancelled() ? 'text-decoration-line-through text-muted' : '' }}">
                                                        ${{ number_format($item->subtotal, 2) }}
                                                    </td>
                                                    @if(auth()->check() && auth()->user()->hasRole('admin') && $order->canBeModified())
                                                        <td class="text-center">
                                                            @if(! $item->isCancelled() && ! $item->isPaid())
                                                                <button type="button" 
                                                                        class="btn-action-icon-danger" 
                                                                        title="Gestionar o retirar plato de la comanda"
                                                                        data-bs-toggle="modal" 
                                                                        data-bs-target="#deleteItemModal"
                                                                        data-item-name="{{ $item->product->name }}"
                                                                        data-item-qty="{{ (float) $item->quantity == (int) $item->quantity ? (int) $item->quantity : $item->quantity }}"
                                                                        data-item-unit-price="{{ (float) $item->unit_price }}"
                                                                        data-action-url="{{ route('restaurant.orders.items.destroy', [$order, $item]) }}">
                                                                    <i class="bi bi-trash3"></i>
                                                                </button>
                                                            @elseif($item->isPaid())
                                                                <span class="badge bg-success-subtle text-success border border-success-subtle small" title="Plato pagado con soporte contable"><i class="bi bi-lock-fill me-1"></i>Pagado</span>
                                                            @else
                                                                <span class="text-muted small">—</span>
                                                            @endif
                                                        </td>
                                                    @endif
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>

            {{-- Formulario para Agregar Más Platos o Panel Informativo de Comanda Cerrada --}}
            {{-- Formulario para Agregar Más Platos o Panel Informativo de Comanda Cerrada --}}
            @if($order->canBeModified())
                <div class="card border-0 shadow-sm rounded-4">
                    <div class="card-header bg-white border-0 pt-4 pb-2 px-4">
                        <h5 class="fw-bold mb-0">
                            <i class="bi bi-plus-circle-fill me-2 text-primary"></i>Agregar Platos a la Comanda
                        </h5>
                        <p class="text-muted small mb-0">Los platos agregados formarán parte de una nueva tanda para despacho.</p>
                    </div>

                    <div class="card-body p-4">
                        @php
                            $groupedProducts = $products->groupBy(fn($p) => $p->category?->name ?? 'General / Sin Categoría');
                        @endphp
                        <form action="{{ route('restaurant.orders.items.store', $order) }}" method="POST" id="addItemsForm">
                            @csrf

                            <div id="stock-error-container" class="w-100"></div>

                            <div id="new-items-container">
                                <div class="card bg-light border-0 mb-3 new-item-row p-3 rounded-3">
                                    <div class="row g-2 align-items-center">
                                        <div class="col-12 col-md-5">
                                            <label class="form-label small fw-semibold">Plato / Producto <span class="text-danger">*</span></label>
                                            <div class="position-relative mb-1">
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-search text-muted"></i></span>
                                                    <input type="text" class="form-control form-control-sm border-start-0 product-search-input" placeholder="Escribe para buscar plato o bebida..." autocomplete="off">
                                                </div>
                                                <div class="product-dropdown-list list-group position-absolute w-100 shadow-lg border rounded-3 overflow-auto d-none" style="max-height: 220px; z-index: 1050; top: 100%; left: 0; background: #fff;"></div>
                                            </div>
                                            <select class="form-select form-select-sm product-select" name="items[0][product_id]" required>
                                                <option value="" disabled selected>-- O selecciona del menú agrupado --</option>
                                                @foreach($groupedProducts as $categoryName => $catProducts)
                                                    <optgroup label="{{ $categoryName }}">
                                                        @foreach($catProducts as $prod)
                                                            @php
                                                                $isStd = $prod->isStandard();
                                                                $availStock = (float) ($prod->available_stock ?? $prod->stock);
                                                                $availStockFormatted = (float)$availStock == (int)$availStock ? (int)$availStock : $availStock;
                                                            @endphp
                                                            <option value="{{ $prod->id }}" 
                                                                    data-category="{{ $categoryName }}" 
                                                                    data-price="{{ $prod->sale_price }}"
                                                                    data-name="{{ $prod->name }}"
                                                                    data-is-standard="{{ $isStd ? '1' : '0' }}"
                                                                    data-stock="{{ $availStock }}"
                                                                    {{ ($isStd && $availStock <= 0) ? 'disabled' : '' }}>
                                                                @if($isStd)
                                                                    @if($availStock <= 0)
                                                                        {{ $prod->name }} — ${{ number_format($prod->sale_price, 0, ',', '.') }}  ·  Agotado
                                                                    @else
                                                                        {{ $prod->name }} — ${{ number_format($prod->sale_price, 0, ',', '.') }}  ·  {{ $availStockFormatted }} disp.
                                                                    @endif
                                                                @else
                                                                    {{ $prod->name }} — ${{ number_format($prod->sale_price, 0, ',', '.') }}
                                                                @endif
                                                            </option>
                                                        @endforeach
                                                    </optgroup>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-6 col-md-2">
                                            <label class="form-label small fw-semibold">Cantidad <span class="text-danger">*</span></label>
                                            <div class="input-group input-group-sm" style="max-width: 130px;">
                                                <button type="button" class="btn btn-outline-secondary btn-qty-minus fw-bold px-2">−</button>
                                                <input type="number" name="items[0][quantity]" class="form-control text-center input-qty input-stepper" value="1" min="1" max="999" required>
                                                <button type="button" class="btn btn-outline-secondary btn-qty-plus fw-bold px-2">+</button>
                                            </div>
                                            <span class="stock-hint badge rounded-pill bg-light text-secondary border mt-1 d-none" style="font-size: 0.7rem; font-weight: 500;"></span>
                                        </div>
                                        <div class="col-6 col-md-4">
                                            <label class="form-label small fw-semibold">Observación</label>
                                            <input type="text" class="form-control form-select-sm" name="items[0][notes]" placeholder="Ej: Sin cebolla, término 3/4">
                                        </div>
                                        <div class="col-12 col-md-1 text-end pt-md-4">
                                            <button type="button" class="btn btn-sm btn-outline-danger remove-row-btn" disabled>
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 pt-3 border-top">
                                <button type="button" class="btn btn-outline-primary fw-semibold px-3 py-2 d-inline-flex align-items-center gap-2" id="btn-add-more-row" style="min-height: 44px;">
                                    <i class="bi bi-plus-circle fs-5"></i>
                                    <span>Agregar otro plato</span>
                                </button>
                                <button type="submit" class="btn btn-primary px-4 py-2 fw-bold d-inline-flex align-items-center shadow-sm" style="min-height: 44px;">
                                    <i class="bi bi-send-check fs-5 me-2"></i> Confirmar y Enviar a Cocina
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @elseif($order->isCancelled())
                {{-- Panel informativo de Comanda Anulada --}}
                <div class="card border-0 shadow-sm rounded-4 p-4 text-center bg-light-subtle mb-4">
                    <div class="mb-3">
                        <span class="badge bg-danger-subtle text-danger fs-6 px-3 py-2 border border-danger-subtle rounded-pill">
                            <i class="bi bi-x-circle-fill me-1"></i> Comanda Anulada
                        </span>
                    </div>
                    <h5 class="fw-bold text-dark mb-1">Esta comanda ha sido anulada</h5>
                    <p class="text-muted small mb-3">La mesa asociada ha sido liberada y no se pueden agregar platos a este servicio.</p>
                    <div class="d-flex justify-content-center gap-2 flex-wrap">
                        <a href="{{ route('restaurant.tables.index') }}" class="btn btn-primary px-4 py-2 rounded-3 fw-semibold">
                            <i class="bi bi-grid-3x3-gap me-2"></i>Volver al Salón y Mesas
                        </a>
                        <a href="{{ route('restaurant.orders.index') }}" class="btn btn-outline-secondary px-4 py-2 rounded-3 fw-semibold">
                            <i class="bi bi-receipt me-2"></i>Ir a Comandas de Hoy
                        </a>
                    </div>
                </div>
            @elseif($order->isClosedOrBilled())
                {{-- Panel informativo de Comanda Cerrada y Facturada --}}
                <div class="card border-0 shadow-sm rounded-4 p-4 text-center bg-light-subtle mb-4">
                    <div class="mb-3">
                        <span class="badge bg-success-subtle text-success fs-6 px-3 py-2 border border-success-subtle rounded-pill">
                            <i class="bi bi-check-circle-fill me-1"></i> Comanda Cerrada y Facturada
                        </span>
                    </div>
                    <h5 class="fw-bold text-dark mb-1">Esta comanda ha sido liquidada en su totalidad</h5>
                    <p class="text-muted small mb-3">La mesa asociada ha sido liberada y no se pueden agregar más platos a este servicio.</p>
                    <div class="d-flex justify-content-center gap-2 flex-wrap">
                        <a href="{{ route('restaurant.tables.index') }}" class="btn btn-primary px-4 py-2 rounded-3 fw-semibold">
                            <i class="bi bi-grid-3x3-gap me-2"></i>Volver al Salón y Mesas
                        </a>
                        <a href="{{ route('restaurant.orders.index') }}" class="btn btn-outline-secondary px-4 py-2 rounded-3 fw-semibold">
                            <i class="bi bi-receipt me-2"></i>Ir a Comandas de Hoy
                        </a>
                        @php
                            $panelSaleId = $order->sale_id ?? ($order->relationLoaded('sales') && $order->sales->isNotEmpty() ? $order->sales->last()->id : null);
                        @endphp
                        @if($panelSaleId)
                            <a href="{{ route('sales.show', $panelSaleId) }}" class="btn btn-outline-success px-3 py-2 rounded-3 fw-semibold" target="_blank">
                                <i class="bi bi-file-earmark-text me-1"></i>Ver Factura
                            </a>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        {{-- Columna Derecha: Resumen de Totales y Mesa --}}
        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 sticky-top" style="top: 20px;">
                <div class="card-header bg-white border-0 pt-4 pb-2 px-4">
                    <h5 class="fw-bold mb-0">Resumen de Comanda</h5>
                </div>

                <div class="card-body px-4 pb-4">
                    <div class="d-flex justify-content-between py-2 border-bottom">
                        <span class="text-muted">Subtotal Consumo:</span>
                        <span class="fw-semibold text-dark">${{ number_format($order->itemsSubtotal(), 2) }}</span>
                    </div>

                    @if($order->deliveryFeeAmount() > 0)
                        <div class="d-flex justify-content-between py-2 border-bottom">
                            <span class="text-muted">Flete Domicilio:</span>
                            <span class="fw-semibold text-dark">${{ number_format($order->deliveryFeeAmount(), 2) }}</span>
                        </div>
                    @endif

                    @if($order->totalTips() > 0)
                        <div class="d-flex justify-content-between py-2 border-bottom text-success small">
                            <span><i class="bi bi-heart me-1"></i>Propinas Pagadas:</span>
                            <span class="fw-semibold">+${{ number_format($order->totalTips(), 2) }}</span>
                        </div>
                    @endif

                    @if($order->totalTaxes() > 0)
                        <div class="d-flex justify-content-between py-2 border-bottom text-primary small">
                            <span>Impuesto al Consumo (INC):</span>
                            <span class="fw-semibold">+${{ number_format($order->totalTaxes(), 2) }}</span>
                        </div>
                    @endif

                    @if($order->hasPartialPayments())
                        <div class="d-flex justify-content-between py-2 border-bottom text-muted small">
                            <span><i class="bi bi-check-circle me-1 text-success"></i>Consumo Pagado:</span>
                            <span class="fw-semibold text-dark">${{ number_format($order->paidAmount(), 2) }}</span>
                        </div>
                        <div class="d-flex justify-content-between py-3 border-bottom bg-light px-2 rounded-3 my-2">
                            <span class="h5 fw-bold text-dark mb-0">SALDO POR COBRAR:</span>
                            <span class="h4 fw-bold text-success mb-0">${{ number_format($order->pendingBalance(), 2) }}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center pt-2">
                            <span class="small text-muted fw-semibold">Recaudado hasta ahora:</span>
                            <span class="fw-bold text-primary">${{ number_format($order->totalCollected(), 2) }}</span>
                        </div>
                    @elseif($order->totalCollected() == 0)
                        <div class="d-flex justify-content-between align-items-center border-top pt-2 mt-2">
                            <span class="fw-bold">Total a Cobrar:</span>
                            <span class="fs-4 fw-bold text-primary">${{ number_format($order->pendingBalance(), 2) }}</span>
                        </div>
                    @else
                        <div class="d-flex justify-content-between align-items-center border-top pt-2 mt-2">
                            <span class="fw-bold">Total Recaudado:</span>
                            <span class="fs-4 fw-bold text-primary">${{ number_format($order->totalCollected(), 2) }}</span>
                        </div>
                    @endif

                    <div class="bg-light rounded-3 p-3 mt-1 text-start small">
                        <div class="mb-2">
                            <span class="text-muted d-block">Estado de Comanda:</span>
                            @switch($order->status)
                                @case('open')
                                    @if(!empty($order->precount_printed_at) || $order->hasPartialPayments())
                                        <span class="badge bg-warning text-dark fs-6"><i class="bi bi-clock-history me-1"></i>En Cobro / Pre-cuenta</span>
                                    @else
                                        <span class="badge bg-info text-dark fs-6">Abierta</span>
                                    @endif
                                    @break
                                @case('in_kitchen')
                                    @if(!empty($order->precount_printed_at) || $order->hasPartialPayments())
                                        <span class="badge bg-warning text-dark fs-6"><i class="bi bi-clock-history me-1"></i>En Cobro / Pre-cuenta</span>
                                    @else
                                        <span class="badge bg-warning text-dark fs-6">En Cocina</span>
                                    @endif
                                    @break
                                @case('dispatched')
                                    <span class="badge bg-primary text-white fs-6">En Despacho</span>
                                    @break
                                @case('delivered')
                                    <span class="badge bg-secondary text-white fs-6">Entregado</span>
                                    @break
                                @case('billed')
                                    @if($order->isFullyPaid())
                                        <span class="badge bg-success text-white fs-6"><i class="bi bi-check2-circle me-1"></i>Cerrada / Pagada</span>
                                    @else
                                        <span class="badge bg-warning text-dark fs-6"><i class="bi bi-clock-history me-1"></i>En Cobro / Pre-cuenta</span>
                                    @endif
                                    @break
                                @case('closed')
                                    <span class="badge bg-success text-white fs-6"><i class="bi bi-check2-circle me-1"></i>Cerrada / Facturada</span>
                                    @break
                                @case('cancelled')
                                    <span class="badge bg-danger text-white fs-6">Anulada</span>
                                    @break
                                @default
                                    <span class="badge bg-secondary fs-6">{{ ucfirst($order->status) }}</span>
                            @endswitch
                        </div>
                        @if($order->notes)
                            <div class="mt-2 pt-2 border-top">
                                <span class="text-muted d-block fw-semibold">Notas:</span>
                                <span class="text-dark">{{ $order->notes }}</span>
                            </div>
                        @endif
                    </div>

                    @if($order->canBeModified() && !$order->isFullyPaid())
                        <button type="button" class="btn btn-success btn-lg w-100 fw-bold text-white shadow-sm mt-3" data-bs-toggle="modal" data-bs-target="#settlementModal" {{ $activeItems->isEmpty() ? 'disabled' : '' }}>
                            <i class="bi bi-cash-coin me-1"></i> {{ $order->hasPartialPayments() ? 'Cobrar Saldo Restante ($' . number_format($order->pendingBalance(), 0, ',', '.') . ')' : 'Cobrar / Facturar' }}
                        </button>
                        @if(auth()->check() && auth()->user()->hasRole('admin') && $order->canBeModified())
                            <button type="button" class="btn btn-outline-danger w-100 fw-bold mt-2" data-bs-toggle="modal" data-bs-target="#cancelOrderModal">
                                <i class="bi bi-x-octagon-fill me-1"></i> Anular Comanda
                            </button>
                        @endif
                    @elseif($order->isCancelled())
                        <div class="alert alert-danger border-danger-subtle rounded-3 mt-3 mb-0 small text-start">
                            <div class="fw-bold mb-1 text-danger">
                                <i class="bi bi-x-circle-fill me-1"></i> Comanda Anulada Definitivamente
                            </div>
                            <div>
                                Anulada por: <strong>{{ $order->cancelledByUser?->name ?? 'Administrador' }}</strong>
                            </div>
                            @if($order->cancelled_at)
                                <div>
                                    Fecha: <strong>{{ $order->cancelled_at->format('d/m/Y H:i') }}</strong>
                                </div>
                            @endif
                            @if($order->cancellation_reason)
                                <div class="mt-2 pt-1 border-top border-danger-subtle">
                                    <span class="text-muted d-block fw-semibold">Motivo:</span>
                                    <em>{{ $order->cancellation_reason }}</em>
                                </div>
                            @endif
                        </div>
                    @elseif($order->isClosedOrBilled() && ($order->sale_id || ($order->relationLoaded('sales') && $order->sales->isNotEmpty())))
                        @php
                            $sideSaleId = $order->sale_id ?? ($order->relationLoaded('sales') && $order->sales->isNotEmpty() ? $order->sales->last()->id : null);
                        @endphp
                        @if($sideSaleId)
                            <div class="mt-3">
                                <a href="{{ route('sales.show', $sideSaleId) }}" class="btn btn-outline-primary w-100 fw-bold mb-2">
                                    <i class="bi bi-receipt me-1"></i> Ver Factura
                                </a>
                                <a href="{{ route('sales.print.receipt', $sideSaleId) }}" target="_blank" class="btn btn-outline-secondary w-100" data-no-disable="true">
                                    <i class="bi bi-printer me-1"></i> Imprimir Ticket Fiscal
                                </a>
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- MODAL DE LIQUIDACIÓN Y FACTURACIÓN INTEGRADA --}}
@if($order->canBeModified() && !$order->isFullyPaid())
<div class="modal fade" id="settlementModal" tabindex="-1" aria-labelledby="settlementModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title fw-bold" id="settlementModalLabel">
                    <i class="bi bi-cash-coin me-2"></i>Facturación y Cobro — Comanda {{ $order->order_number }}
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form action="{{ route('restaurant.orders.charge', $order) }}" method="POST" id="settlementForm">
                @csrf
                <input type="hidden" name="sale_date" value="{{ now()->format('Y-m-d\TH:i:s') }}">
                <input type="hidden" name="restaurant_order_id" value="{{ $order->id }}">
                <input type="hidden" name="order_type" value="{{ $order->order_type }}">
                <input type="hidden" name="delivery_fee" id="settlement_delivery_fee" value="{{ $order->pendingDeliveryFee() }}">

                {{-- Productos pendientes de la comanda agrupados para facturación --}}
                @php
                    $unpaidItemsList = $order->unpaidItems();
                    $groupedItems = [];
                    $pendingDishSubtotal = 0.0;
                    foreach ($unpaidItemsList as $item) {
                        $pid = $item->product_id;
                        if (!isset($groupedItems[$pid])) {
                            $groupedItems[$pid] = [
                                'product_id' => $pid,
                                'product_name' => $item->product->name ?? 'Plato',
                                'quantity' => 0,
                                'unit_price' => (float)$item->unit_price,
                            ];
                        }
                        $groupedItems[$pid]['quantity'] += (float)$item->quantity;
                        if ($item->product && $item->product->isDish()) {
                            $pendingDishSubtotal += (float)$item->quantity * (float)$item->unit_price;
                        }
                    }
                @endphp
                @php $itemIdx = 0; @endphp
                @foreach($groupedItems as $g)
                    <input type="hidden" name="items[{{ $itemIdx }}][product_id]" value="{{ $g['product_id'] }}">
                    <input type="hidden" name="items[{{ $itemIdx }}][quantity]" value="{{ $g['quantity'] }}">
                    <input type="hidden" name="items[{{ $itemIdx }}][unit_price]" value="{{ $g['unit_price'] }}">
                    @php $itemIdx++; @endphp
                @endforeach

                <div class="modal-body p-4">
                    {{-- Desglose Transparente de Pagos Parciales Previos (Si existen) --}}
                    @if($order->hasPartialPayments())
                        <div class="card bg-light border-0 p-3 mb-3 rounded-3">
                            <div class="d-flex justify-content-between text-muted small mb-1">
                                <span>Total Consumo Comanda:</span>
                                <span>${{ number_format($order->totalAmount(), 2) }}</span>
                            </div>
                            <div class="d-flex justify-content-between text-success small mb-2">
                                <span>Pagos Parciales Realizados:</span>
                                <span>- ${{ number_format($order->paidAmount(), 2) }}</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center border-top pt-2">
                                <span class="fw-bold">Total a Liquidar (Saldo Pendiente):</span>
                                <span class="fs-4 fw-bold text-primary" id="checkoutTotalDisplay">${{ number_format($order->pendingBalance(), 2) }}</span>
                            </div>
                        </div>
                    @endif

                    {{-- Datos del Comprador y Descuento --}}
                    <div class="row g-3 mb-3">
                        <div class="col-12 col-md-7">
                            <label class="form-label fw-semibold small mb-1">Cliente / Comprador:</label>
                            <div class="d-flex align-items-stretch gap-1">
                                <select name="customer_id" id="settlement_customer_id" class="form-select form-select-sm">
                                    <option value="">Consumidor Final DIAN (222222222222)</option>
                                    @foreach($customers as $c)
                                        <option value="{{ $c->id }}" {{ (old('customer_id', $order->customer_id) == $c->id) ? 'selected' : '' }}>
                                            {{ $c->name }} (Doc: {{ $c->document ?? 'N/A' }})
                                        </option>
                                    @endforeach
                                </select>
                                <button type="button" 
                                        class="btn btn-outline-primary bg-white text-primary d-inline-flex align-items-center justify-content-center px-3 flex-shrink-0 btn-open-quick-customer btn-quick-customer-trigger" 
                                        data-target-select="#settlement_customer_id"
                                        title="Crear Cliente Rápido"
                                        aria-label="Crear Cliente Rápido">
                                    <i class="bi bi-person-plus fs-5" style="line-height: 1;"></i>
                                </button>
                            </div>
                            <small class="text-muted d-block" style="font-size: 0.72rem;">Por defecto asignado a Consumidor Final</small>
                        </div>
                        <div class="col-12 col-md-5">
                            <label class="form-label small fw-semibold text-dark">Porcentaje de Descuento:</label>
                            <div class="input-group">
                                <button type="button" class="btn btn-outline-secondary px-2 btn-discount-step" data-target="#settlement_discount_pct" data-step="-5">
                                    <i class="bi bi-dash"></i>
                                </button>
                                <input type="number" 
                                       name="discount_percentage" 
                                       id="settlement_discount_pct" 
                                       class="form-control text-center fw-bold" 
                                       min="0" 
                                       max="100" 
                                       step="1" 
                                       value="0">
                                <button type="button" class="btn btn-outline-secondary px-2 btn-discount-step" data-target="#settlement_discount_pct" data-step="5">
                                    <i class="bi bi-plus"></i>
                                </button>
                                <span class="input-group-text bg-light fw-bold">%</span>
                            </div>
                            <small class="text-muted">Descuento aplicado sobre el saldo a liquidar</small>
                        </div>
                    </div>

                    {{-- Opciones Fiscales y Propinas (Servicio e INC) --}}
                    <div class="card border p-3 mb-3 rounded-3 bg-white">
                        <div class="row g-3">
                            {{-- Servicio Voluntario / Propina --}}
                            <div class="col-12 col-md-6 border-end-md">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="switch_service_fee" role="switch">
                                    <label class="form-check-label fw-semibold small text-dark" for="switch_service_fee">
                                        <i class="bi bi-heart text-danger me-1"></i> Incluir Servicio / Propina (10%)
                                    </label>
                                </div>
                                <div id="service_fee_container" class="d-none">
                                    <div class="input-group input-group-sm mb-1">
                                        <span class="input-group-text bg-light small">Tarifa:</span>
                                        <input type="number" step="0.5" min="0" max="100" id="service_fee_pct" class="form-control text-end fw-bold" value="10" style="max-width: 75px;">
                                        <span class="input-group-text bg-light">%</span>
                                        <span class="input-group-text bg-light">$</span>
                                        <input type="number" step="0.01" min="0" name="service_fee" id="settlement_service_fee" class="form-control text-end fw-bold" value="0.00">
                                    </div>
                                    <small class="text-muted d-block" style="font-size: 0.72rem;">Sugerido 10% voluntario sobre el consumo a liquidar</small>
                                </div>
                            </div>

                            {{-- Impuesto Nacional al Consumo (INC) --}}
                            <div class="col-12 col-md-6">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="switch_tax_inc" role="switch">
                                    <label class="form-check-label fw-semibold small text-dark" for="switch_tax_inc">
                                        <i class="bi bi-receipt text-primary me-1"></i> Aplicar Impuesto al Consumo (INC 8%)
                                    </label>
                                </div>
                                <div id="tax_inc_container" class="d-none">
                                    <div class="input-group input-group-sm mb-1">
                                        <span class="input-group-text bg-light small">Tarifa:</span>
                                        <input type="text" class="form-control bg-light text-center fw-bold" value="8%" readonly style="max-width: 75px;">
                                        <span class="input-group-text bg-light">$</span>
                                        <input type="number" step="0.01" min="0" name="tax_inc" id="settlement_tax_inc" class="form-control text-end fw-bold" value="0.00" readonly>
                                    </div>
                                    <small class="text-muted d-block" style="font-size: 0.72rem;">8% sobre base gravable de platos pendientes</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Resumen de Cálculo Autoritativo --}}
                    <div class="card bg-light border-0 p-3 mb-3 rounded-3">
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-muted">Subtotal Base a Cobrar ({{ count($groupedItems) }} ref):</span>
                            <span class="fw-semibold text-dark" id="display_subtotal">${{ number_format($order->pendingItemsSubtotal(), 2) }}</span>
                        </div>
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-muted">Descuento (<span id="display_discount_pct_label">0%</span>):</span>
                            <span class="fw-semibold text-danger" id="display_discount">-$0.00</span>
                        </div>
                        @if($order->pendingDeliveryFee() > 0)
                            <div class="d-flex justify-content-between small mb-1">
                                <span class="text-muted">Flete de Domicilio:</span>
                                <span class="fw-semibold text-dark" id="display_delivery_fee">+${{ number_format($order->pendingDeliveryFee(), 2) }}</span>
                            </div>
                        @endif
                        <div class="d-flex justify-content-between small mb-1" id="row_service_fee" style="display: none !important;">
                            <span class="text-muted">Servicio / Propina (<span id="display_service_pct_label">10%</span>):</span>
                            <span class="fw-semibold text-dark" id="display_service_fee">+$0.00</span>
                        </div>
                        <div class="d-flex justify-content-between small mb-1" id="row_tax_inc" style="display: none !important;">
                            <span class="text-muted">Impuesto al Consumo (INC 8%):</span>
                            <span class="fw-semibold text-dark" id="display_tax_inc">+$0.00</span>
                        </div>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-bold fs-6 text-dark">Total a Liquidar:</span>
                            <span class="fw-bold fs-4 text-primary" id="display_total">${{ number_format($order->pendingBalance(), 2) }}</span>
                        </div>
                    </div>

                    {{-- Pasarela de Pagos Mixtos --}}
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label small fw-bold text-dark mb-0">
                                <i class="bi bi-wallet2 text-primary me-1"></i>Métodos de Pago
                            </label>
                            <button type="button" class="btn btn-xs btn-outline-primary py-1 px-2 rounded-pill d-inline-flex align-items-center gap-1" id="btnAddPaymentLine" style="font-size: 0.75rem;">
                                <i class="bi bi-plus-circle"></i>
                                <span>Agregar otro medio</span>
                            </button>
                        </div>

                        {{-- Atajos rápidos de efectivo (Reactivos según saldo pendiente) --}}
                        <div class="d-flex flex-wrap gap-1 mb-2 align-items-center" id="quickCashButtons">
                            <span class="small text-muted me-1" style="font-size: 0.75rem;"><i class="bi bi-cash me-1"></i>Atajos Efectivo:</span>
                            <button type="button" class="btn btn-sm btn-outline-success py-1 px-2 quick-cash-btn fw-bold" data-amt="{{ (float)$order->pendingBalance() }}" style="font-size: 0.75rem; min-height: 32px;">
                                <i class="bi bi-check2 me-1"></i>Exacto (${{ number_format($order->pendingBalance(), 0, ',', '.') }})
                            </button>
                        </div>

                        <div id="maxPaymentsAlert" class="alert alert-warning py-1.5 px-3 small rounded-3 mb-2 d-none" role="alert">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i> Se permite un máximo de 5 medios de pago por venta.
                        </div>

                        <div id="paymentsContainer">
                            {{-- Línea 1 por defecto --}}
                            <div class="payment-line card border p-2 mb-2 rounded-3 bg-white" data-index="0">
                                <div class="row g-2 align-items-center">
                                    <div class="col-12 col-md-3">
                                        <select name="payments[0][method]" class="form-select form-select-sm payment-method-select" required>
                                            <option value="cash" selected>Efectivo</option>
                                            <option value="transfer">Transferencia / Nequi</option>
                                            <option value="card">Tarjeta</option>
                                            <option value="other">Otro</option>
                                        </select>
                                    </div>
                                    <div class="col-6 col-md-3">
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">$</span>
                                            <input type="number" step="0.01" min="0.01" name="payments[0][amount]" class="form-control text-end payment-amount-input fw-bold" value="{{ (float)$order->pendingBalance() }}" required>
                                        </div>
                                    </div>
                                    <div class="col-6 col-md-3 cash-fields-col">
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text" title="Efectivo Recibido"><i class="bi bi-box-arrow-in-down"></i></span>
                                            <input type="number" step="0.01" min="0" name="payments[0][cash_received]" class="form-control text-end payment-cash-received-input" placeholder="Recibido" value="{{ (float)$order->pendingBalance() }}">
                                        </div>
                                    </div>
                                    <div class="col-10 col-md-2 text-end text-md-center change-display-col">
                                        <span class="badge bg-light text-dark border small payment-change-display">Vuelto: $0</span>
                                        <input type="hidden" name="payments[0][change_given]" class="payment-change-given-input" value="0.00">
                                    </div>
                                    <div class="col-2 col-md-1 text-end">
                                        <button type="button" class="btn btn-sm btn-outline-danger remove-payment-btn" disabled>
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="row g-2 mt-1 reference-row" style="display: none;">
                                    <div class="col-12">
                                        <input type="text" name="payments[0][reference]" class="form-control form-control-sm payment-reference-input" placeholder="N° Comprobante / Referencia (opcional)" maxlength="60">
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Validación de diferencia de balance --}}
                        <div id="paymentBalanceAlert" class="alert alert-warning py-2 px-3 small d-none rounded-3 mt-2">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i>
                            <span id="paymentBalanceMessage">La suma de los pagos no coincide con el total.</span>
                        </div>
                    </div>

                    {{-- Notas Opcionales --}}
                    <div>
                        <label class="form-label small fw-semibold text-muted">Observaciones de Facturación (Opcional):</label>
                        <input type="text" name="notes" class="form-control form-control-sm" placeholder="Ej: Mesa VIP, cliente frecuente" maxlength="255">
                    </div>
                </div>

                <div class="modal-footer bg-light py-3">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success fw-bold px-4" id="btnSubmitSettlement">
                        <i class="bi bi-check2-circle me-1"></i> {{ $order->hasPartialPayments() ? 'Cobrar Saldo y Cerrar Comanda' : 'Confirmar Pago y Generar Factura' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal Estandarizado de Creación Rápida de Cliente --}}
<div class="modal fade" id="quickCustomerModal" tabindex="-1" aria-labelledby="quickCustomerModalLabel" aria-hidden="true" style="z-index: 1065;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold" id="quickCustomerModalLabel">
                    <i class="bi bi-person-plus text-primary me-2"></i>Crear Cliente Rápido
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <div id="quickCustomerErrors" class="alert alert-danger d-none small mb-3"></div>

                <div class="mb-3">
                    <label for="quick_customer_document" class="form-label small fw-semibold text-muted">Cédula o NIT <span class="text-danger">*</span></label>
                    <input type="text" id="quick_customer_document" class="form-control font-monospace" placeholder="Ej: 1144001234 o 900123456-1" minlength="5" maxlength="15" inputmode="numeric" autocomplete="off" required>
                    <div class="form-text small">Solo dígitos (5–15) y guión opcional con dígito de verificación.</div>
                </div>

                <div class="mb-3">
                    <label for="quick_customer_name" class="form-label small fw-semibold text-muted">Nombre o Razón Social <span class="text-danger">*</span></label>
                    <input type="text" id="quick_customer_name" class="form-control" placeholder="Nombre completo o razón social" minlength="2" maxlength="100" autocomplete="off" required>
                    <div class="form-text small">Debe contener letras (no se permiten únicamente números).</div>
                </div>

                <div class="mb-3">
                    <label for="quick_customer_phone" class="form-label small fw-semibold text-muted">Teléfono <span class="text-muted">(opcional)</span></label>
                    <input type="tel" id="quick_customer_phone" class="form-control font-monospace" placeholder="Ej: 3151234567" minlength="7" maxlength="15" inputmode="tel" autocomplete="off">
                    <div class="form-text small">Solo números y símbolos telefónicos (+, -).</div>
                </div>

                <div class="mb-3">
                    <label for="quick_customer_email" class="form-label small fw-semibold text-muted">Correo Electrónico <span class="text-muted">(opcional)</span></label>
                    <input type="email" id="quick_customer_email" class="form-control" placeholder="cliente@ejemplo.com">
                </div>
            </div>
            <div class="modal-footer border-top bg-light">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" id="btn_save_quick_customer" class="btn btn-primary rounded-pill px-4 fw-semibold">
                    <i class="bi bi-box-arrow-in-down me-1"></i> Guardar Cliente
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const quickCustomerModalEl = document.getElementById('quickCustomerModal');
    if (!quickCustomerModalEl) return;

    let currentTargetCustomerSelect = null;

    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-open-quick-customer');
        if (!btn) return;
        currentTargetCustomerSelect = btn.getAttribute('data-target-select');

        const errBox = document.getElementById('quickCustomerErrors');
        if (errBox) {
            errBox.classList.add('d-none');
            errBox.innerHTML = '';
        }

        const modalInstance = bootstrap.Modal.getOrCreateInstance(quickCustomerModalEl);
        modalInstance.show();
    });

    quickCustomerModalEl.addEventListener('show.bs.modal', function () {
        setTimeout(function() {
            const backdrops = document.querySelectorAll('.modal-backdrop');
            if (backdrops.length > 1) {
                backdrops[backdrops.length - 1].style.zIndex = '1060';
            }
        }, 10);
    });

    quickCustomerModalEl.addEventListener('hidden.bs.modal', function () {
        const openModals = document.querySelectorAll('.modal.show');
        if (openModals.length > 0) {
            document.body.classList.add('modal-open');
            document.body.style.overflow = 'hidden';
        }
    });

    const btnSaveQuickCust = document.getElementById('btn_save_quick_customer');
    if (btnSaveQuickCust) {
        btnSaveQuickCust.addEventListener('click', function () {
            const docInput = document.getElementById('quick_customer_document');
            const nameInput = document.getElementById('quick_customer_name');
            const phoneInput = document.getElementById('quick_customer_phone');
            const emailInput = document.getElementById('quick_customer_email');
            const errBox = document.getElementById('quickCustomerErrors');

            const docVal = (docInput ? docInput.value : '').trim();
            const nameVal = (nameInput ? nameInput.value : '').trim();
            const phoneVal = (phoneInput ? phoneInput.value : '').trim();
            const emailVal = (emailInput ? emailInput.value : '').trim();

            if (errBox) {
                errBox.classList.add('d-none');
                errBox.innerHTML = '';
            }

            btnSaveQuickCust.disabled = true;
            btnSaveQuickCust.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando...';

            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') 
                || document.querySelector('input[name="_token"]')?.value 
                || '{{ csrf_token() }}';

            fetch('{{ route("customers.store") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    document: docVal,
                    name: nameVal,
                    phone: phoneVal || null,
                    email: emailVal || null,
                    is_active: true
                })
            })
            .then(async function (response) {
                const data = await response.json();
                btnSaveQuickCust.disabled = false;
                btnSaveQuickCust.innerHTML = '<i class="bi bi-box-arrow-in-down me-1"></i> Guardar Cliente';

                if (!response.ok) {
                    if (errBox) {
                        const ul = document.createElement('ul');
                        ul.className = 'mb-0 ps-3';
                        if (data.errors) {
                            Object.values(data.errors).forEach(function(errArr) {
                                errArr.forEach(function(errText) {
                                    const li = document.createElement('li');
                                    li.textContent = errText;
                                    ul.appendChild(li);
                                });
                            });
                        } else if (data.message) {
                            const li = document.createElement('li');
                            li.textContent = data.message;
                            ul.appendChild(li);
                        }
                        errBox.appendChild(ul);
                        errBox.classList.remove('d-none');
                    }
                    return;
                }

                // Éxito: agregar la opción a ambos selectores de cliente
                const labelText = data.name + ' (Doc: ' + (data.document || 'N/A') + ')';
                const selects = [
                    document.getElementById('settlement_customer_id'),
                    document.getElementById('split_customer_id')
                ];

                selects.forEach(function(selectEl) {
                    if (selectEl) {
                        let existingOpt = selectEl.querySelector('option[value="' + data.id + '"]');
                        if (!existingOpt) {
                            existingOpt = document.createElement('option');
                            existingOpt.value = data.id;
                            existingOpt.textContent = labelText;
                            selectEl.appendChild(existingOpt);
                        }
                    }
                });

                if (currentTargetCustomerSelect) {
                    const targetSelect = document.querySelector(currentTargetCustomerSelect);
                    if (targetSelect) {
                        targetSelect.value = data.id;
                        targetSelect.dispatchEvent(new Event('change'));
                    }
                } else {
                    selects.forEach(function(s) {
                        if (s) {
                            s.value = data.id;
                            s.dispatchEvent(new Event('change'));
                        }
                    });
                }

                if (docInput) docInput.value = '';
                if (nameInput) nameInput.value = '';
                if (phoneInput) phoneInput.value = '';
                if (emailInput) emailInput.value = '';

                const modalInstance = bootstrap.Modal.getInstance(quickCustomerModalEl);
                if (modalInstance) {
                    modalInstance.hide();
                }
            })
            .catch(function (err) {
                btnSaveQuickCust.disabled = false;
                btnSaveQuickCust.innerHTML = '<i class="bi bi-box-arrow-in-down me-1"></i> Guardar Cliente';
                if (errBox) {
                    errBox.textContent = 'Ocurrió un error al procesar la solicitud. Intente nuevamente.';
                    errBox.classList.remove('d-none');
                }
            });
        });
    }
});
</script>
@endif

@if(!in_array($order->status, ['closed', 'cancelled']))
<template id="more-row-template">
    <div class="card bg-light border-0 mb-3 new-item-row p-3 rounded-3">
        <div class="row g-2 align-items-center">
            <div class="col-12 col-md-5">
                <label class="form-label small fw-semibold">Plato / Producto <span class="text-danger">*</span></label>
                <div class="position-relative mb-1">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" class="form-control form-control-sm border-start-0 product-search-input" placeholder="Escribe para buscar plato o bebida..." autocomplete="off">
                    </div>
                    <div class="product-dropdown-list list-group position-absolute w-100 shadow-lg border rounded-3 overflow-auto d-none" style="max-height: 220px; z-index: 1050; top: 100%; left: 0; background: #fff;"></div>
                </div>
                <select class="form-select form-select-sm product-select" name="items[INDEX][product_id]" required>
                    <option value="" disabled selected>-- O selecciona del menú agrupado --</option>
                    @foreach($groupedProducts as $categoryName => $catProducts)
                        <optgroup label="{{ $categoryName }}">
                            @foreach($catProducts as $prod)
                                @php
                                    $isStd = $prod->isStandard();
                                    $availStock = (float) ($prod->available_stock ?? $prod->stock);
                                    $availStockFormatted = (float)$availStock == (int)$availStock ? (int)$availStock : $availStock;
                                @endphp
                                <option value="{{ $prod->id }}" 
                                        data-category="{{ $categoryName }}" 
                                        data-price="{{ $prod->sale_price }}"
                                        data-name="{{ $prod->name }}"
                                        data-is-standard="{{ $isStd ? '1' : '0' }}"
                                        data-stock="{{ $availStock }}"
                                        {{ ($isStd && $availStock <= 0) ? 'disabled' : '' }}>
                                    @if($isStd)
                                        @if($availStock <= 0)
                                            {{ $prod->name }} — ${{ number_format($prod->sale_price, 0, ',', '.') }}  ·  Agotado
                                        @else
                                            {{ $prod->name }} — ${{ number_format($prod->sale_price, 0, ',', '.') }}  ·  {{ $availStockFormatted }} disp.
                                        @endif
                                    @else
                                        {{ $prod->name }} — ${{ number_format($prod->sale_price, 0, ',', '.') }}
                                    @endif
                                </option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-semibold">Cantidad <span class="text-danger">*</span></label>
                <div class="input-group input-group-sm" style="max-width: 130px;">
                    <button type="button" class="btn btn-outline-secondary btn-qty-minus fw-bold px-2">−</button>
                    <input type="number" name="items[INDEX][quantity]" class="form-control text-center input-qty input-stepper" value="1" min="1" max="999" required>
                    <button type="button" class="btn btn-outline-secondary btn-qty-plus fw-bold px-2">+</button>
                </div>
                <span class="stock-hint badge rounded-pill bg-light text-secondary border mt-1 d-none" style="font-size: 0.7rem; font-weight: 500;"></span>
            </div>
            <div class="col-6 col-md-4">
                <label class="form-label small fw-semibold">Observación</label>
                <input type="text" class="form-control form-select-sm" name="items[INDEX][notes]" placeholder="Ej: Sin cebolla, término 3/4">
            </div>
            <div class="col-12 col-md-1 text-end pt-md-4">
                <button type="button" class="btn btn-sm btn-outline-danger remove-row-btn">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        </div>
    </div>
</template>
@endif

<script>
document.addEventListener('DOMContentLoaded', function () {
    function showStockErrorBanner(productName, availableStock, requestedQuantity) {
        const errorContainer = document.getElementById('stock-error-container');
        if (!errorContainer) return;

        let existingBanner = document.getElementById('stock-error-banner');
        if (existingBanner) {
            const listMsg = existingBanner.querySelector('.stock-error-msg');
            if (listMsg) {
                listMsg.textContent = `Stock insuficiente para '${productName}'. Existencias disponibles: ${availableStock}, Cantidad solicitada: ${requestedQuantity}.`;
            }
            return;
        }

        const banner = document.createElement('div');
        banner.id = 'stock-error-banner';
        banner.className = 'alert shadow-sm d-flex align-items-start gap-3 mb-3 w-100';
        banner.style.cssText = 'background-color: #fef2f2; border: 1px solid #fecaca; border-left: 4px solid #ef4444; color: #991b1b; border-radius: 8px; padding: 12px 16px; position: relative;';
        banner.setAttribute('role', 'alert');
        banner.innerHTML = `
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1" viewBox="0 0 16 16">
                <path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/>
            </svg>
            <div class="flex-grow-1">
                <div class="fw-bold mb-1" style="font-size: 14px;">Error al procesar:</div>
                <ul class="mb-0 ps-3" style="font-size: 13px; list-style-type: disc;">
                    <li class="stock-error-msg">Stock insuficiente para '${productName}'. Existencias disponibles: ${availableStock}, Cantidad solicitada: ${requestedQuantity}.</li>
                </ul>
            </div>
            <button type="button" class="btn-close" style="font-size: 10px; margin-top: 2px;" aria-label="Close" onclick="this.closest('#stock-error-banner').remove();"></button>
        `;

        errorContainer.appendChild(banner);
        banner.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function updateStockHint(row, opt) {
        const hint = row.querySelector('.stock-hint');
        if (!hint) return;
        if (!opt || !opt.value) {
            hint.classList.add('d-none');
            return;
        }
        const isStd = opt.getAttribute('data-is-standard') === '1';
        if (isStd) {
            const stock = parseFloat(opt.getAttribute('data-stock') || '0');
            const stockFormatted = Number.isInteger(stock) ? stock : stock.toFixed(2);
            hint.textContent = `${stockFormatted} disp.`;
            hint.className = 'stock-hint badge rounded-pill mt-1 ' + (stock > 0 ? 'bg-light text-secondary border' : 'bg-danger-subtle text-danger border border-danger-subtle');
            hint.classList.remove('d-none');
        } else {
            hint.classList.add('d-none');
        }
    }

    function checkRowStock(row, requestedQty) {
        const select = row.querySelector('.product-select');
        if (!select || select.selectedIndex < 0) return requestedQty;
        const opt = select.options[select.selectedIndex];
        if (!opt || !opt.value) return requestedQty;

        const isStd = opt.getAttribute('data-is-standard') === '1';
        if (!isStd) return requestedQty;

        const stock = parseFloat(opt.getAttribute('data-stock') || '0');
        const name = opt.getAttribute('data-name') || opt.textContent.trim();

        if (requestedQty > stock) {
            const maxAllowed = Math.max(1, Math.floor(stock));
            showStockErrorBanner(name, stock, requestedQty);
            return maxAllowed;
        }
        return requestedQty;
    }

    // Manejo de botones de cantidad (+ / -)
    function attachQuantityControls(row) {
        const btnMinus = row.querySelector('.btn-qty-minus');
        const btnPlus = row.querySelector('.btn-qty-plus');
        const qtyInput = row.querySelector('.input-qty');
        if (!btnMinus || !btnPlus || !qtyInput) return;

        btnMinus.addEventListener('click', function () {
            let val = parseInt(qtyInput.value) || 1;
            if (val > 1) {
                qtyInput.value = val - 1;
                qtyInput.dispatchEvent(new Event('input', { bubbles: true }));
                qtyInput.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });

        btnPlus.addEventListener('click', function () {
            let val = (parseInt(qtyInput.value) || 1) + 1;
            const adjusted = checkRowStock(row, val);
            qtyInput.value = adjusted;
            qtyInput.dispatchEvent(new Event('input', { bubbles: true }));
            qtyInput.dispatchEvent(new Event('change', { bubbles: true }));
        });

        qtyInput.addEventListener('change', function () {
            let val = parseInt(this.value) || 1;
            if (val < 1) val = 1;
            const adjusted = checkRowStock(row, val);
            this.value = adjusted;
        });

        qtyInput.addEventListener('input', function () {
            let val = parseInt(this.value);
            if (!isNaN(val)) {
                const adjusted = checkRowStock(row, val);
                if (adjusted !== val) {
                    this.value = adjusted;
                }
            }
        });
    }

    // Manejo de filtrado rápido y combobox instantáneo de platos
    function attachProductSearch(row) {
        const searchInput = row.querySelector('.product-search-input');
        const select = row.querySelector('.product-select');
        const dropdown = row.querySelector('.product-dropdown-list');
        if (!searchInput || !select || !dropdown) return;

        // Normalización insensible a mayúsculas y acentos
        function normalizeStr(str) {
            return (str || '')
                .normalize("NFD")
                .replace(/[\u0300-\u036f]/g, "")
                .toLowerCase();
        }

        // Extraer lista de opciones
        const productsList = [];
        select.querySelectorAll('option').forEach(opt => {
            if (opt.value) {
                const rawText = opt.textContent.trim();
                const category = opt.getAttribute('data-category') || opt.closest('optgroup')?.label || 'General';
                const price = opt.getAttribute('data-price') || '';
                const cleanName = opt.getAttribute('data-name') || rawText.replace(/\s*\(\$[\d,\.]+\)\s*$/, '').trim();
                const isStandard = opt.getAttribute('data-is-standard') === '1';
                const stock = parseFloat(opt.getAttribute('data-stock') || '0');
                const disabled = opt.disabled;
                productsList.push({
                    id: opt.value,
                    fullName: rawText,
                    name: cleanName,
                    category: category,
                    price: price,
                    isStandard: isStandard,
                    stock: stock,
                    disabled: disabled
                });
            }
        });

        let highlightedIndex = -1;

        function updateHighlight(items) {
            items.forEach((it, idx) => {
                if (idx === highlightedIndex) {
                    it.classList.add('active', 'bg-primary-subtle');
                    it.scrollIntoView({ block: 'nearest' });
                } else {
                    it.classList.remove('active', 'bg-primary-subtle');
                }
            });
        }

        function renderMatches(q) {
            dropdown.innerHTML = '';
            highlightedIndex = -1;
            if (!q) {
                dropdown.classList.add('d-none');
                return;
            }

            const normalizedQ = normalizeStr(q);
            const matches = productsList.filter(p => 
                normalizeStr(p.name).includes(normalizedQ) || 
                normalizeStr(p.category).includes(normalizedQ)
            );

            if (matches.length === 0) {
                const emptyItem = document.createElement('div');
                emptyItem.className = 'list-group-item text-muted small py-3 px-3 text-center';
                emptyItem.textContent = 'No se encontraron platos coincidentes';
                dropdown.appendChild(emptyItem);
                dropdown.classList.remove('d-none');
                return;
            }

            matches.forEach((p, idx) => {
                const itemBtn = document.createElement('button');
                itemBtn.type = 'button';
                itemBtn.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center text-start py-2 px-3 border-0 border-bottom product-result-item';
                itemBtn.style.minHeight = '44px';
                itemBtn.dataset.index = idx;

                if (p.isStandard && p.disabled) {
                    itemBtn.classList.add('disabled', 'opacity-50');
                    itemBtn.disabled = true;
                }

                const leftDiv = document.createElement('div');
                leftDiv.className = 'pe-2';

                const nameSpan = document.createElement('span');
                nameSpan.className = 'fw-semibold text-dark d-block';
                nameSpan.textContent = p.name;
                leftDiv.appendChild(nameSpan);

                if (p.category) {
                    const catBadge = document.createElement('span');
                    catBadge.className = 'badge bg-light text-secondary border';
                    catBadge.style.fontSize = '0.72rem';
                    catBadge.textContent = p.category;
                    leftDiv.appendChild(catBadge);
                }

                const rightDiv = document.createElement('div');
                rightDiv.className = 'text-end d-flex flex-column align-items-end justify-content-center';

                const priceSpan = document.createElement('span');
                priceSpan.className = 'fw-bold text-primary small';
                priceSpan.textContent = p.price ? '$' + Number(p.price).toLocaleString('es-CO', {minimumFractionDigits: 0, maximumFractionDigits: 0}) : '';
                rightDiv.appendChild(priceSpan);

                if (p.isStandard) {
                    const stockBadge = document.createElement('span');
                    stockBadge.style.fontSize = '0.72rem';
                    if (p.stock <= 0) {
                        stockBadge.className = 'badge bg-danger-subtle text-danger border border-danger-subtle mt-1';
                        stockBadge.textContent = 'Agotado';
                    } else {
                        stockBadge.className = 'badge bg-light text-secondary border mt-1';
                        const stockFmt = Number.isInteger(p.stock) ? p.stock : p.stock.toFixed(2);
                        stockBadge.textContent = `${stockFmt} disp.`;
                    }
                    rightDiv.appendChild(stockBadge);
                }

                itemBtn.appendChild(leftDiv);
                itemBtn.appendChild(rightDiv);

                function selectItem() {
                    select.value = p.id;
                    select.dispatchEvent(new Event('change', { bubbles: true }));
                    searchInput.value = p.fullName;
                    dropdown.classList.add('d-none');
                    highlightedIndex = -1;

                    const qtyInput = row.querySelector('.input-qty');
                    const notesInput = row.querySelector('input[name*="[notes]"]');
                    if (qtyInput) {
                        qtyInput.focus();
                        try {
                            qtyInput.select();
                        } catch (err) {}
                    } else if (notesInput) {
                        notesInput.focus();
                    }
                }

                itemBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    selectItem();
                });

                itemBtn.addEventListener('mouseenter', function () {
                    highlightedIndex = idx;
                    const items = dropdown.querySelectorAll('button.product-result-item');
                    updateHighlight(items);
                });

                dropdown.appendChild(itemBtn);
            });

            dropdown.classList.remove('d-none');
        }

        searchInput.addEventListener('input', function () {
            renderMatches(this.value.trim());
        });

        searchInput.addEventListener('focus', function () {
            if (this.value.trim().length > 0) {
                renderMatches(this.value.trim());
            }
        });

        searchInput.addEventListener('keydown', function (e) {
            const items = dropdown.querySelectorAll('button.product-result-item');
            const isVisible = !dropdown.classList.contains('d-none') && items.length > 0;

            if (e.key === 'ArrowDown' || e.keyCode === 40) {
                if (isVisible) {
                    e.preventDefault();
                    if (highlightedIndex < items.length - 1) {
                        highlightedIndex++;
                    } else {
                        highlightedIndex = 0;
                    }
                    updateHighlight(items);
                }
            } else if (e.key === 'ArrowUp' || e.keyCode === 38) {
                if (isVisible) {
                    e.preventDefault();
                    if (highlightedIndex > 0) {
                        highlightedIndex--;
                    } else {
                        highlightedIndex = items.length - 1;
                    }
                    updateHighlight(items);
                }
            } else if (e.key === 'Enter' || e.keyCode === 13) {
                e.preventDefault(); // Prevenir estrictamente el submit accidental del formulario
                if (isVisible) {
                    const targetBtn = (highlightedIndex >= 0 && items[highlightedIndex])
                        ? items[highlightedIndex]
                        : items[0];
                    if (targetBtn) {
                        targetBtn.click();
                    }
                }
            } else if (e.key === 'Escape' || e.keyCode === 27) {
                dropdown.classList.add('d-none');
                highlightedIndex = -1;
            }
        });

        document.addEventListener('click', function (e) {
            if (!row.contains(e.target)) {
                dropdown.classList.add('d-none');
                highlightedIndex = -1;
            }
        });

        select.addEventListener('change', function () {
            const opt = select.options[select.selectedIndex];
            if (opt && opt.value) {
                searchInput.value = opt.textContent.trim();
                updateStockHint(row, opt);
                const qtyInput = row.querySelector('.input-qty');
                if (qtyInput) {
                    let currentVal = parseInt(qtyInput.value) || 1;
                    const adjusted = checkRowStock(row, currentVal);
                    qtyInput.value = adjusted;
                }
            } else {
                updateStockHint(row, null);
            }
            dropdown.classList.add('d-none');
            highlightedIndex = -1;
        });
    }

    // Inicializar controles en filas existentes
    document.querySelectorAll('.new-item-row').forEach(row => {
        attachQuantityControls(row);
        attachProductSearch(row);
        const sel = row.querySelector('.product-select');
        if (sel && sel.selectedIndex >= 0) {
            updateStockHint(row, sel.options[sel.selectedIndex]);
        }
    });

    // 1. Manejo de líneas dinámicas para agregar platos
    const container = document.getElementById('new-items-container');
    const template = document.getElementById('more-row-template');
    const addBtn = document.getElementById('btn-add-more-row');
    let rowIndex = 1;

    if (addBtn && template && container) {
        addBtn.addEventListener('click', function () {
            const clone = template.content.cloneNode(true);
            const row = clone.querySelector('.new-item-row');
            row.innerHTML = row.innerHTML.replace(/INDEX/g, rowIndex);
            
            row.querySelector('.remove-row-btn').addEventListener('click', function () {
                row.remove();
            });

            attachQuantityControls(row);
            attachProductSearch(row);
            const sel = row.querySelector('.product-select');
            if (sel && sel.selectedIndex >= 0) {
                updateStockHint(row, sel.options[sel.selectedIndex]);
            }

            container.appendChild(clone);
            rowIndex++;
        });
    }

    // 2. Lógica Reactiva de la Pasarela de Liquidación / Cobro
    const BASE_ITEMS_PENDING = parseFloat("{{ $order->pendingItemsSubtotal() }}") || 0;
    const DELIVERY_FEE = parseFloat("{{ $order->pendingDeliveryFee() }}") || 0;
    const subtotal = BASE_ITEMS_PENDING;
    const dishSubtotal = parseFloat("{{ $pendingDishSubtotal ?? 0 }}") || 0;
    const deliveryFee = DELIVERY_FEE;
    const discountInput = document.getElementById('settlement_discount_pct');
    const displaySubtotal = document.getElementById('display_subtotal');
    const displayDiscount = document.getElementById('display_discount');
    const displayDiscountLabel = document.getElementById('display_discount_pct_label');
    const displayTotal = document.getElementById('display_total');

    const switchServiceFee = document.getElementById('switch_service_fee');
    const serviceFeeContainer = document.getElementById('service_fee_container');
    const serviceFeePctInput = document.getElementById('service_fee_pct');
    const serviceFeeInput = document.getElementById('settlement_service_fee');
    const rowServiceFee = document.getElementById('row_service_fee');
    const displayServiceFee = document.getElementById('display_service_fee');
    const displayServicePctLabel = document.getElementById('display_service_pct_label');

    const switchTaxInc = document.getElementById('switch_tax_inc');
    const taxIncContainer = document.getElementById('tax_inc_container');
    const taxIncInput = document.getElementById('settlement_tax_inc');
    const rowTaxInc = document.getElementById('row_tax_inc');
    const displayTaxInc = document.getElementById('display_tax_inc');

    const paymentsContainer = document.getElementById('paymentsContainer');
    const btnAddPaymentLine = document.getElementById('btnAddPaymentLine');
    const balanceAlert = document.getElementById('paymentBalanceAlert');
    const balanceMessage = document.getElementById('paymentBalanceMessage');
    const btnSubmitSettlement = document.getElementById('btnSubmitSettlement');
    const quickCashContainer = document.getElementById('quickCashButtons');

    let currentTotal = Math.max(0, Math.round((BASE_ITEMS_PENDING + DELIVERY_FEE) * 100) / 100);
    let paymentLineCount = 1;

    function formatMoney(amount) {
        const num = Number(amount) || 0;
        const hasDecimals = (num % 1 !== 0);
        return '$' + num.toLocaleString('es-CO', {
            minimumFractionDigits: hasDecimals ? 2 : 0,
            maximumFractionDigits: 2
        });
    }

    function formatInputValue(num) {
        const val = Number(num) || 0;
        return (val % 1 === 0) ? String(Math.round(val)) : val.toFixed(2);
    }

    function recalculateTotal(source) {
        // Blindaje normativo colombiano de liquidación (Gastronomía y Restaurantes):
        // 1. Base Gravable / Consumo neto = Subtotal Platos - Descuento (Art. 512-1 del Estatuto Tributario).
        // 2. Propina Voluntaria / Servicio (Ley 1935 de 2018 y Circular Única SIC):
        //    Liberalidad de los trabajadores. NO hace parte de la base gravable de INC ni IVA.
        // 3. Impuesto Nacional al Consumo - INC 8% (Art. 512-1 E.T.):
        //    Grava exclusivamente el consumo neto de platos preparados del menú (dishSubtotal).
        // 4. Total a Liquidar = Base Gravable (Subtotal Platos - Descuento) + Flete Domicilio + Servicio + INC.
        const discountPct = parseFloat(discountInput?.value || 0) || 0;
        const discountAmount = Math.round(BASE_ITEMS_PENDING * (discountPct / 100) * 100) / 100;
        const netFoodBase = Math.max(0, Math.round((BASE_ITEMS_PENDING - discountAmount) * 100) / 100);

        // Descuento proporcional sobre platos preparados
        const dishDiscountAmount = BASE_ITEMS_PENDING > 0 ? Math.round(dishSubtotal * (discountPct / 100) * 100) / 100 : 0;
        const netDishBase = Math.max(0, Math.round((dishSubtotal - dishDiscountAmount) * 100) / 100);

        // Servicio / Propina (Ley 1935 de 2018 - 10% sugerido editable o monto en pesos)
        let serviceFee = 0;
        if (switchServiceFee?.checked) {
            serviceFeeContainer?.classList.remove('d-none');
            if (source === 'service_amount') {
                serviceFee = Math.max(0, parseFloat(serviceFeeInput?.value || 0) || 0);
                if (netFoodBase > 0) {
                    const derivedPct = Math.round((serviceFee / netFoodBase) * 1000) / 10;
                    if (serviceFeePctInput) serviceFeePctInput.value = derivedPct;
                }
            } else {
                const servicePct = parseFloat(serviceFeePctInput?.value || 10) || 0;
                serviceFee = Math.round(netFoodBase * (servicePct / 100) * 100) / 100;
                if (serviceFeeInput) serviceFeeInput.value = formatInputValue(serviceFee);
            }
            if (rowServiceFee) rowServiceFee.style.setProperty('display', 'flex', 'important');
            if (displayServiceFee) displayServiceFee.textContent = '+' + formatMoney(serviceFee);
            if (displayServicePctLabel) displayServicePctLabel.textContent = (parseFloat(serviceFeePctInput?.value || 10)) + '%';
        } else {
            serviceFeeContainer?.classList.add('d-none');
            if (serviceFeeInput) serviceFeeInput.value = '0';
            if (rowServiceFee) rowServiceFee.style.setProperty('display', 'none', 'important');
        }

        // Impuesto Nacional al Consumo (INC 8% Art. 512-1 E.T. sobre platos preparados)
        let taxInc = 0;
        if (switchTaxInc?.checked) {
            taxIncContainer?.classList.remove('d-none');
            taxInc = Math.round(netDishBase * 0.08 * 100) / 100;
            if (taxIncInput) taxIncInput.value = formatInputValue(taxInc);
            if (rowTaxInc) rowTaxInc.style.setProperty('display', 'flex', 'important');
            if (displayTaxInc) displayTaxInc.textContent = '+' + formatMoney(taxInc);
        } else {
            taxIncContainer?.classList.add('d-none');
            if (taxIncInput) taxIncInput.value = '0';
            if (rowTaxInc) rowTaxInc.style.setProperty('display', 'none', 'important');
        }

        currentTotal = Math.max(0, Math.round((netFoodBase + DELIVERY_FEE + serviceFee + taxInc) * 100) / 100);

        if (displayDiscount) displayDiscount.textContent = '-' + formatMoney(discountAmount);
        if (displayDiscountLabel) displayDiscountLabel.textContent = discountPct + '%';
        if (displayTotal) displayTotal.textContent = formatMoney(currentTotal);
        const checkoutTotalDisplay = document.getElementById('checkoutTotalDisplay');
        if (checkoutTotalDisplay) checkoutTotalDisplay.textContent = formatMoney(currentTotal);

        // Si solo hay una línea de pago, sincronizarla con el nuevo total
        const lines = paymentsContainer?.querySelectorAll('.payment-line') || [];
        if (lines.length === 1) {
            const firstAmtInput = lines[0].querySelector('.payment-amount-input');
            const firstCashRecInput = lines[0].querySelector('.payment-cash-received-input');
            const methodSelect = lines[0].querySelector('.payment-method-select');
            if (firstAmtInput) firstAmtInput.value = formatInputValue(currentTotal);
            if (methodSelect?.value === 'cash' && firstCashRecInput) {
                const currentRec = parseFloat(firstCashRecInput.value || 0) || 0;
                if (currentRec < currentTotal || currentRec === 0) {
                    firstCashRecInput.value = formatInputValue(currentTotal);
                }
            }
        }

        recalculatePaymentsBalance();
        renderQuickCashButtons();
    }

    function recalculatePaymentsBalance() {
        if (!paymentsContainer) return;

        const lines = paymentsContainer.querySelectorAll('.payment-line');
        let sumPaymentsCents = 0;
        let hasCashError = false;

        lines.forEach(line => {
            const methodSelect = line.querySelector('.payment-method-select');
            const amtInput = line.querySelector('.payment-amount-input');
            const cashRecInput = line.querySelector('.payment-cash-received-input');
            const changeDisplay = line.querySelector('.payment-change-display');
            const changeGivenInput = line.querySelector('.payment-change-given-input');

            const amt = parseFloat(amtInput?.value || 0) || 0;
            sumPaymentsCents += Math.round(amt * 100);

            if (methodSelect?.value === 'cash') {
                const recVal = cashRecInput?.value;
                const rec = (recVal !== '' && recVal !== null) ? (parseFloat(recVal) || 0) : amt;
                const change = Math.max(0, Math.round((rec - amt) * 100) / 100);
                if (changeDisplay) changeDisplay.textContent = 'Vuelto: ' + formatMoney(change);
                if (changeGivenInput) changeGivenInput.value = formatInputValue(change);

                // Tolerancia de 0.01 en la comparación
                if ((amt - rec) > 0.01) {
                    hasCashError = true;
                    if (changeDisplay) changeDisplay.textContent = 'Falta dinero';
                }
            }
        });

        const totalCents = Math.round(currentTotal * 100);
        const diffCents = totalCents - sumPaymentsCents;
        const diff = diffCents / 100;

        if (Math.abs(diff) > 0.009 || hasCashError) {
            if (balanceAlert) {
                balanceAlert.classList.remove('d-none');
                if (hasCashError) {
                    balanceAlert.className = 'alert alert-danger py-2 px-3 small rounded-3 mt-2';
                    balanceMessage.innerHTML = '<i class="bi bi-exclamation-octagon-fill me-1"></i>El efectivo recibido no puede ser inferior al monto asignado.';
                } else if (diff > 0.009) {
                    balanceAlert.className = 'alert alert-warning py-2 px-3 small rounded-3 mt-2';
                    balanceMessage.innerHTML = `<i class="bi bi-exclamation-triangle-fill me-1"></i><strong>Falta cubrir:</strong> ${formatMoney(diff)} (Total a cubrir: ${formatMoney(currentTotal)}).`;
                } else {
                    balanceAlert.className = 'alert alert-danger py-2 px-3 small rounded-3 mt-2';
                    balanceMessage.innerHTML = `<i class="bi bi-exclamation-octagon-fill me-1"></i><strong>Monto asignado excede el total en:</strong> ${formatMoney(Math.abs(diff))}.`;
                }
            }
            if (btnSubmitSettlement) btnSubmitSettlement.disabled = true;
        } else {
            if (balanceAlert) {
                balanceAlert.classList.remove('d-none');
                balanceAlert.className = 'alert alert-success py-2 px-3 small rounded-3 mt-2';
                balanceMessage.innerHTML = `<i class="bi bi-check-circle-fill me-1"></i><strong>Total cubierto exactamente:</strong> ${formatMoney(currentTotal)}.`;
            }
            if (btnSubmitSettlement) btnSubmitSettlement.disabled = false;
        }
    }

    const calculatePaymentTotals = recalculatePaymentsBalance;
    window.calculatePaymentTotals = recalculatePaymentsBalance;

    function renderQuickCashButtons() {
        if (!quickCashContainer) return;

        const firstCashLine = Array.from(paymentsContainer.querySelectorAll('.payment-line'))
            .find(l => l.querySelector('.payment-method-select')?.value === 'cash');

        if (!firstCashLine) {
            quickCashContainer.innerHTML = '';
            quickCashContainer.classList.add('d-none');
            return;
        }

        quickCashContainer.classList.remove('d-none');
        const amtInput = firstCashLine.querySelector('.payment-amount-input');
        const cashAmt = parseFloat(amtInput?.value || 0) || 0;

        // Sugerencias de billetes redondeados hacia arriba según denominaciones colombianas
        const suggestions = [];
        if (cashAmt > 0) {
            const steps = [10000, 20000, 50000, 100000];
            steps.forEach(step => {
                const nextMultiple = Math.ceil(cashAmt / step) * step;
                if (nextMultiple > cashAmt && !suggestions.includes(nextMultiple)) {
                    suggestions.push(nextMultiple);
                }
            });

            if (suggestions.length < 2) {
                const plus50 = Math.ceil((cashAmt + 1) / 50000) * 50000;
                const plus100 = Math.ceil((cashAmt + 1) / 100000) * 100000;
                if (plus50 > cashAmt && !suggestions.includes(plus50)) suggestions.push(plus50);
                if (plus100 > cashAmt && !suggestions.includes(plus100)) suggestions.push(plus100);
            }
        }

        const filtered = suggestions.filter(s => s > cashAmt).sort((a, b) => a - b).slice(0, 3);

        let html = `<span class="small text-muted me-1" style="font-size: 0.75rem;"><i class="bi bi-cash me-1"></i>Atajos Efectivo:</span>`;
        html += `<button type="button" class="btn btn-sm btn-outline-success py-1 px-2 quick-cash-btn fw-bold" data-amt="${cashAmt}" style="font-size: 0.75rem; min-height: 32px;"><i class="bi bi-check2 me-1"></i>Exacto (${formatMoney(cashAmt)})</button>`;

        filtered.forEach(s => {
            html += `<button type="button" class="btn btn-sm btn-outline-secondary py-1 px-2 quick-cash-btn fw-semibold" data-amt="${s}" style="font-size: 0.75rem; min-height: 32px;">${formatMoney(s)}</button>`;
        });

        quickCashContainer.innerHTML = html;

        quickCashContainer.querySelectorAll('.quick-cash-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                const targetAmt = parseFloat(this.getAttribute('data-amt') || 0);
                const cashRecInput = firstCashLine.querySelector('.payment-cash-received-input');
                if (cashRecInput) {
                    cashRecInput.value = formatInputValue(targetAmt);
                }
                recalculatePaymentsBalance();
            });
        });
    }

    function attachPaymentLineEvents(line) {
        const methodSelect = line.querySelector('.payment-method-select');
        const amtInput = line.querySelector('.payment-amount-input');
        const cashRecInput = line.querySelector('.payment-cash-received-input');
        const cashFieldsCol = line.querySelector('.cash-fields-col');
        const changeDisplayCol = line.querySelector('.change-display-col');
        const refRow = line.querySelector('.reference-row');
        const removeBtn = line.querySelector('.remove-payment-btn');

        function updateMethodVisibility() {
            const isCash = methodSelect.value === 'cash';
            if (isCash) {
                if (cashFieldsCol) cashFieldsCol.style.display = 'block';
                if (changeDisplayCol) changeDisplayCol.style.display = 'block';
                if (refRow) refRow.style.display = 'none';
                if (cashRecInput && (!cashRecInput.value || parseFloat(cashRecInput.value) <= 0)) {
                    cashRecInput.value = formatInputValue(parseFloat(amtInput?.value || 0) || 0);
                }
            } else {
                if (cashFieldsCol) cashFieldsCol.style.display = 'none';
                if (changeDisplayCol) changeDisplayCol.style.display = 'none';
                if (refRow) refRow.style.display = 'block';
            }
            recalculatePaymentsBalance();
            renderQuickCashButtons();
        }

        methodSelect?.addEventListener('change', updateMethodVisibility);
        amtInput?.addEventListener('input', function () {
            recalculatePaymentsBalance();
            renderQuickCashButtons();
        });
        cashRecInput?.addEventListener('input', recalculatePaymentsBalance);

        removeBtn?.addEventListener('click', function () {
            line.remove();
            updateRemoveButtons();
            recalculatePaymentsBalance();
            renderQuickCashButtons();
        });

        updateMethodVisibility();
    }

    function updateRemoveButtons() {
        const lines = paymentsContainer?.querySelectorAll('.payment-line') || [];
        lines.forEach(line => {
            const btn = line.querySelector('.remove-payment-btn');
            if (btn) btn.disabled = (lines.length <= 1);
        });
    }

    if (discountInput) {
        discountInput.addEventListener('input', () => recalculateTotal('discount'));
    }

    switchServiceFee?.addEventListener('change', () => recalculateTotal('service_switch'));
    serviceFeePctInput?.addEventListener('input', () => recalculateTotal('service_pct'));
    serviceFeeInput?.addEventListener('input', () => recalculateTotal('service_amount'));

    switchTaxInc?.addEventListener('change', () => recalculateTotal('tax_switch'));

    // Inicializar primera línea de pago
    const initialLine = paymentsContainer?.querySelector('.payment-line');
    if (initialLine) {
        attachPaymentLineEvents(initialLine);
    }

    // Agregar nueva línea de pago (+ Agregar otro medio)
    if (btnAddPaymentLine && paymentsContainer) {
        btnAddPaymentLine.addEventListener('click', function () {
            const lines = paymentsContainer.querySelectorAll('.payment-line');
            if (lines.length >= 5) {
                const maxAlert = document.getElementById('maxPaymentsAlert');
                if (maxAlert) {
                    maxAlert.classList.remove('d-none');
                    setTimeout(() => maxAlert.classList.add('d-none'), 4000);
                }
                return;
            }

            // 1. Sumar los montos asignados en las filas ya existentes
            let currentSum = 0;
            lines.forEach(l => {
                const amt = parseFloat(l.querySelector('.payment-amount-input')?.value || 0) || 0;
                currentSum += amt;
            });

            // 2. Calcular saldo pendiente a cubrir
            const remaining = Math.max(0, Math.round((currentTotal - currentSum) * 100) / 100);
            const remainingStr = formatInputValue(remaining);

            const idx = paymentLineCount++;
            const newLine = document.createElement('div');
            newLine.className = 'payment-line card border p-2 mb-2 rounded-3 bg-white';
            newLine.setAttribute('data-index', idx);
            newLine.innerHTML = `
                <div class="row g-2 align-items-center">
                    <div class="col-12 col-md-3">
                        <select name="payments[${idx}][method]" class="form-select form-select-sm payment-method-select" required>
                            <option value="transfer" selected>Transferencia / Nequi</option>
                            <option value="card">Tarjeta</option>
                            <option value="cash">Efectivo</option>
                            <option value="other">Otro</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">$</span>
                            <input type="number" step="0.01" min="0.01" name="payments[${idx}][amount]" class="form-control text-end payment-amount-input fw-bold" value="${remainingStr}" required>
                        </div>
                    </div>
                    <div class="col-6 col-md-3 cash-fields-col" style="display: none;">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text" title="Efectivo Recibido"><i class="bi bi-box-arrow-in-down"></i></span>
                            <input type="number" step="0.01" min="0" name="payments[${idx}][cash_received]" class="form-control text-end payment-cash-received-input" placeholder="Recibido" value="${remainingStr}">
                        </div>
                    </div>
                    <div class="col-10 col-md-2 text-end text-md-center change-display-col" style="display: none;">
                        <span class="badge bg-light text-dark border small payment-change-display">Vuelto: $0</span>
                        <input type="hidden" name="payments[${idx}][change_given]" class="payment-change-given-input" value="0.00">
                    </div>
                    <div class="col-2 col-md-1 text-end">
                        <button type="button" class="btn btn-sm btn-outline-danger remove-payment-btn">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
                <div class="row g-2 mt-1 reference-row">
                    <div class="col-12">
                        <input type="text" name="payments[${idx}][reference]" class="form-control form-control-sm payment-reference-input" placeholder="N° Comprobante / Referencia (opcional)" maxlength="60">
                    </div>
                </div>
            `;

            paymentsContainer.appendChild(newLine);
            attachPaymentLineEvents(newLine);
            updateRemoveButtons();
            recalculatePaymentsBalance();
            renderQuickCashButtons();
        });
    }

    // Inicializar cálculo y atajos de efectivo iniciales
    recalculateTotal('init');

    // Controladores ergonómicos táctiles para botones de incremento/decremento de descuento (+5% / -5%)
    document.querySelectorAll('.btn-discount-step').forEach(btn => {
        btn.addEventListener('click', function () {
            const targetSelector = this.getAttribute('data-target');
            const targetInput = targetSelector 
                ? document.querySelector(targetSelector) 
                : this.closest('.input-group')?.querySelector('input[type="number"]');
            if (!targetInput) return;
            const step = parseFloat(this.getAttribute('data-step')) || 5;
            let currentVal = parseFloat(targetInput.value) || 0;
            let newVal = Math.min(100, Math.max(0, currentVal + step));
            targetInput.value = newVal;
            targetInput.dispatchEvent(new Event('input', { bubbles: true }));
            targetInput.dispatchEvent(new Event('change', { bubbles: true }));
        });
    });

    // Prevención de doble clic al liquidar
    const settlementForm = document.getElementById('settlementForm');
    if (settlementForm) {
        settlementForm.addEventListener('submit', function () {
            if (btnSubmitSettlement) {
                btnSubmitSettlement.disabled = true;
                btnSubmitSettlement.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Facturando...';
            }
        });
    }

    // Apertura automática si la URL contiene el hash #cobrar
    function checkHashForSettlement() {
        if (window.location.hash === '#cobrar') {
            const settlementModalEl = document.getElementById('settlementModal');
            if (settlementModalEl && typeof bootstrap !== 'undefined') {
                const modal = bootstrap.Modal.getOrCreateInstance(settlementModalEl);
                modal.show();
            }
        }
    }

    checkHashForSettlement();
    window.addEventListener('hashchange', checkHashForSettlement);

    // Listener para modal de eliminar o reducir ítem individual
    const deleteItemModal = document.getElementById('deleteItemModal');
    if (deleteItemModal) {
        let currentItemQty = 1;
        let currentItemUnitPrice = 0;

        const nameEl = document.getElementById('deleteItemName');
        const unitPriceEl = document.getElementById('deleteItemUnitPriceDisplay');
        const currentQtyBadge = document.getElementById('deleteItemCurrentQtyBadge');
        const fullQtyBtnSpan = document.getElementById('deleteItemFullQtyBtn');
        const qtyInput = document.getElementById('quantity_to_remove');
        const dynamicAlert = document.getElementById('deleteItemDynamicAlert');
        const formEl = document.getElementById('deleteItemForm');
        const reasonInput = document.getElementById('deleteItemReason');
        const btnSubmitText = document.getElementById('btnSubmitDeleteItemText');
        const btnDecrement = document.getElementById('btnDecrementQty');
        const btnIncrement = document.getElementById('btnIncrementQty');
        const btnSetFull = document.getElementById('btnSetFullQuantity');

        function formatCurrency(amount) {
            return new Intl.NumberFormat('es-CO', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(amount);
        }

        function updateModalState() {
            let removeQty = parseInt(qtyInput.value, 10);
            if (isNaN(removeQty) || removeQty < 1) {
                removeQty = 1;
            }
            if (removeQty > currentItemQty) {
                removeQty = currentItemQty;
                qtyInput.value = removeQty;
            }

            const remainingQty = currentItemQty - removeQty;
            const newSubtotal = remainingQty * currentItemUnitPrice;

            if (removeQty >= currentItemQty) {
                // Eliminación total
                dynamicAlert.className = 'alert alert-danger d-flex align-items-center mb-3 rounded-3';
                dynamicAlert.innerHTML = `
                    <i class="bi bi-trash3-fill fs-4 me-3 text-danger"></i>
                    <div>
                        <strong>Cancelación Total:</strong> Se cancelará el plato por completo de la comanda. Se restaurará el stock de <strong>${removeQty}</strong> unidad(es) y se recalculará el total.
                    </div>
                `;
                if (btnSubmitText) btnSubmitText.textContent = 'Confirmar Eliminación Total';
            } else {
                // Reducción parcial
                dynamicAlert.className = 'alert alert-warning d-flex align-items-center mb-3 rounded-3';
                dynamicAlert.innerHTML = `
                    <i class="bi bi-dash-circle-fill fs-4 me-3 text-warning"></i>
                    <div>
                        <strong>Reducción Parcial:</strong> Se retirará(n) <strong>${removeQty}</strong> unidad(es). Quedarán <strong>${remainingQty}</strong> unidad(es) en la comanda. Se devolverá al stock <strong>${removeQty}</strong> unidad(es) y se recalculará el subtotal de este plato a <strong>$${formatCurrency(newSubtotal)}</strong>.
                    </div>
                `;
                if (btnSubmitText) btnSubmitText.textContent = `Confirmar Retiro (${removeQty} un.)`;
            }
        }

        deleteItemModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            if (!button) return;
            const name = button.getAttribute('data-item-name') || '';
            const qty = parseFloat(button.getAttribute('data-item-qty') || '1');
            const unitPrice = parseFloat(button.getAttribute('data-item-unit-price') || '0');
            const url = button.getAttribute('data-action-url') || '';

            currentItemQty = Math.max(1, Math.round(qty));
            currentItemUnitPrice = unitPrice;

            if (nameEl) nameEl.textContent = name;
            if (unitPriceEl) unitPriceEl.textContent = `$${formatCurrency(unitPrice)}`;
            if (currentQtyBadge) currentQtyBadge.textContent = currentItemQty;
            if (fullQtyBtnSpan) fullQtyBtnSpan.textContent = currentItemQty;
            if (formEl) formEl.setAttribute('action', url);
            if (reasonInput) reasonInput.value = '';

            if (qtyInput) {
                qtyInput.max = currentItemQty;
                qtyInput.value = 1;
            }

            updateModalState();
        });

        if (qtyInput) {
            qtyInput.addEventListener('input', updateModalState);
            qtyInput.addEventListener('change', updateModalState);
        }

        if (btnDecrement) {
            btnDecrement.addEventListener('click', function () {
                let currentVal = parseInt(qtyInput.value, 10) || 1;
                if (currentVal > 1) {
                    qtyInput.value = currentVal - 1;
                    updateModalState();
                }
            });
        }

        if (btnIncrement) {
            btnIncrement.addEventListener('click', function () {
                let currentVal = parseInt(qtyInput.value, 10) || 1;
                if (currentVal < currentItemQty) {
                    qtyInput.value = currentVal + 1;
                    updateModalState();
                }
            });
        }

        if (btnSetFull) {
            btnSetFull.addEventListener('click', function () {
                qtyInput.value = currentItemQty;
                updateModalState();
            });
        }
    }
});
</script>

{{-- MODAL PARA ELIMINAR O REDUCIR ÍTEM INDIVIDUAL (ADMIN ONLY) --}}
@if(auth()->check() && auth()->user()->hasRole('admin') && $order->canBeModified())
<div class="modal fade" id="deleteItemModal" tabindex="-1" aria-labelledby="deleteItemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
            <div class="modal-header bg-danger text-white py-3">
                <h5 class="modal-title fw-bold" id="deleteItemModalLabel">
                    <i class="bi bi-trash3-fill me-2"></i>Gestionar / Retirar Plato de Comanda
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="deleteItemForm" method="POST" action="">
                @csrf
                @method('DELETE')
                <div class="modal-body p-4">
                    {{-- Información del Plato --}}
                    <div class="bg-light p-3 rounded-3 mb-3 border">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-muted small">Plato / Producto:</span>
                            <span class="text-muted small">Precio Unitario:</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <h6 class="fw-bold mb-0 text-dark" id="deleteItemName">Plato</h6>
                            <span class="fw-bold text-dark" id="deleteItemUnitPriceDisplay">$0.00</span>
                        </div>
                        <div class="mt-2 pt-2 border-top d-flex justify-content-between align-items-center">
                            <span class="text-muted small">Cantidad actual en comanda:</span>
                            <span class="badge bg-primary fs-6 px-2 py-1" id="deleteItemCurrentQtyBadge">1</span>
                        </div>
                    </div>

                    {{-- Selector de Cantidad a Retirar --}}
                    <div class="mb-3">
                        <label for="quantity_to_remove" class="form-label fw-semibold text-dark">
                            Cantidad a retirar <span class="text-danger">*</span>:
                        </label>
                        <div class="input-group">
                            <button type="button" class="btn btn-outline-secondary px-3" id="btnDecrementQty" title="Restar 1">
                                <i class="bi bi-dash-lg"></i>
                            </button>
                            <input type="number" 
                                   class="form-control form-control-lg fw-bold text-center" 
                                   id="quantity_to_remove" 
                                   name="quantity_to_remove" 
                                   min="1" 
                                   step="1" 
                                   value="1" 
                                   required>
                            <button type="button" class="btn btn-outline-secondary px-3" id="btnIncrementQty" title="Sumar 1">
                                <i class="bi bi-plus-lg"></i>
                            </button>
                            <button type="button" class="btn btn-outline-danger px-3 fw-semibold" id="btnSetFullQuantity" title="Retirar todas las unidades">
                                Retirar Todo (<span id="deleteItemFullQtyBtn">1</span>)
                            </button>
                        </div>
                        <div class="form-text">Elige cuántas unidades deseas retirar (reducción parcial o eliminación total).</div>
                    </div>

                    {{-- Mensaje Reactivo Dinámico según la cantidad seleccionada --}}
                    <div id="deleteItemDynamicAlert" class="alert mb-3 rounded-3 transition-all" role="alert">
                    </div>

                    {{-- Motivo Obligatorio --}}
                    <div class="mb-3">
                        <label for="deleteItemReason" class="form-label fw-semibold text-dark">
                            Motivo de retiro o eliminación <span class="text-danger">*</span>:
                        </label>
                        <textarea class="form-control" 
                                  id="deleteItemReason" 
                                  name="reason" 
                                  rows="3" 
                                  required 
                                  minlength="4" 
                                  maxlength="500" 
                                  placeholder="Ej: Cliente canceló una unidad, error de digitación, plato devuelto..."></textarea>
                        <small class="text-muted">Mínimo 4 caracteres. Quedará registrado en la auditoría con tu usuario y fecha.</small>
                    </div>
                </div>
                <div class="modal-footer bg-light px-4 py-3">
                    <button type="button" class="btn btn-outline-secondary px-3" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger px-4 fw-bold" id="btnSubmitDeleteItem">
                        <i class="bi bi-trash3 me-1"></i> <span id="btnSubmitDeleteItemText">Confirmar Retiro</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- MODAL PARA ANULAR COMANDA COMPLETA (ADMIN ONLY) --}}
<div class="modal fade" id="cancelOrderModal" tabindex="-1" aria-labelledby="cancelOrderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
            <div class="modal-header bg-danger text-white py-3">
                <h5 class="modal-title fw-bold" id="cancelOrderModalLabel">
                    <i class="bi bi-x-octagon-fill me-2"></i>Anular Comanda {{ $order->order_number }}
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('restaurant.orders.cancel', $order) }}">
                @csrf
                <div class="modal-body p-4">
                    <div class="alert alert-danger d-flex align-items-center mb-3 rounded-3" role="alert">
                        <i class="bi bi-shield-slash-fill fs-3 me-3 text-danger"></i>
                        <div>
                            <strong>Acción Crítica e Irreversible:</strong> Esta acción liberará la mesa asociada, restaurará el stock de los productos consumidos y cancelará la comanda definitivamente.
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="cancelOrderReason" class="form-label fw-semibold text-dark">
                            Motivo de anulación <span class="text-danger">*</span>:
                        </label>
                        <textarea class="form-control" 
                                  id="cancelOrderReason" 
                                  name="reason" 
                                  rows="3" 
                                  required 
                                  minlength="4" 
                                  maxlength="500" 
                                  placeholder="Ej: Cliente se retiró del local, error en apertura de mesa, pedido duplicado..."></textarea>
                        <small class="text-muted">Mínimo 4 caracteres. Quedará registrado en la auditoría con tu usuario y fecha.</small>
                    </div>
                </div>
                <div class="modal-footer bg-light px-4 py-3">
                    <button type="button" class="btn btn-outline-secondary px-3" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger px-4 fw-bold">
                        <i class="bi bi-x-octagon-fill me-1"></i> Sí, Anular Comanda
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

{{-- MODAL PARA CAMBIO DE MESA EN COMANDA ACTIVA --}}
@if($order->canBeModified())
<div class="modal fade" id="changeTableModal" tabindex="-1" aria-labelledby="changeTableModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title fw-bold" id="changeTableModalLabel">
                    <i class="bi bi-arrow-left-right me-2"></i>Cambiar Mesa de la Comanda
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="{{ route('restaurant.orders.change-table', $order) }}">
                @csrf
                <div class="modal-body p-4">
                    {{-- Información contextual --}}
                    <div class="alert alert-light border d-flex align-items-center mb-3 rounded-3">
                        <i class="bi bi-info-circle-fill text-primary fs-3 me-3"></i>
                        <div>
                            <div class="small text-muted">Comanda activa: <strong class="text-dark">{{ $order->order_number }}</strong></div>
                            <div class="fw-semibold">Mesa actual: <span class="badge bg-secondary text-white ms-1">{{ $order->table ? $order->table->name : 'Sin mesa asignada' }}</span></div>
                        </div>
                    </div>

                    @php
                        $filteredTables = isset($availableTables) ? $availableTables->where('id', '!=', $order->table_id) : collect();
                    @endphp

                    @if($filteredTables->isNotEmpty())
                        <div class="mb-3">
                            <label for="changeTableSelect" class="form-label fw-semibold text-dark">
                                Nueva Mesa Destino <span class="text-danger">*</span>
                            </label>
                            <select class="form-select form-select-lg" id="changeTableSelect" name="table_id" required>
                                <option value="" disabled selected>-- Selecciona una mesa disponible --</option>
                                @foreach($filteredTables as $availableTable)
                                    <option value="{{ $availableTable->id }}">
                                        {{ $availableTable->name }} — Capacidad: {{ $availableTable->capacity }} personas
                                    </option>
                                @endforeach
                            </select>
                            <div class="form-text text-muted mt-2">
                                <i class="bi bi-check-circle text-success me-1"></i>
                                La mesa anterior quedará libre automáticamente y la comanda pasará a la nueva mesa seleccionada.
                            </div>
                        </div>
                    @else
                        <div class="alert alert-warning d-flex align-items-center rounded-3 mb-0" role="alert">
                            <i class="bi bi-exclamation-triangle-fill text-warning fs-3 me-3"></i>
                            <div>
                                <strong class="d-block mb-1">No hay otras mesas disponibles en este momento.</strong>
                                <span class="small text-muted">Todas las demás mesas del establecimiento se encuentran ocupadas o en proceso de facturación.</span>
                            </div>
                        </div>
                    @endif
                </div>
                <div class="modal-footer bg-light px-4 py-3">
                    <button type="button" class="btn btn-outline-secondary px-3" data-bs-dismiss="modal">Cancelar</button>
                    @if($filteredTables->isNotEmpty())
                        <button type="submit" class="btn btn-primary px-4 fw-bold">
                            <i class="bi bi-arrow-left-right me-1"></i> Confirmar Cambio de Mesa
                        </button>
                    @else
                        <button type="button" class="btn btn-primary px-4 fw-bold" disabled>
                            <i class="bi bi-arrow-left-right me-1"></i> Confirmar Cambio de Mesa
                        </button>
                    @endif
                </div>
            </form>
        </div>
    </div>
</div>
@endif

{{-- MODAL PARA CANCELAR COMANDA VACÍA --}}
@if($order->status === 'open' && $order->items->isEmpty())
<div class="modal fade" id="cancelEmptyOrderModal" tabindex="-1" aria-labelledby="cancelEmptyOrderModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
            <div class="modal-header bg-danger text-white py-3">
                <h5 class="modal-title fw-bold" id="cancelEmptyOrderModalLabel">
                    <i class="bi bi-trash3 me-2"></i>Cancelar Comanda Vacía
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-warning d-flex align-items-center mb-0 rounded-3" role="alert">
                    <i class="bi bi-exclamation-triangle-fill fs-3 me-3 text-warning flex-shrink-0"></i>
                    <div>
                        <div class="fw-semibold text-dark mb-1">
                            ¿Confirmas que deseas cancelar la comanda <strong>{{ $order->order_number }}</strong> y liberar inmediatamente la <strong>{{ $order->table ? $order->table->name : 'mesa' }}</strong>?
                        </div>
                        <div class="small text-muted">
                            Esta comanda no contiene platos activos y la mesa quedará disponible para nuevos comensales.
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light px-4 py-3 d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-secondary px-3" data-bs-dismiss="modal">
                    Conservar Comanda
                </button>
                <form action="{{ route('restaurant.orders.cancel-empty', $order) }}" method="POST" id="cancelEmptyOrderForm" class="d-inline m-0" data-no-disable>
                    @csrf
                    @method('POST')
                    <button type="submit" class="btn btn-danger px-4 fw-bold" id="btnConfirmCancelEmpty">
                        <i class="bi bi-trash3 me-1"></i> Sí, Cancelar y Liberar Mesa
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const formCancelEmpty = document.getElementById('cancelEmptyOrderForm');
    const btnConfirmCancelEmpty = document.getElementById('btnConfirmCancelEmpty');
    const cancelEmptyModal = document.getElementById('cancelEmptyOrderModal');

    if (formCancelEmpty && btnConfirmCancelEmpty) {
        formCancelEmpty.addEventListener('submit', function() {
            btnConfirmCancelEmpty.disabled = true;
            btnConfirmCancelEmpty.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Procesando...';
        });
    }

    if (cancelEmptyModal && btnConfirmCancelEmpty) {
        cancelEmptyModal.addEventListener('hidden.bs.modal', function () {
            btnConfirmCancelEmpty.disabled = false;
            btnConfirmCancelEmpty.innerHTML = '<i class="bi bi-trash3 me-1"></i> Sí, Cancelar y Liberar Mesa';
        });
    }
});
</script>
@endif

@include('restaurant.partials.kitchen-config-modal')

@if(session('print_kitchen_ticket_id'))
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            try {
                const autoPrint = localStorage.getItem('puntostock_auto_print_kitchen') !== 'disabled';
                if (autoPrint) {
                    const printUrl = "{{ route('restaurant.orders.kitchen-ticket', ['order' => session('print_kitchen_ticket_id'), 'batch' => session('print_kitchen_batch')]) }}";
                    const popup = window.open(printUrl, '_blank', 'width=450,height=650,scrollbars=yes');
                    if (!popup || popup.closed || typeof popup.closed === 'undefined') {
                        console.info('Aviso: La ventana emergente de impresión automática fue bloqueada por el navegador. El botón de impresión manual está disponible.');
                    }
                }
            } catch (e) {
                console.warn('Impresión automática de cocina no ejecutada:', e);
            }
        });
    </script>
@endif

@if($order->order_type === 'table' && $order->canBeModified() && !$order->isFullyPaid())
{{-- Modal Reactivo de División de Cuenta por Platos (Split Bill) --}}
<div class="modal fade" id="splitBillModal" tabindex="-1" aria-labelledby="splitBillModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
            {{-- Header del Modal --}}
            <div class="modal-header border-bottom px-4 py-3 align-items-center bg-white">
                <div class="d-flex align-items-center">
                    <div class="rounded-circle bg-success-subtle p-2 text-success me-3 d-inline-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="bi bi-pie-chart-fill fs-4"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <h5 class="modal-title fw-bold text-dark mb-0" id="splitBillModalLabel">División de Cuenta por Platos</h5>
                            @if($order->table)
                                <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle px-2 py-1">
                                    <i class="bi bi-aspect-ratio me-1"></i>{{ $order->table->name }}
                                </span>
                            @endif
                        </div>
                        <p class="text-muted small mb-0 mt-1">
                            Comanda <strong>{{ $order->order_number }}</strong> &bull; Total comanda: <strong>${{ number_format($order->total, 0, ',', '.') }}</strong> &bull; Saldo pendiente: <span class="text-success fw-bold">${{ number_format($order->pendingBalance(), 0, ',', '.') }}</span>
                        </p>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            {{-- Formulario de Liquidación Dividida --}}
            <form action="{{ route('restaurant.orders.split-payment', $order) }}" method="POST" id="splitBillForm">
                @csrf
                <div class="modal-body p-4 bg-white">
                    <div class="row g-4">
                        {{-- Columna Izquierda: Platos y Consumos de la Comanda (col-lg-7) --}}
                        <div class="col-12 col-lg-7">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h6 class="fw-bold mb-0 text-dark">Platos y Consumos de la Comanda</h6>
                                    <small class="text-muted">Seleccione los platos que pagará este comensal:</small>
                                </div>
                                <button type="button" id="btnSelectAllSplit" class="btn btn-sm btn-outline-success rounded-pill px-3 fw-semibold">
                                    <i class="bi bi-check2-all me-1"></i>Marcar todos
                                </button>
                            </div>

                            <div class="table-responsive border rounded-3" style="max-height: 420px; overflow-y: auto;">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th style="width: 45px;" class="text-center">
                                                <i class="bi bi-check2-square text-muted"></i>
                                            </th>
                                            <th>Plato / Consumo</th>
                                            <th class="text-center" style="width: 80px;">Cant.</th>
                                            <th class="text-end" style="width: 120px;">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($order->unpaidItems() as $item)
                                            <tr>
                                                <td class="text-center">
                                                    <input type="checkbox"
                                                           class="form-check-input split-item-checkbox"
                                                           name="items[]"
                                                           value="{{ $item->id }}"
                                                           data-price="{{ $item->subtotal }}"
                                                           id="split_item_{{ $item->id }}"
                                                           style="cursor: pointer; width: 20px; height: 20px;">
                                                </td>
                                                <td>
                                                    <label for="split_item_{{ $item->id }}" class="mb-0 fw-semibold text-dark d-block" style="cursor: pointer;">
                                                        {{ $item->product?->name ?? 'Plato / Consumo' }}
                                                    </label>
                                                    @if(!empty($item->notes))
                                                        <span class="small text-muted fst-italic">Nota: {{ $item->notes }}</span>
                                                    @endif
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-light text-dark border">
                                                        {{ (float)$item->quantity == (int)$item->quantity ? (int)$item->quantity : number_format($item->quantity, 1) }}
                                                    </span>
                                                </td>
                                                <td class="text-end fw-bold text-dark">
                                                    ${{ number_format($item->subtotal, 0, ',', '.') }}
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="4" class="text-center py-4 text-muted">
                                                    <i class="bi bi-check-circle-fill text-success fs-3 d-block mb-2"></i>
                                                    Todos los platos de esta comanda ya han sido pagados.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- Columna Derecha: Liquidación de Platos Seleccionados (col-lg-5) --}}
                        <div class="col-12 col-lg-5">
                            <div class="card border bg-light-subtle rounded-4 p-4 h-100 shadow-sm">
                                <h6 class="fw-bold mb-3 text-dark d-flex align-items-center">
                                    <i class="bi bi-calculator me-2 text-success"></i>Liquidación de Platos Seleccionados
                                </h6>

                                {{-- Datos del Comprador y Descuento --}}
                                <div class="row g-2 mb-3">
                                    <div class="col-12 col-md-7">
                                        <label class="form-label fw-semibold small mb-1">Cliente / Comprador:</label>
                                        <div class="d-flex align-items-stretch gap-1">
                                            <select name="customer_id" id="split_customer_id" class="form-select form-select-sm">
                                                <option value="">Consumidor Final DIAN (222222222222)</option>
                                                @foreach($customers as $c)
                                                    <option value="{{ $c->id }}" {{ (old('customer_id', $order->customer_id) == $c->id) ? 'selected' : '' }}>
                                                        {{ $c->name }} (Doc: {{ $c->document ?? 'N/A' }})
                                                    </option>
                                                @endforeach
                                            </select>
                                            <button type="button" 
                                                    class="btn btn-outline-primary bg-white text-primary d-inline-flex align-items-center justify-content-center px-3 flex-shrink-0 btn-open-quick-customer btn-quick-customer-trigger" 
                                                    data-target-select="#split_customer_id"
                                                    title="Crear Cliente Rápido"
                                                    aria-label="Crear Cliente Rápido">
                                                <i class="bi bi-person-plus fs-5" style="line-height: 1;"></i>
                                            </button>
                                        </div>
                                        <small class="text-muted d-block" style="font-size: 0.72rem;">Opcional para facturación individual</small>
                                    </div>
                                    <div class="col-12 col-md-5">
                                        <label for="split_discount_percentage" class="form-label small fw-semibold text-dark mb-1">Descuento (%):</label>
                                        <div class="input-group">
                                            <button type="button" class="btn btn-outline-secondary px-2 btn-discount-step" data-target="#split_discount_percentage" data-step="-5">
                                                <i class="bi bi-dash"></i>
                                            </button>
                                            <input type="number" 
                                                   name="discount_percentage" 
                                                   id="split_discount_percentage" 
                                                   class="form-control text-center fw-bold" 
                                                   min="0" 
                                                   max="100" 
                                                   step="1" 
                                                   value="0">
                                            <button type="button" class="btn btn-outline-secondary px-2 btn-discount-step" data-target="#split_discount_percentage" data-step="5">
                                                <i class="bi bi-plus"></i>
                                            </button>
                                            <span class="input-group-text bg-light fw-bold">%</span>
                                        </div>
                                        <small class="text-muted d-block" style="font-size: 0.72rem;">Sobre platos de este comensal</small>
                                    </div>
                                </div>

                                {{-- Interruptores de Propina (10%) e Impuesto al Consumo (INC 8%) --}}
                                <div class="card border p-3 mb-3 rounded-3 bg-white">
                                    <div class="row g-2">
                                        <div class="col-12 col-sm-6 border-end-sm">
                                            <div class="form-check form-switch mb-0">
                                                <input class="form-check-input" type="checkbox" id="split_include_tip" name="include_tip" value="1" role="switch">
                                                <label class="form-check-label fw-semibold small text-dark" for="split_include_tip">
                                                    <i class="bi bi-heart text-danger me-1"></i> Propina (10%)
                                                </label>
                                            </div>
                                            <small class="text-muted d-block" style="font-size: 0.7rem;">Sugerido voluntario</small>
                                        </div>
                                        <div class="col-12 col-sm-6">
                                            <div class="form-check form-switch mb-0">
                                                <input class="form-check-input" type="checkbox" id="split_include_tax" name="include_tax" value="1" role="switch">
                                                <label class="form-check-label fw-semibold small text-dark" for="split_include_tax">
                                                    <i class="bi bi-receipt text-primary me-1"></i> Impuesto (INC 8%)
                                                </label>
                                            </div>
                                            <small class="text-muted d-block" style="font-size: 0.7rem;">Consumo gravable</small>
                                        </div>
                                    </div>
                                </div>

                                {{-- Tarjeta de Desglose Financiero Transparente --}}
                                <div class="card bg-white border p-3 mb-3 rounded-3 shadow-sm">
                                    <div class="d-flex justify-content-between small mb-1">
                                        <span class="text-muted">Subtotal Platos (<span id="splitRefsCount">0</span> ref):</span>
                                        <span class="fw-semibold text-dark" id="splitBaseSubtotal">$0</span>
                                    </div>
                                    <div class="d-flex justify-content-between small mb-1">
                                        <span class="text-muted">Descuento (<span id="splitDiscountPercentLabel">0</span>%):</span>
                                        <span class="fw-semibold text-danger" id="splitDiscountDisplay">-$0</span>
                                    </div>
                                    <div class="d-flex justify-content-between small mb-1" id="splitRowTip" style="display: none !important;">
                                        <span class="text-muted">Servicio / Propina (10%):</span>
                                        <span class="fw-semibold text-success" id="splitTipDisplay">+$0</span>
                                    </div>
                                    <div class="d-flex justify-content-between small mb-1" id="splitRowTax" style="display: none !important;">
                                        <span class="text-muted">Impuesto al Consumo (INC 8%):</span>
                                        <span class="fw-semibold text-primary" id="splitTaxDisplay">+$0</span>
                                    </div>
                                    <hr class="my-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="fw-bold fs-6 text-dark">Total a Liquidar:</span>
                                        <span class="fw-bold fs-3 text-primary" id="splitFinalTotalDisplay">$0</span>
                                    </div>
                                </div>

                                {{-- Pasarela de Medios de Pago Mixtos --}}
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <label class="form-label small fw-bold text-dark mb-0">
                                            <i class="bi bi-wallet2 text-primary me-1"></i>Métodos de Pago
                                        </label>
                                        <button type="button" class="btn btn-xs btn-outline-primary py-1 px-2 rounded-pill d-inline-flex align-items-center gap-1" id="btnAddSplitPaymentMethod" style="font-size: 0.75rem;">
                                            <i class="bi bi-plus-circle"></i>
                                            <span>Agregar otro medio</span>
                                        </button>
                                    </div>

                                    {{-- Atajos rápidos de efectivo (Reactivos al total a liquidar) --}}
                                    <div class="d-flex flex-wrap gap-1 mb-2 align-items-center" id="splitQuickCashButtons">
                                        <span class="small text-muted me-1" style="font-size: 0.75rem;"><i class="bi bi-cash me-1"></i>Atajos Efectivo:</span>
                                        <button type="button" class="btn btn-sm btn-outline-success py-1 px-2 split-quick-cash-btn fw-bold" data-amt="0" style="font-size: 0.75rem; min-height: 30px;">
                                            <i class="bi bi-check2 me-1"></i>Exacto ($0)
                                        </button>
                                    </div>

                                    {{-- Contenedor dinámico de filas de pago --}}
                                    <div id="splitPaymentMethodsContainer">
                                        {{-- Línea 1 por defecto --}}
                                        <div class="split-payment-line payment-method-row card border p-2 mb-2 rounded-3 bg-white" data-index="0">
                                            <div class="d-flex align-items-center gap-2">
                                                <div style="flex: 1 1 130px; min-width: 115px;">
                                                    <select name="payments[0][method]" class="form-select form-select-sm split-method-select" required>
                                                        <option value="cash" selected>Efectivo</option>
                                                        <option value="transfer">Transferencia / Nequi</option>
                                                        <option value="card">Tarjeta</option>
                                                        <option value="other">Otro</option>
                                                    </select>
                                                </div>
                                                <div style="flex: 1 1 120px; min-width: 110px;">
                                                    <div class="input-group input-group-sm">
                                                        <span class="input-group-text">$</span>
                                                        <input type="number" step="0.01" min="0.01" name="payments[0][amount]" class="form-control text-end split-payment-amount-input fw-bold" value="0" required>
                                                    </div>
                                                </div>
                                                <button type="button" 
                                                        class="btn btn-outline-danger btn-remove-split-payment remove-split-payment-btn flex-shrink-0 d-flex align-items-center justify-content-center" 
                                                        style="width: 38px; height: 38px; position: relative; z-index: 2;" 
                                                        title="Eliminar fila de pago" 
                                                        disabled>
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>

                                            {{-- Sub-fila para Efectivo (Recibido y Vuelto) --}}
                                            <div class="split-cash-details-row w-100 d-flex align-items-center justify-content-between gap-2 mt-2 pt-2 border-top">
                                                <div class="d-flex align-items-center gap-1" style="max-width: 175px;">
                                                    <span class="text-muted small" style="font-size: 0.72rem; white-space: nowrap;">Recibido:</span>
                                                    <div class="input-group input-group-sm">
                                                        <span class="input-group-text" style="font-size: 0.75rem;">$</span>
                                                        <input type="number" step="0.01" min="0" name="payments[0][cash_received]" class="form-control text-end split-cash-received-input" placeholder="0" value="0">
                                                    </div>
                                                </div>
                                                <div class="text-end">
                                                    <span class="badge bg-light text-success border small split-change-display">Vuelto: $0</span>
                                                    <input type="hidden" name="payments[0][change_given]" class="split-change-given-input" value="0.00">
                                                </div>
                                            </div>

                                            {{-- Sub-fila para Referencia / Voucher (cuando no es efectivo) --}}
                                            <div class="split-reference-row w-100 mt-2 pt-2 border-top" style="display: none;">
                                                <input type="text" name="payments[0][reference]" class="form-control form-control-sm split-reference-input" placeholder="N° Comprobante / Voucher (opcional)" maxlength="60">
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Alerta de balance en tiempo real --}}
                                    <div id="splitPaymentBalanceAlert" class="alert py-2 px-3 small d-none rounded-3 mt-2">
                                        <span id="splitPaymentBalanceMessage">Seleccione platos a liquidar.</span>
                                    </div>
                                </div>

                                {{-- Observaciones de Facturación --}}
                                <div class="mb-3">
                                    <label for="split_notes" class="form-label small fw-semibold text-muted mb-1">Observaciones de Facturación (Opcional):</label>
                                    <input type="text" name="notes" id="split_notes" class="form-control form-control-sm" placeholder="Ej: Pago individual comensal 1, transferencia aprobada..." maxlength="255">
                                </div>

                                {{-- Botones de Acción --}}
                                <button type="button" id="btnPrintSplitPreTicket" class="btn btn-outline-secondary w-100 py-2 rounded-3 mb-2 fw-semibold" disabled>
                                    <i class="bi bi-printer me-1"></i> Imprimir Pre-cuenta (Platos Seleccionados)
                                </button>

                                <button type="submit" id="btnSubmitSplitPayment" class="btn btn-success w-100 py-2 fw-bold rounded-3 shadow-sm" disabled>
                                    <i class="bi bi-check2-circle me-1"></i> Cobrar Platos e Imprimir Factura
                                </button>

                                <div class="text-center mt-3">
                                    <small class="text-muted" style="font-size: 0.8rem;">
                                        <i class="bi bi-shield-check me-1 text-success"></i>Descuenta los platos pagados y mantiene pendientes los restantes hasta liberar la mesa.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const splitModal = document.getElementById('splitBillModal');
    if (!splitModal) return;

    const checkboxes = splitModal.querySelectorAll('.split-item-checkbox');
    const btnSelectAll = document.getElementById('btnSelectAllSplit');
    const customerSelect = document.getElementById('split_customer_id');
    const discountInput = document.getElementById('split_discount_percentage');
    const switchTip = document.getElementById('split_include_tip');
    const switchTax = document.getElementById('split_include_tax');

    const refsCountDisplay = document.getElementById('splitRefsCount');
    const baseSubtotalDisplay = document.getElementById('splitBaseSubtotal');
    const discountPctLabel = document.getElementById('splitDiscountPercentLabel');
    const discountDisplay = document.getElementById('splitDiscountDisplay');
    const rowTip = document.getElementById('splitRowTip');
    const tipDisplay = document.getElementById('splitTipDisplay');
    const rowTax = document.getElementById('splitRowTax');
    const taxDisplay = document.getElementById('splitTaxDisplay');
    const finalTotalDisplay = document.getElementById('splitFinalTotalDisplay');

    const paymentsContainer = document.getElementById('splitPaymentMethodsContainer');
    const btnAddPaymentMethod = document.getElementById('btnAddSplitPaymentMethod');
    const quickCashContainer = document.getElementById('splitQuickCashButtons');
    const balanceAlert = document.getElementById('splitPaymentBalanceAlert');
    const balanceMessage = document.getElementById('splitPaymentBalanceMessage');

    const btnPrecount = document.getElementById('btnPrintSplitPreTicket');
    const btnSubmit = document.getElementById('btnSubmitSplitPayment');
    const splitForm = document.getElementById('splitBillForm');

    let currentBaseSubtotal = 0;
    let currentFinalTotal = 0;
    let paymentLineIndex = 1;
    let allSelected = false;

    function formatCurrency(amount) {
        return '$' + Math.round(amount).toLocaleString('es-CO');
    }

    function calculateSplitTotals() {
        let sum = 0;
        let count = 0;

        checkboxes.forEach(function (cb) {
            if (cb.checked) {
                sum += parseFloat(cb.dataset.price || 0);
                count++;
            }
        });

        currentBaseSubtotal = roundToCents(sum);
        if (refsCountDisplay) refsCountDisplay.textContent = count;
        if (baseSubtotalDisplay) baseSubtotalDisplay.textContent = formatCurrency(currentBaseSubtotal);

        // Descuento
        let discPct = parseFloat(discountInput ? discountInput.value : 0) || 0;
        discPct = Math.max(0, Math.min(100, discPct));
        const discAmount = roundToCents(currentBaseSubtotal * (discPct / 100));
        const baseAjustada = Math.max(0, roundToCents(currentBaseSubtotal - discAmount));

        if (discountPctLabel) discountPctLabel.textContent = discPct;
        if (discountDisplay) discountDisplay.textContent = '-' + formatCurrency(discAmount);

        // Propina (10%)
        const includeTip = switchTip && switchTip.checked;
        const tipAmount = includeTip ? roundToCents(baseAjustada * 0.10) : 0;
        if (rowTip) {
            rowTip.style.setProperty('display', includeTip ? 'flex' : 'none', 'important');
        }
        if (tipDisplay) tipDisplay.textContent = '+' + formatCurrency(tipAmount);

        // Impuesto INC (8%)
        const includeTax = switchTax && switchTax.checked;
        const taxAmount = includeTax ? roundToCents(baseAjustada * 0.08) : 0;
        if (rowTax) {
            rowTax.style.setProperty('display', includeTax ? 'flex' : 'none', 'important');
        }
        if (taxDisplay) taxDisplay.textContent = '+' + formatCurrency(taxAmount);

        // Total Final
        currentFinalTotal = roundToCents(baseAjustada + tipAmount + taxAmount);
        if (finalTotalDisplay) finalTotalDisplay.textContent = formatCurrency(currentFinalTotal);

        const hasSelected = count > 0;
        if (btnPrecount) btnPrecount.disabled = !hasSelected;

        // Si solo hay una línea de pago, sincronizar automáticamente su monto con currentFinalTotal
        const lines = paymentsContainer.querySelectorAll('.split-payment-line');
        if (lines.length === 1) {
            const firstAmtInput = lines[0].querySelector('.split-payment-amount-input');
            const firstReceivedInput = lines[0].querySelector('.split-cash-received-input');
            const methodSelect = lines[0].querySelector('.split-method-select');

            if (firstAmtInput) firstAmtInput.value = currentFinalTotal;
            if (methodSelect && methodSelect.value === 'cash' && firstReceivedInput) {
                firstReceivedInput.value = currentFinalTotal;
            }
        }

        renderQuickCashButtons(currentFinalTotal);
        updatePaymentLinesChange();
        calculatePaymentBalance();

        allSelected = count === checkboxes.length && checkboxes.length > 0;
        if (btnSelectAll) {
            btnSelectAll.innerHTML = allSelected
                ? '<i class="bi bi-dash-circle me-1"></i>Desmarcar todos'
                : '<i class="bi bi-check2-all me-1"></i>Marcar todos';
        }
    }

    function roundToCents(val) {
        return Math.round(val * 100) / 100;
    }

    function renderQuickCashButtons(total) {
        if (!quickCashContainer) return;

        quickCashContainer.innerHTML = '<span class="small text-muted me-1" style="font-size: 0.75rem;"><i class="bi bi-cash me-1"></i>Atajos Efectivo:</span>';

        if (total <= 0) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'btn btn-sm btn-outline-success py-1 px-2 split-quick-cash-btn fw-bold';
            btn.style.fontSize = '0.75rem';
            btn.style.minHeight = '30px';
            btn.dataset.amt = 0;
            btn.innerHTML = '<i class="bi bi-check2 me-1"></i>Exacto ($0)';
            quickCashContainer.appendChild(btn);
            return;
        }

        const exactBtn = document.createElement('button');
        exactBtn.type = 'button';
        exactBtn.className = 'btn btn-sm btn-outline-success py-1 px-2 split-quick-cash-btn fw-bold';
        exactBtn.style.fontSize = '0.75rem';
        exactBtn.style.minHeight = '30px';
        exactBtn.dataset.amt = total;
        exactBtn.innerHTML = `<i class="bi bi-check2 me-1"></i>Exacto (${formatCurrency(total)})`;
        quickCashContainer.appendChild(exactBtn);

        // Denominaciones colombianas redondeadas sugeridas
        const steps = [1000, 2000, 5000, 10000, 20000, 50000, 100000];
        const addedAmts = new Set([total]);

        steps.forEach(function (step) {
            const rounded = Math.ceil(total / step) * step;
            if (rounded > total && !addedAmts.has(rounded) && addedAmts.size < 4) {
                addedAmts.add(rounded);
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'btn btn-sm btn-outline-secondary py-1 px-2 split-quick-cash-btn';
                btn.style.fontSize = '0.75rem';
                btn.style.minHeight = '30px';
                btn.dataset.amt = rounded;
                btn.textContent = formatCurrency(rounded);
                quickCashContainer.appendChild(btn);
            }
        });
    }

    function updatePaymentLinesChange() {
        const lines = paymentsContainer.querySelectorAll('.split-payment-line');
        lines.forEach(function (line) {
            const methodSelect = line.querySelector('.split-method-select');
            const amtInput = line.querySelector('.split-payment-amount-input');
            const cashInput = line.querySelector('.split-cash-received-input');
            const changeDisplay = line.querySelector('.split-change-display');
            const changeInput = line.querySelector('.split-change-given-input');

            if (!methodSelect || !amtInput) return;

            const isCash = methodSelect.value === 'cash';
            const amount = parseFloat(amtInput.value) || 0;

            if (isCash && cashInput && changeDisplay) {
                const received = parseFloat(cashInput.value) || 0;
                const change = roundToCents(received - amount);

                if (change >= 0) {
                    changeDisplay.textContent = 'Vuelto: ' + formatCurrency(change);
                    changeDisplay.className = 'badge bg-light text-success border small split-change-display';
                    if (changeInput) changeInput.value = change.toFixed(2);
                } else {
                    changeDisplay.textContent = 'Faltan: ' + formatCurrency(Math.abs(change));
                    changeDisplay.className = 'badge bg-danger-subtle text-danger border small split-change-display';
                    if (changeInput) changeInput.value = '0.00';
                }
            }
        });
    }

    function calculatePaymentBalance() {
        if (!balanceAlert || !balanceMessage) return;

        const lines = paymentsContainer.querySelectorAll('.split-payment-line');
        let sumPayments = 0;
        let hasCashDeficit = false;

        lines.forEach(function (line) {
            const amtInput = line.querySelector('.split-payment-amount-input');
            const methodSelect = line.querySelector('.split-method-select');
            const cashInput = line.querySelector('.split-cash-received-input');

            const amt = parseFloat(amtInput ? amtInput.value : 0) || 0;
            sumPayments += amt;

            if (methodSelect && methodSelect.value === 'cash' && cashInput) {
                const received = parseFloat(cashInput.value) || 0;
                if (received < amt) {
                    hasCashDeficit = true;
                }
            }
        });

        sumPayments = roundToCents(sumPayments);
        const diff = roundToCents(currentFinalTotal - sumPayments);

        let count = 0;
        checkboxes.forEach(function (cb) { if (cb.checked) count++; });

        if (count === 0 || currentFinalTotal <= 0) {
            balanceAlert.className = 'alert alert-secondary py-2 px-3 small rounded-3 mt-2';
            balanceMessage.innerHTML = '<i class="bi bi-info-circle me-1"></i> Seleccione al menos un plato en la columna izquierda.';
            balanceAlert.classList.remove('d-none');
            if (btnSubmit) btnSubmit.disabled = true;
            return;
        }

        if (Math.abs(diff) < 0.05) {
            if (hasCashDeficit) {
                balanceAlert.className = 'alert alert-warning py-2 px-3 small rounded-3 mt-2';
                balanceMessage.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i> El efectivo recibido en una de las líneas no cubre el monto a abonar.';
                balanceAlert.classList.remove('d-none');
                if (btnSubmit) btnSubmit.disabled = true;
            } else {
                balanceAlert.className = 'alert alert-success py-2 px-3 small rounded-3 mt-2';
                balanceMessage.innerHTML = `<i class="bi bi-check-circle-fill me-1"></i> Total cubierto exactamente: ${formatCurrency(sumPayments)}`;
                balanceAlert.classList.remove('d-none');
                if (btnSubmit) btnSubmit.disabled = false;
            }
        } else if (diff > 0) {
            balanceAlert.className = 'alert alert-warning py-2 px-3 small rounded-3 mt-2';
            balanceMessage.innerHTML = `<i class="bi bi-exclamation-triangle-fill me-1"></i> ⚠️ Falta cubrir: ${formatCurrency(diff)} (Total a liquidar: ${formatCurrency(currentFinalTotal)})`;
            balanceAlert.classList.remove('d-none');
            if (btnSubmit) btnSubmit.disabled = true;
        } else {
            balanceAlert.className = 'alert alert-danger py-2 px-3 small rounded-3 mt-2';
            balanceMessage.innerHTML = `<i class="bi bi-exclamation-octagon-fill me-1"></i> ⚠️ El monto ingresado excede el total a liquidar por ${formatCurrency(Math.abs(diff))}`;
            balanceAlert.classList.remove('d-none');
            if (btnSubmit) btnSubmit.disabled = true;
        }
    }

    function updateRemoveButtonsState() {
        const lines = paymentsContainer.querySelectorAll('.split-payment-line');
        lines.forEach(function (line) {
            const removeBtn = line.querySelector('.remove-split-payment-btn');
            if (removeBtn) {
                removeBtn.disabled = (lines.length <= 1);
            }
        });
    }

    function attachPaymentLineEvents(line) {
        const methodSelect = line.querySelector('.split-method-select');
        const amtInput = line.querySelector('.split-payment-amount-input');
        const cashInput = line.querySelector('.split-cash-received-input');
        const cashRow = line.querySelector('.split-cash-details-row');
        const refRow = line.querySelector('.split-reference-row');

        if (methodSelect) {
            methodSelect.addEventListener('change', function () {
                const isCash = this.value === 'cash';
                if (cashRow) cashRow.style.setProperty('display', isCash ? 'flex' : 'none', 'important');
                if (refRow) refRow.style.setProperty('display', isCash ? 'none' : 'block', 'important');

                if (isCash && cashInput && amtInput) {
                    if (!cashInput.value || parseFloat(cashInput.value) === 0) {
                        cashInput.value = amtInput.value;
                    }
                }
                updatePaymentLinesChange();
                calculatePaymentBalance();
            });
        }

        if (amtInput) {
            amtInput.addEventListener('input', function () {
                if (methodSelect && methodSelect.value === 'cash' && cashInput) {
                    if (parseFloat(cashInput.value) < parseFloat(this.value)) {
                        cashInput.value = this.value;
                    }
                }
                updatePaymentLinesChange();
                calculatePaymentBalance();
            });
        }

        if (cashInput) {
            cashInput.addEventListener('input', function () {
                updatePaymentLinesChange();
                calculatePaymentBalance();
            });
        }
    }

    // Delegación de eventos para eliminar filas de pago en split bill
    if (paymentsContainer) {
        paymentsContainer.addEventListener('click', function (e) {
            const removeBtn = e.target.closest('.btn-remove-split-payment, .remove-split-payment-btn');
            if (!removeBtn) return;

            const lines = paymentsContainer.querySelectorAll('.split-payment-line');
            if (lines.length <= 1) {
                const amtInput = lines[0].querySelector('.split-payment-amount-input');
                if (amtInput) amtInput.value = 0;
                const cashInput = lines[0].querySelector('.split-cash-received-input');
                if (cashInput) cashInput.value = 0;
                updatePaymentLinesChange();
                calculateSplitTotals();
                return;
            }

            const line = removeBtn.closest('.split-payment-line');
            if (line) {
                line.remove();
                updateRemoveButtonsState();
                calculateSplitTotals();
            }
        });
    }

    // Inicializar eventos de la primera línea de pago
    const initialLine = paymentsContainer.querySelector('.split-payment-line');
    if (initialLine) {
        attachPaymentLineEvents(initialLine);
        updateRemoveButtonsState();
    }

    // Evento de checkboxes de platos
    checkboxes.forEach(function (cb) {
        cb.addEventListener('change', calculateSplitTotals);
    });

    // Marcar / Desmarcar todos
    if (btnSelectAll) {
        btnSelectAll.addEventListener('click', function () {
            allSelected = !allSelected;
            checkboxes.forEach(function (cb) { cb.checked = allSelected; });
            calculateSplitTotals();
        });
    }

    // Cambios en Descuento y Switches
    if (discountInput) {
        discountInput.addEventListener('input', calculateSplitTotals);
    }
    if (switchTip) {
        switchTip.addEventListener('change', calculateSplitTotals);
    }
    if (switchTax) {
        switchTax.addEventListener('change', calculateSplitTotals);
    }

    // Agregar nueva línea de pago
    if (btnAddPaymentMethod) {
        btnAddPaymentMethod.addEventListener('click', function () {
            const lines = paymentsContainer.querySelectorAll('.split-payment-line');
            let sumExisting = 0;
            lines.forEach(function (l) {
                const a = l.querySelector('.split-payment-amount-input');
                sumExisting += parseFloat(a ? a.value : 0) || 0;
            });

            const remainingToCover = Math.max(0, roundToCents(currentFinalTotal - sumExisting));
            const idx = paymentLineIndex++;

            const newLine = document.createElement('div');
            newLine.className = 'split-payment-line payment-method-row card border p-2 mb-2 rounded-3 bg-white';
            newLine.dataset.index = idx;
            newLine.innerHTML = `
                <div class="d-flex align-items-center gap-2">
                    <div style="flex: 1 1 130px; min-width: 115px;">
                        <select name="payments[${idx}][method]" class="form-select form-select-sm split-method-select" required>
                            <option value="transfer" selected>Transferencia / Nequi</option>
                            <option value="cash">Efectivo</option>
                            <option value="card">Tarjeta</option>
                            <option value="other">Otro</option>
                        </select>
                    </div>
                    <div style="flex: 1 1 120px; min-width: 110px;">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">$</span>
                            <input type="number" step="0.01" min="0.01" name="payments[${idx}][amount]" class="form-control text-end split-payment-amount-input fw-bold" value="${remainingToCover}" required>
                        </div>
                    </div>
                    <button type="button" 
                            class="btn btn-outline-danger btn-remove-split-payment remove-split-payment-btn flex-shrink-0 d-flex align-items-center justify-content-center" 
                            style="width: 38px; height: 38px; position: relative; z-index: 2;" 
                            title="Eliminar fila de pago">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>

                <div class="split-cash-details-row w-100 d-flex align-items-center justify-content-between gap-2 mt-2 pt-2 border-top" style="display: none !important;">
                    <div class="d-flex align-items-center gap-1" style="max-width: 175px;">
                        <span class="text-muted small" style="font-size: 0.72rem; white-space: nowrap;">Recibido:</span>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text" style="font-size: 0.75rem;">$</span>
                            <input type="number" step="0.01" min="0" name="payments[${idx}][cash_received]" class="form-control text-end split-cash-received-input" placeholder="0" value="${remainingToCover}">
                        </div>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-light text-success border small split-change-display">Vuelto: $0</span>
                        <input type="hidden" name="payments[${idx}][change_given]" class="split-change-given-input" value="0.00">
                    </div>
                </div>

                <div class="split-reference-row w-100 mt-2 pt-2 border-top">
                    <input type="text" name="payments[${idx}][reference]" class="form-control form-control-sm split-reference-input" placeholder="N° Comprobante / Voucher (opcional)" maxlength="60">
                </div>
            `;

            paymentsContainer.appendChild(newLine);
            attachPaymentLineEvents(newLine);
            updateRemoveButtonsState();
            calculatePaymentBalance();
        });
    }

    // Delegación de clic para atajos de efectivo rápido
    if (quickCashContainer) {
        quickCashContainer.addEventListener('click', function (e) {
            const btn = e.target.closest('.split-quick-cash-btn');
            if (!btn) return;

            const amt = parseFloat(btn.dataset.amt || 0);
            const lines = paymentsContainer.querySelectorAll('.split-payment-line');
            if (lines.length === 0) return;

            const firstLine = lines[0];
            const methodSelect = firstLine.querySelector('.split-method-select');
            const amtInput = firstLine.querySelector('.split-payment-amount-input');
            const cashInput = firstLine.querySelector('.split-cash-received-input');

            if (methodSelect && methodSelect.value !== 'cash') {
                methodSelect.value = 'cash';
                methodSelect.dispatchEvent(new Event('change'));
            }

            if (lines.length === 1 && amtInput) {
                amtInput.value = currentFinalTotal;
            }

            if (cashInput) {
                cashInput.value = amt;
            }

            updatePaymentLinesChange();
            calculatePaymentBalance();
        });
    }

    // Impresión de Pre-cuenta con parámetros enriquecidos
    if (btnPrecount) {
        btnPrecount.addEventListener('click', function () {
            const selectedIds = [];
            checkboxes.forEach(function (cb) {
                if (cb.checked) selectedIds.push(cb.value);
            });

            if (selectedIds.length === 0) return;

            const custId = customerSelect ? customerSelect.value : '';
            const disc = parseFloat(discountInput ? discountInput.value : 0) || 0;
            const tip = switchTip && switchTip.checked ? 1 : 0;
            const tax = switchTax && switchTax.checked ? 1 : 0;

            let url = "{{ route('restaurant.orders.print-split-precount', $order) }}?items[]=" + selectedIds.join('&items[]=');
            if (custId) url += '&customer_id=' + encodeURIComponent(custId);
            if (disc > 0) url += '&discount_percentage=' + encodeURIComponent(disc);
            if (tip) url += '&include_tip=1';
            if (tax) url += '&include_tax=1';

            window.open(url, '_blank', 'width=450,height=650,scrollbars=yes');
        });
    }

    // Submit del formulario
    if (splitForm && btnSubmit) {
        splitForm.addEventListener('submit', function () {
            btnSubmit.disabled = true;
            btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Procesando pago...';
        });
    }

    // Cálculo inicial
    calculateSplitTotals();
});
</script>
@endif

@if(session('print_split_ticket_id'))
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            try {
                const printUrl = "{{ route('restaurant.orders.print-split-ticket', [$order, session('print_split_ticket_id')]) }}";
                const popup = window.open(printUrl, '_blank', 'width=450,height=650,scrollbars=yes');
                if (!popup || popup.closed || typeof popup.closed === 'undefined') {
                    console.info('Aviso: La ventana emergente de impresión de comprobante parcial fue bloqueada por el navegador.');
                }
            } catch (e) {
                console.warn('Impresión de ticket parcial no ejecutada:', e);
            }
        });
    </script>
@endif
@endsection
