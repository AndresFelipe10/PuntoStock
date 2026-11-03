@extends('layouts.app')

@section('title', 'Tablero de Domicilios y Despacho')

@section('content')
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark">
                <i class="bi bi-bicycle text-primary me-2"></i>Tablero de Domicilios y Despacho
            </h1>
            <p class="text-muted small mb-0">Control en tiempo real de pedidos a domicilio y para llevar.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('restaurant.orders.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-receipt me-1"></i> Todas las Comandas
            </a>
            <a href="{{ route('restaurant.orders.create-delivery') }}" class="btn btn-primary fw-bold">
                <i class="bi bi-plus-circle me-1"></i> Nuevo Domicilio / Para Llevar
            </a>
        </div>
    </div>

    {{-- Columnas Kanban --}}
    <div class="row g-4">
        {{-- 1. En Cocina / Preparación --}}
        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-light">
                <div class="card-header bg-white border-0 pt-3 pb-2 px-3 d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-dark">
                        <i class="bi bi-fire text-warning me-1"></i> 1. En Preparación
                    </span>
                    <span class="badge bg-warning text-dark rounded-pill">{{ $kitchenOrders->count() }}</span>
                </div>

                <div class="card-body p-3">
                    @forelse($kitchenOrders as $order)
                        <div class="card border-0 shadow-sm rounded-3 mb-3 p-3">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="fw-bold text-primary fs-5">{{ $order->order_number }}</span>
                                <span class="badge {{ $order->order_type === 'delivery' ? 'bg-info text-dark' : 'bg-secondary' }}">
                                    <i class="bi {{ $order->order_type === 'delivery' ? 'bi-bicycle' : 'bi-bag' }} me-1"></i>
                                    {{ $order->order_type === 'delivery' ? 'Domicilio' : 'Para Llevar' }}
                                </span>
                            </div>

                            <h6 class="fw-bold text-dark mb-1">{{ $order->client_display_name }}</h6>
                            @if($order->delivery_phone)
                                <p class="text-muted small mb-1">
                                    <i class="bi bi-telephone me-1"></i>{{ $order->delivery_phone }}
                                </p>
                            @endif
                            @if($order->delivery_address)
                                <p class="text-muted small mb-2 text-truncate" title="{{ $order->delivery_address }}">
                                    <i class="bi bi-geo-alt me-1"></i>{{ $order->delivery_address }}
                                </p>
                            @endif

                            <div class="d-flex justify-content-between align-items-center small text-muted border-top pt-2 mb-3">
                                <span>{{ $order->items->count() }} platos ordenados</span>
                                <span class="fw-bold text-dark fs-6">${{ number_format($order->total, 2) }}</span>
                            </div>

                            <div class="d-flex gap-2">
                                <a href="{{ route('restaurant.orders.dispatch-ticket', $order) }}" target="_blank" class="btn btn-sm btn-outline-secondary flex-grow-1" title="Imprimir Tirilla Despacho">
                                    <i class="bi bi-printer me-1"></i> Tirilla
                                </a>
                                @if($order->order_type === 'delivery')
                                    <form action="{{ route('restaurant.orders.status.update', $order) }}" method="POST" class="d-inline flex-grow-1">
                                        @csrf
                                        <input type="hidden" name="status" value="dispatched">
                                        <button type="submit" class="btn btn-sm btn-primary w-100 fw-bold">
                                            <i class="bi bi-send me-1"></i> Despachar
                                        </button>
                                    </form>
                                @else
                                    <form action="{{ route('restaurant.deliveries.pickup', $order) }}" method="POST" class="d-inline flex-grow-1">
                                        @csrf
                                        <button type="submit" 
                                                class="btn btn-sm btn-primary w-100 fw-bold d-inline-flex align-items-center justify-content-center px-3 btn-mark-picked-up"
                                                data-order-id="{{ $order->id }}"
                                                data-url="{{ route('restaurant.deliveries.pickup', $order) }}"
                                                title="Marcar como recogido por el cliente">
                                            <i class="bi bi-bag-check me-1"></i> Recogido
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-5 text-muted small">
                            <i class="bi bi-check-all display-6 d-block mb-1 text-muted"></i>
                            No hay pedidos pendientes en cocina.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- 2. Despachado / En Camino --}}
        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-light">
                <div class="card-header bg-white border-0 pt-3 pb-2 px-3 d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-dark">
                        <i class="bi bi-send-fill text-primary me-1"></i> 2. En Camino / Despachado
                    </span>
                    <span class="badge bg-primary rounded-pill">{{ $dispatchedOrders->count() }}</span>
                </div>

                <div class="card-body p-3">
                    @forelse($dispatchedOrders as $order)
                        <div class="card border-0 shadow-sm rounded-3 mb-3 p-3 border-start border-4 border-primary">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="fw-bold text-primary fs-5">{{ $order->order_number }}</span>
                                <span class="badge bg-primary">En Camino</span>
                            </div>

                            <h6 class="fw-bold text-dark mb-1">{{ $order->client_display_name }}</h6>
                            @if($order->delivery_phone)
                                <p class="text-muted small mb-1">
                                    <i class="bi bi-telephone me-1"></i>{{ $order->delivery_phone }}
                                </p>
                            @endif
                            @if($order->delivery_address)
                                <p class="text-dark small fw-semibold mb-1">
                                    <i class="bi bi-geo-alt-fill text-danger me-1"></i>{{ $order->delivery_address }}
                                </p>
                            @endif
                            @if($order->delivery_notes)
                                <div class="bg-white rounded p-1 mb-2 small text-muted border">
                                    <i class="bi bi-info-circle me-1"></i>{{ $order->delivery_notes }}
                                </div>
                            @endif

                            <div class="d-flex justify-content-between align-items-center border-top pt-2 mb-3">
                                <span class="small text-muted">Contraentrega:</span>
                                <span class="fw-bold text-success fs-6">${{ number_format($order->total, 2) }}</span>
                            </div>

                            <div class="d-flex gap-2">
                                <a href="{{ route('restaurant.orders.dispatch-ticket', $order) }}" target="_blank" class="btn btn-sm btn-outline-secondary" title="Reimprimir Tirilla">
                                    <i class="bi bi-printer"></i>
                                </a>
                                <form action="{{ route('restaurant.orders.status.update', $order) }}" method="POST" class="d-inline flex-grow-1">
                                    @csrf
                                    <input type="hidden" name="status" value="delivered">
                                    <button type="submit" class="btn btn-sm btn-success w-100 fw-bold">
                                        <i class="bi bi-check2-circle me-1"></i> Marcar Entregado
                                    </button>
                                </form>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-5 text-muted small">
                            <i class="bi bi-bicycle display-6 d-block mb-1 text-muted"></i>
                            No hay repartidores en ruta actualmente.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- 3. Entregado (Por Liquidar en Caja) --}}
        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-light">
                <div class="card-header bg-white border-0 pt-3 pb-2 px-3 d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-dark">
                        <i class="bi bi-check-circle-fill text-success me-1"></i> 3. Entregados
                    </span>
                    <span class="badge bg-success rounded-pill">{{ $deliveredOrders->count() }}</span>
                </div>

                <div class="card-body p-3">
                    @forelse($deliveredOrders as $order)
                        <div class="card border-0 shadow-sm rounded-3 mb-3 p-3">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="fw-bold text-dark">{{ $order->order_number }}</span>
                                <span class="badge bg-success">Entregado</span>
                            </div>

                            <h6 class="fw-bold text-dark mb-1">{{ $order->client_display_name }}</h6>
                            <p class="text-muted small mb-2">
                                Total: <strong>${{ number_format($order->total, 2) }}</strong>
                            </p>

                            <div class="d-flex gap-2 mt-2">
                                <a href="{{ route('restaurant.orders.show', $order) }}" class="btn btn-outline-primary btn-sm flex-fill">
                                    <i class="bi bi-eye me-1"></i> Ver Detalle
                                </a>
                                <a href="{{ route('restaurant.orders.show', $order) }}#cobrar" class="btn btn-success btn-sm flex-fill fw-semibold btn-settle-order" data-order-id="{{ $order->id }}">
                                    <i class="bi bi-cash-coin me-1"></i> Cuadrar Pedido
                                </a>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-5 text-muted small">
                            <i class="bi bi-inbox display-6 d-block mb-1 text-muted"></i>
                            No hay pedidos en este estado.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

@if(session('print_kitchen_ticket_id'))
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const printUrl = "{{ route('restaurant.orders.kitchen-ticket', session('print_kitchen_ticket_id')) }}";
            window.open(printUrl, '_blank', 'width=450,height=650,scrollbars=yes');
        });
    </script>
@endif
@endsection
