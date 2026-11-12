@extends('layouts.app')

@section('title', 'Nueva Venta')

@section('content')
<style>
/* Ocultar flechas/spinners nativos en inputs de cantidad del carrito */
.qty-input::-webkit-outer-spin-button,
.qty-input::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}
.qty-input[type=number] {
    -moz-appearance: textfield;
}
</style>
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h3 class="fw-bold mb-1">Nueva Venta</h3>
        <p class="text-muted mb-0">Registrar una venta de productos</p>
    </div>
    <a href="{{ route('sales.index') }}" class="btn btn-outline-secondary rounded-pill px-3">
        <i class="bi bi-arrow-left me-1"></i> Volver al Historial
    </a>
</div>

@if ($errors->any())
    <div class="alert alert-danger rounded-3 mb-4">
        <i class="bi bi-exclamation-triangle-fill me-2"></i>
        <strong>Error:</strong>
        <ul class="mb-0 mt-1">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('sales.store') }}" id="saleForm">
    @csrf
    <div class="row g-4">
        {{-- Panel izquierdo: Buscar y agregar productos --}}
        <div class="col-lg-8">
            <div class="card card-custom p-4 mb-4">
                <h5 class="fw-bold mb-3 border-bottom pb-2"><i class="bi bi-cart-plus me-2 text-primary"></i>Agregar Productos</h5>

                <div id="stock-error-container" class="w-100"></div>

                {{-- Selector Dual Sincronizado: Buscador Rápido y Dropdown de Catálogo --}}
                <div class="row g-2 mb-3">
                    <div class="col-md-6 position-relative">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0" id="searchScanIconContainer"><i class="bi bi-upc-scan text-muted"></i></span>
                            <input type="text" id="productSearch" class="form-control border-start-0"
                                   placeholder="Buscar por nombre o SKU..." autocomplete="off">
                        </div>
                        {{-- Resultados flotantes de búsqueda inmediata --}}
                        <div id="searchResults" class="list-group position-absolute w-100 shadow-lg border rounded-3 overflow-auto" 
                             style="display:none; max-height:280px; z-index: 1050; top: 100%; left: 0; background: #fff;"></div>
                    </div>
                    <div class="col-md-6">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-box-seam text-muted"></i></span>
                            <select id="product_dropdown_pos" class="form-select border-start-0">
                                <option value="">-- O selecciona un producto del catálogo --</option>
                                @php
                                    $allProds = collect($products ?? []);
                                    $menuProducts = $allProds->filter(fn($p) => ($p->product_type ?? '') === 'dish' || ($p->product_type ?? '') === 'menu');
                                    $merchandiseProducts = $allProds->reject(fn($p) => ($p->product_type ?? '') === 'dish' || ($p->product_type ?? '') === 'menu');
                                @endphp

                                @if($menuProducts->isNotEmpty() && $merchandiseProducts->isNotEmpty())
                                    <optgroup label="📦 Mercancía de Mostrador">
                                        @foreach($merchandiseProducts as $product)
                                            @php
                                                $stockVal = (float) ($product->available_stock ?? $product->stock);
                                                $stockFormatted = (float)$stockVal == (int)$stockVal ? (int)$stockVal : $stockVal;
                                            @endphp
                                            <option value="{{ $product->id }}" 
                                                    data-id="{{ $product->id }}"
                                                    data-name="{{ $product->name }}"
                                                    data-sku="{{ $product->sku }}"
                                                    data-price="{{ $product->sale_price }}"
                                                    data-is-standard="1"
                                                    data-stock="{{ $stockVal }}"
                                                    title="Stock: {{ $stockFormatted }}"
                                                    {{ $stockVal <= 0 ? 'disabled' : '' }}>
                                                @if($stockVal <= 0)
                                                    {{ $product->name }} — ${{ number_format($product->sale_price, 0, ',', '.') }}  ·  Agotado
                                                @else
                                                    {{ $product->name }} — ${{ number_format($product->sale_price, 0, ',', '.') }}  ·  {{ $stockFormatted }} disp.
                                                @endif
                                            </option>
                                        @endforeach
                                    </optgroup>
                                    <optgroup label="🍽️ Carta / Menú de Cocina">
                                        @foreach($menuProducts as $product)
                                            <option value="{{ $product->id }}" 
                                                    data-id="{{ $product->id }}"
                                                    data-name="{{ $product->name }}"
                                                    data-sku="{{ $product->sku }}"
                                                    data-price="{{ $product->sale_price }}"
                                                    data-is-standard="0"
                                                    data-stock="{{ (float) ($product->available_stock ?? $product->stock) }}">
                                                {{ $product->name }} — ${{ number_format($product->sale_price, 0, ',', '.') }}
                                            </option>
                                        @endforeach
                                    </optgroup>
                                @else
                                    <optgroup label="{{ $menuProducts->isNotEmpty() ? '🍽️ Carta / Menú de Cocina' : '📦 Mercancía de Mostrador' }}">
                                        @foreach($allProds as $product)
                                            @php
                                                $isStd = $product->isStandard();
                                                $stockVal = (float) ($product->available_stock ?? $product->stock);
                                                $stockFormatted = (float)$stockVal == (int)$stockVal ? (int)$stockVal : $stockVal;
                                            @endphp
                                            <option value="{{ $product->id }}" 
                                                    data-id="{{ $product->id }}"
                                                    data-name="{{ $product->name }}"
                                                    data-sku="{{ $product->sku }}"
                                                    data-price="{{ $product->sale_price }}"
                                                    data-is-standard="{{ $isStd ? '1' : '0' }}"
                                                    data-stock="{{ $stockVal }}"
                                                    title="Stock: {{ $stockFormatted }}"
                                                    {{ ($isStd && $stockVal <= 0) ? 'disabled' : '' }}>
                                                @if($isStd)
                                                    @if($stockVal <= 0)
                                                        {{ $product->name }} — ${{ number_format($product->sale_price, 0, ',', '.') }}  ·  Agotado
                                                    @else
                                                        {{ $product->name }} — ${{ number_format($product->sale_price, 0, ',', '.') }}  ·  {{ $stockFormatted }} disp.
                                                    @endif
                                                @else
                                                    {{ $product->name }} — ${{ number_format($product->sale_price, 0, ',', '.') }}
                                                @endif
                                            </option>
                                        @endforeach
                                    </optgroup>
                                @endif
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Tabla del carrito --}}
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="cartTable">
                        <thead class="bg-light">
                            <tr>
                                <th>Producto</th>
                                <th class="text-center" style="width:90px;">Stock</th>
                                <th class="text-center" style="width:140px;">Cantidad</th>
                                <th class="text-end" style="width:130px;">P. Unitario</th>
                                <th class="text-end" style="width:130px;">Subtotal</th>
                                <th class="text-center" style="width:50px;"></th>
                            </tr>
                        </thead>
                        <tbody id="cartBody">
                            <tr id="emptyCartRow">
                                <td colspan="6" class="text-center text-muted py-4">
                                    <i class="bi bi-cart3 fs-3 d-block mb-1 opacity-50"></i>
                                    Busque y agregue productos al carrito
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Panel derecho: Resumen y datos de la venta --}}
        <div class="col-lg-4">
            <div class="card card-custom p-4 mb-4">
                {{-- SECCIÓN 1: CABECERA / CONTEXTO --}}
                <div class="border-bottom pb-3 mb-3">
                    <h5 class="fw-bold mb-3"><i class="bi bi-receipt me-2 text-primary"></i>Datos de la Venta</h5>

                    {{-- Fecha y Hora (compacta con indicador de fecha colombiana) --}}
                    <div class="mb-3">
                        <label for="sale_date" class="form-label small fw-semibold text-muted d-flex justify-content-between align-items-center mb-1">
                            <span><i class="bi bi-clock-history me-1"></i>Fecha y Hora</span>
                            <span class="badge bg-light text-secondary border font-monospace fw-normal">{{ now()->format('d/m/Y') }}</span>
                        </label>
                        <input type="datetime-local" id="sale_date" name="sale_date" class="form-control form-control-sm"
                               value="{{ old('sale_date', now()->format('Y-m-d\TH:i')) }}" required>
                    </div>

                    {{-- Selector de Cliente --}}
                    <div>
                        <label class="form-label small fw-semibold text-muted d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-person me-1"></i>Cliente</span>
                            <small class="text-secondary">Opcional</small>
                        </label>

                        {{-- Hidden input with authoritative customer_id --}}
                        <input type="hidden" id="customer_id" name="customer_id" 
                               value="{{ old('customer_id', $selectedCustomer?->id ?? '') }}">

                        {{-- Chip del cliente seleccionado --}}
                        <div id="customer_chip_container" class="mb-2 p-2 rounded-3 border bg-light d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center overflow-hidden me-2">
                                <i id="customer_chip_icon" class="bi {{ ($selectedCustomer || old('customer_id')) ? 'bi-person-check-fill text-success' : 'bi-person text-secondary' }} fs-5 me-2 flex-shrink-0"></i>
                                <div class="small text-truncate">
                                    <span id="customer_chip_name" class="fw-bold d-block text-truncate">
                                        {{ $selectedCustomer ? $selectedCustomer->name : ($defaultCustomerName ?? config('sales.default_customer_name')) }}
                                    </span>
                                    <span id="customer_chip_doc" class="text-muted font-monospace small">
                                        {{ $selectedCustomer ? ($selectedCustomer->document ?? $selectedCustomer->identification_number) : ($defaultCustomerDocument ?? config('sales.default_customer_document')) }}
                                    </span>
                                </div>
                            </div>
                            <button type="button" id="btn_remove_customer" class="btn btn-sm btn-outline-danger py-0 px-2 rounded-pill {{ ($selectedCustomer || old('customer_id')) ? '' : 'd-none' }}" title="Quitar cliente (Consumidor Final)">
                                <i class="bi bi-x"></i> Quitar
                            </button>
                        </div>

                        {{-- Campo de búsqueda y botón '+' --}}
                        <div class="position-relative">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                                <input type="text" id="customer_search" class="form-control" 
                                       placeholder="Buscar cliente (Cédula/NIT o nombre)..." 
                                       autocomplete="off">
                                <button type="button" id="btnOpenQuickCustomer" class="btn btn-outline-primary bg-white text-primary d-inline-flex align-items-center justify-content-center px-3 flex-shrink-0 btn-quick-customer-trigger btn-open-quick-customer" data-bs-toggle="modal" data-bs-target="#quickCustomerModal" title="Crear Cliente Rápido" aria-label="Crear Cliente Rápido">
                                    <i class="bi bi-person-plus fs-5" style="line-height: 1;"></i>
                                </button>
                            </div>
                            {{-- Dropdown de resultados --}}
                            <div id="customer_search_results" class="list-group position-absolute shadow w-100 mt-1 d-none" style="z-index: 1060; max-height: 220px; overflow-y: auto;"></div>
                        </div>
                    </div>
                </div>

                {{-- SECCIÓN 2: LIQUIDACIÓN Y RESUMEN DE TOTALES --}}
                <div class="border-bottom pb-3 mb-3">
                    <h6 class="fw-bold text-uppercase text-muted small mb-2"><i class="bi bi-calculator me-1"></i>Resumen de Cobro</h6>

                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="text-muted small">Subtotal</span>
                        <span class="fw-semibold font-monospace" id="displaySubtotal">$0</span>
                    </div>

                    <div class="mb-2">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label for="discount_percentage" class="form-label small text-muted mb-0">Descuento (%)</label>
                            <span class="fw-semibold text-danger font-monospace small" id="displayDiscount">-$0</span>
                        </div>
                        <div class="input-group">
                            <button type="button" class="btn btn-outline-secondary px-3" id="btnDiscountMinus" title="Disminuir descuento" style="min-height: 38px;">
                                <i class="bi bi-dash-lg"></i>
                            </button>
                            <input type="number" id="discount_percentage" name="discount_percentage" class="form-control text-center fw-bold font-monospace"
                                   value="{{ old('discount_percentage', 0) }}" min="0" max="100" step="1" placeholder="0" style="min-height: 38px;">
                            <button type="button" class="btn btn-outline-secondary px-3" id="btnDiscountPlus" title="Aumentar descuento" style="min-height: 38px;">
                                <i class="bi bi-plus-lg"></i>
                            </button>
                            <span class="input-group-text bg-light fw-bold">%</span>
                        </div>
                        <div id="discountFeedback" class="form-text text-info mt-1" style="display:none;">
                            <i class="bi bi-info-circle me-1"></i>Equivale a <strong id="discountMoneyPreview">$0</strong> de descuento
                        </div>
                    </div>

                    {{-- Caja destacada de Total Final a Cobrar --}}
                    <div class="bg-light p-3 rounded-3 border mt-3 text-center">
                        <span class="text-muted small d-block text-uppercase fw-semibold mb-1">Total a Cobrar</span>
                        <span class="fw-bold fs-3 text-primary font-monospace d-block" id="displayTotal">$0</span>
                    </div>
                </div>

                {{-- SECCIÓN 3: RECEPCIÓN Y MÉTODOS DE PAGO --}}
                <div class="border-bottom pb-3 mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label small fw-semibold text-muted mb-0">
                            <i class="bi bi-wallet2 me-1"></i>Métodos de Pago
                        </label>
                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill py-0 px-2" id="btnAddPaymentLine" title="Agregar método de pago">
                            <i class="bi bi-plus-circle me-1"></i>Agregar método
                        </button>
                    </div>

                    {{-- Contenedor dinámico de líneas de pago --}}
                    <div id="payment_lines_container" class="d-flex flex-column gap-2 mb-2"></div>

                    {{-- Indicador dinámico de saldo / balance --}}
                    <div id="payment_balance_feedback" class="small p-2 rounded-3 text-center fw-semibold mb-2"></div>

                    {{-- Contenedor de inputs ocultos para submit --}}
                    <div id="hidden_payments_inputs"></div>
                    <input type="hidden" name="payment_method" id="hidden_payment_method" value="cash">

                    @error('payments')
                        <div class="alert alert-danger small py-1 px-2 mt-1 mb-0">{{ $message }}</div>
                    @enderror
                </div>

                {{-- SECCIÓN 4: METADATOS Y ACCIÓN --}}
                <div>
                    <div class="mb-3">
                        <label for="notes" class="form-label small fw-semibold text-muted"><i class="bi bi-chat-left-text me-1"></i>Notas</label>
                        <textarea id="notes" name="notes" class="form-control" rows="2" maxlength="1000"
                                  placeholder="Notas u observaciones adicionales...">{{ old('notes') }}</textarea>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 rounded-pill py-2 fw-bold shadow-sm" id="btnSubmitSale" disabled>
                        <i class="bi bi-check2-circle me-2"></i> Registrar Venta
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>

{{-- Modal de creación rápida de cliente --}}
<div class="modal fade" id="quickCustomerModal" tabindex="-1" aria-labelledby="quickCustomerModalLabel" aria-hidden="true">
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
                    <input type="text" id="quick_customer_document" class="form-control font-monospace" placeholder="Ej: 1144001234 o 900123456-1" inputmode="numeric" required>
                    <div class="form-text small">Solo dígitos (5-15) y guión opcional con dígito de verificación.</div>
                </div>

                <div class="mb-3">
                    <label for="quick_customer_name" class="form-label small fw-semibold text-muted">Nombre o Razón Social <span class="text-danger">*</span></label>
                    <input type="text" id="quick_customer_name" class="form-control" placeholder="Nombre completo o razón social" required>
                    <div class="form-text small">Debe contener letras (no se permiten únicamente números).</div>
                </div>

                <div class="mb-3">
                    <label for="quick_customer_phone" class="form-label small fw-semibold text-muted">Teléfono <span class="text-muted">(opcional)</span></label>
                    <input type="tel" id="quick_customer_phone" class="form-control font-monospace" placeholder="Ej: 3151234567" inputmode="tel">
                    <div class="form-text small">Solo números y símbolos telefónicos (+, -).</div>
                </div>

                <div class="mb-3">
                    <label for="quick_customer_email" class="form-label small fw-semibold text-muted">Correo Electrónico <span class="text-muted">(opcional)</span></label>
                    <input type="email" id="quick_customer_email" class="form-control" placeholder="cliente@ejemplo.com">
                </div>
            </div>
            <div class="modal-footer border-top bg-light">
                <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" id="btn_save_quick_customer" class="btn btn-primary btn-sm rounded-pill px-4">
                    <i class="bi bi-save me-1"></i> Guardar Cliente
                </button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('productSearch');
    const searchResults = document.getElementById('searchResults');
    const cartBody = document.getElementById('cartBody');
    const emptyCartRow = document.getElementById('emptyCartRow');
    const discountInput = document.getElementById('discount_percentage');
    const discountFeedback = document.getElementById('discountFeedback');
    const discountMoneyPreview = document.getElementById('discountMoneyPreview');
    const btnSubmit = document.getElementById('btnSubmitSale');

    let cart = [];
    let searchTimeout = null;
    let activeProductAbort = null;
    const productSearchCache = new Map();
    let highlightedProductIndex = -1;
    const scanIconContainer = document.getElementById('searchScanIconContainer');

    // Catálogo precargado para filtrado reactivo instantáneo (0ms)
    const localCatalog = @json($catalogProducts ?? []);

    function searchLocalProducts(query) {
        const q = query.trim().toLowerCase();
        if (!q) return [];
        return localCatalog.filter(p => {
            const name = (p.name || '').toLowerCase();
            const sku = (p.sku || '').toLowerCase();
            return name.includes(q) || sku.includes(q);
        }).slice(0, 15);
    }

    function setProductLoading(isLoading) {
        if (!scanIconContainer) return;
        if (isLoading) {
            scanIconContainer.innerHTML = '<span class="spinner-border spinner-border-sm text-primary" role="status" style="width: 0.85rem; height: 0.85rem;"></span>';
        } else {
            scanIconContainer.innerHTML = '<i class="bi bi-upc-scan text-muted"></i>';
        }
    }

    function renderProductResults(products, query) {
        searchResults.innerHTML = '';
        highlightedProductIndex = -1;

        if (!Array.isArray(products) || products.length === 0) {
            searchResults.innerHTML = '<div class="list-group-item text-muted small py-2"><i class="bi bi-info-circle me-1"></i>No se encontraron productos para "' + escapeHtml(query) + '"</div>';
            searchResults.style.display = 'block';
            return;
        }

        products.forEach((p, idx) => {
            const inCart = cart.find(c => c.product_id === p.id);
            const isStandard = p.is_standard !== undefined ? Boolean(p.is_standard) : (p.product_type ? p.product_type === 'standard' : true);
            const stockVal = parseFloat(p.available_stock !== undefined ? p.available_stock : (p.stock || 0));
            const isOutOfStock = isStandard && stockVal <= 0;
            const disabled = inCart || isOutOfStock ? 'opacity-50 disabled' : '';

            let stockBadgeHtml = '';
            if (isStandard) {
                const stockFmt = Number.isInteger(stockVal) ? stockVal : stockVal.toFixed(2);
                if (isOutOfStock) {
                    stockBadgeHtml = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle mt-1" style="font-size:0.72rem;">Agotado</span>';
                } else {
                    stockBadgeHtml = `<span class="badge bg-light text-secondary border mt-1" style="font-size:0.72rem;">${stockFmt} disp.</span>`;
                }
            } else {
                stockBadgeHtml = '<span class="badge bg-success-subtle text-success border border-success-subtle mt-1" style="font-size:0.72rem;">Plato</span>';
            }

            const itemPrice = p.sale_price !== undefined ? p.sale_price : (p.price !== undefined ? p.price : 0);

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = `list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2 px-3 product-result-item ${disabled}`;
            if (isOutOfStock) {
                btn.disabled = true;
            }
            btn.dataset.index = idx;
            btn.innerHTML = `
                <div class="text-truncate me-2">
                    <span class="fw-semibold text-dark">${escapeHtml(p.name)}</span>
                    <small class="text-muted ms-2 font-monospace">${escapeHtml(p.sku || '')}</small>
                    ${inCart ? '<span class="badge bg-secondary-subtle text-secondary border font-monospace ms-1" style="font-size:0.7rem;">En carrito</span>' : ''}
                </div>
                <div class="text-end flex-shrink-0 d-flex flex-column align-items-end justify-content-center">
                    <span class="fw-bold text-primary font-monospace">$${formatNumber(itemPrice)}</span>
                    ${stockBadgeHtml}
                </div>
            `;
            if (!inCart && !isOutOfStock) {
                btn.addEventListener('click', () => {
                    addToCart({
                        ...p,
                        sale_price: itemPrice,
                        price: itemPrice,
                        is_standard: isStandard,
                        stock: stockVal,
                        available_stock: stockVal
                    });
                });
            }
            searchResults.appendChild(btn);
        });

        searchResults.style.display = 'block';
    }

    function executeProductSearch(query, isImmediate = false) {
        if (activeProductAbort) {
            activeProductAbort.abort();
            activeProductAbort = null;
        }

        const trimmed = query.trim();
        if (trimmed.length < 1) {
            searchResults.style.display = 'none';
            searchResults.innerHTML = '';
            setProductLoading(false);
            return;
        }

        const cacheKey = trimmed.toLowerCase();
        if (productSearchCache.has(cacheKey)) {
            const cached = productSearchCache.get(cacheKey);
            setProductLoading(false);
            if (isImmediate && cached.length === 1 && !cart.find(c => c.product_id === cached[0].id)) {
                addToCart(cached[0]);
                return;
            }
            renderProductResults(cached, trimmed);
            return;
        }

        setProductLoading(true);
        activeProductAbort = new AbortController();

        fetch(`{{ route('api.products.search') }}?q=${encodeURIComponent(trimmed)}`, {
            signal: activeProductAbort.signal,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(products => {
            setProductLoading(false);
            activeProductAbort = null;
            productSearchCache.set(cacheKey, products);

            // Si es escaneo directo (código de barras o Enter) y hay exactamente 1 coincidencia
            if (isImmediate && products.length === 1 && !cart.find(c => c.product_id === products[0].id)) {
                addToCart(products[0]);
                return;
            }

            renderProductResults(products, trimmed);
        })
        .catch(err => {
            if (err.name !== 'AbortError') {
                setProductLoading(false);
                activeProductAbort = null;
            }
        });
    }

    // ── Búsqueda reactiva inmediata (0ms local + debounce servidor) ──
    searchInput.addEventListener('input', function () {
        clearTimeout(searchTimeout);
        const q = this.value;
        const trimmed = q.trim();
        if (trimmed.length < 1) {
            if (activeProductAbort) {
                activeProductAbort.abort();
                activeProductAbort = null;
            }
            searchResults.style.display = 'none';
            searchResults.innerHTML = '';
            setProductLoading(false);
            return;
        }

        // Búsqueda instantánea en catálogo local en memoria (0ms)
        const localMatches = searchLocalProducts(trimmed);
        if (localMatches.length > 0) {
            renderProductResults(localMatches, trimmed);
        }

        searchTimeout = setTimeout(() => {
            executeProductSearch(q, false);
        }, 180);
    });

    // ── Soporte Enter (Lectores de código de barras) y flechas de teclado ──
    searchInput.addEventListener('keydown', function (e) {
        const items = searchResults.querySelectorAll('.product-result-item:not(.opacity-50)');

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (items.length > 0) {
                highlightedProductIndex = (highlightedProductIndex + 1) % items.length;
                updateProductHighlight(items);
            }
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (items.length > 0) {
                highlightedProductIndex = (highlightedProductIndex - 1 + items.length) % items.length;
                updateProductHighlight(items);
            }
        } else if (e.key === 'Enter') {
            e.preventDefault();
            clearTimeout(searchTimeout);

            if (searchResults.style.display === 'block' && highlightedProductIndex >= 0 && items[highlightedProductIndex]) {
                items[highlightedProductIndex].click();
            } else if (searchResults.style.display === 'block' && items.length > 0) {
                items[0].click();
            } else {
                executeProductSearch(this.value, true);
            }
        } else if (e.key === 'Escape') {
            searchResults.style.display = 'none';
            highlightedProductIndex = -1;
        }
    });

    function updateProductHighlight(items) {
        items.forEach((it, idx) => {
            if (idx === highlightedProductIndex) {
                it.classList.add('active', 'bg-primary-subtle', 'text-primary');
                it.scrollIntoView({ block: 'nearest' });
            } else {
                it.classList.remove('active', 'bg-primary-subtle', 'text-primary');
            }
        });
    }

    // Close search results on click outside
    document.addEventListener('click', function (e) {
        if (!searchInput.contains(e.target) && !searchResults.contains(e.target)) {
            searchResults.style.display = 'none';
        }
    });

    // ── Banner de Error de Stock Corporativo (Cero window.alert) ──
    function showStockErrorBanner(productName, availableStock, requestedQuantity, targetForm) {
        const errorContainer = document.getElementById('stock-error-container');
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

        if (errorContainer) {
            errorContainer.appendChild(banner);
            banner.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            return;
        }

        const form = targetForm || document.getElementById('saleForm') || document.querySelector('form');
        if (form) {
            form.prepend(banner);
            banner.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    // ── Cart management ──
    function addToCart(product) {
        const prodId = parseInt(product.id || product.product_id);
        const isStandard = product.is_standard !== undefined 
            ? Boolean(product.is_standard) 
            : (product.product_type ? (product.product_type !== 'dish' && product.product_type !== 'menu') : true);
        const prodStock = parseFloat(product.available_stock !== undefined ? product.available_stock : (product.stock || 0));
        const prodName = product.name || 'Producto';
        const unitPrice = parseFloat(product.sale_price !== undefined ? product.sale_price : (product.price !== undefined ? product.price : product.unit_price)) || 0;

        const existing = cart.find(c => c.product_id === prodId);
        if (existing) {
            if (existing.is_standard && (existing.quantity + 1 > existing.stock)) {
                showStockErrorBanner(existing.name, existing.stock, existing.quantity + 1);
                return;
            }
            existing.quantity += 1;
        } else {
            if (isStandard && prodStock <= 0) {
                showStockErrorBanner(prodName, prodStock, 1);
                return;
            }
            cart.push({
                product_id: prodId,
                name: prodName,
                sku: product.sku || '',
                stock: prodStock,
                is_standard: isStandard,
                quantity: 1,
                unit_price: unitPrice,
            });
        }
        searchInput.value = '';
        searchResults.style.display = 'none';
        searchResults.innerHTML = '';
        highlightedProductIndex = -1;
        renderCart();
    }
    window.addProductToSale = addToCart;

    // ── Selector Dropdown POS sincronizado ──
    const productDropdownPos = document.getElementById('product_dropdown_pos') || document.getElementById('productSelect');
    if (productDropdownPos) {
        productDropdownPos.addEventListener('change', function () {
            const selectedOpt = this.options[this.selectedIndex];
            if (!selectedOpt || !selectedOpt.value) return;

            const productData = {
                id: parseInt(selectedOpt.value),
                name: selectedOpt.dataset.name,
                sku: selectedOpt.dataset.sku,
                sale_price: parseFloat(selectedOpt.dataset.price),
                price: parseFloat(selectedOpt.dataset.price),
                stock: parseFloat(selectedOpt.dataset.stock),
                available_stock: parseFloat(selectedOpt.dataset.stock),
                is_standard: selectedOpt.dataset.isStandard === '1',
            };

            addToCart(productData);

            // Resetear el selector a la opción inicial tras agregarlo
            this.selectedIndex = 0;
            this.value = '';

            // Transferir el foco al input de cantidad del ítem en la tabla del carrito
            const lastQtyInput = cartBody.querySelector('tr:last-child .qty-input');
            if (lastQtyInput) {
                lastQtyInput.focus();
                lastQtyInput.select();
            }
        });
    }

    function removeFromCart(index) {
        cart.splice(index, 1);
        renderCart();
    }

    function renderCart() {
        cartBody.innerHTML = '';

        if (cart.length === 0) {
            cartBody.appendChild(emptyCartRow);
            btnSubmit.disabled = true;
            updateTotals();
            return;
        }

        btnSubmit.disabled = false;

        cart.forEach((item, index) => {
            const lineSubtotal = item.quantity * item.unit_price;
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    <span class="fw-semibold">${escapeHtml(item.name)}</span>
                    <small class="text-muted d-block font-monospace">${escapeHtml(item.sku || '')}</small>
                    <input type="hidden" name="items[${index}][product_id]" value="${item.product_id}">
                    <input type="hidden" name="items[${index}][unit_price]" value="${item.unit_price}">
                </td>
                <td class="text-center">
                    ${item.is_standard
                        ? `<span class="badge ${item.stock <= 0 ? 'bg-danger' : item.stock <= 5 ? 'bg-warning text-dark' : 'bg-light text-dark border'} rounded-pill">${item.stock}</span>`
                        : `<span class="badge bg-light text-secondary border rounded-pill">Carta</span>`
                    }
                </td>
                <td class="text-center">
                    <div class="input-group input-group-sm justify-content-center mx-auto" style="max-width: 125px;">
                        <button type="button" class="btn btn-outline-secondary btn-qty-minus px-2" data-index="${index}" title="Disminuir cantidad" ${item.quantity <= 1 ? 'disabled' : ''}>
                            <i class="bi bi-dash-lg"></i>
                        </button>
                        <input type="number" name="items[${index}][quantity]" value="${item.quantity}"
                               class="form-control form-control-sm text-center qty-input px-1 fw-bold font-monospace"
                               min="1" ${item.is_standard ? `max="${item.stock}"` : ''} data-index="${index}" style="width:45px;">
                        <button type="button" class="btn btn-outline-secondary btn-qty-plus px-2" data-index="${index}" title="Aumentar cantidad" ${(item.is_standard && item.quantity >= item.stock) ? 'disabled' : ''}>
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    </div>
                </td>
                <td class="text-end font-monospace">$${formatNumber(item.unit_price)}</td>
                <td class="text-end fw-semibold font-monospace">$${formatNumber(lineSubtotal)}</td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-outline-danger rounded-circle remove-btn" data-index="${index}" title="Quitar producto">
                        <i class="bi bi-trash3"></i>
                    </button>
                </td>
            `;
            cartBody.appendChild(tr);
        });

        // Bind minus buttons
        document.querySelectorAll('.btn-qty-minus').forEach(btn => {
            btn.addEventListener('click', function () {
                const idx = parseInt(this.dataset.index);
                if (cart[idx].quantity > 1) {
                    cart[idx].quantity -= 1;
                    renderCart();
                }
            });
        });

        // Bind plus buttons
        document.querySelectorAll('.btn-qty-plus').forEach(btn => {
            btn.addEventListener('click', function () {
                const idx = parseInt(this.dataset.index);
                const item = cart[idx];
                if (item.is_standard && (item.quantity + 1 > item.stock)) {
                    showStockErrorBanner(item.name, item.stock, item.quantity + 1);
                    return;
                }
                item.quantity += 1;
                renderCart();
            });
        });

        // Bind manual quantity change
        document.querySelectorAll('.qty-input').forEach(input => {
            input.addEventListener('change', function () {
                const idx = parseInt(this.dataset.index);
                const item = cart[idx];
                let val = parseInt(this.value) || 1;
                if (val < 1) val = 1;
                if (item.is_standard && val > item.stock) {
                    showStockErrorBanner(item.name, item.stock, val);
                    val = Math.max(1, Math.floor(item.stock));
                }
                this.value = val;
                item.quantity = val;
                renderCart();
            });
        });

        // Bind remove buttons
        document.querySelectorAll('.remove-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                removeFromCart(parseInt(this.dataset.index));
            });
        });

        updateTotals();
    }

    // ── Totals calculation (percentage-based discount) ──
    function updateTotals() {
        let subtotal = 0;
        cart.forEach(item => {
            subtotal += item.quantity * item.unit_price;
        });

        const pct = parseFloat(discountInput.value) || 0;
        const discountMoney = Math.round(subtotal * (pct / 100));
        const total = Math.max(subtotal - discountMoney, 0);

        document.getElementById('displaySubtotal').textContent = '$' + formatNumber(subtotal);
        document.getElementById('displayDiscount').textContent = '-$' + formatNumber(discountMoney);
        document.getElementById('displayTotal').textContent = '$' + formatNumber(total);

        // Show/hide peso equivalent feedback
        if (pct > 0 && subtotal > 0) {
            discountMoneyPreview.textContent = '$' + formatNumber(discountMoney);
            discountFeedback.style.display = 'block';
        } else {
            discountFeedback.style.display = 'none';
        }

        // Sincronizar pago si hay un único método de pago
        syncSinglePayment(total);
        renderPaymentLines();
        updatePaymentsFeedback();
    }

    // Control ergonómico y reactivo del campo Descuento (%)
    discountInput.addEventListener('focus', function() {
        this.select();
    });

    discountInput.addEventListener('input', function() {
        let val = parseInt(this.value, 10);
        if (isNaN(val) || val < 0) val = 0;
        if (val > 100) val = 100;
        this.value = val;
        updateTotals();
    });

    discountInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.keyCode === 13) {
            e.preventDefault();
            this.blur();
            updateTotals();
        }
    });

    const btnDiscountMinus = document.getElementById('btnDiscountMinus');
    if (btnDiscountMinus) {
        btnDiscountMinus.addEventListener('click', function() {
            let val = parseInt(discountInput.value, 10) || 0;
            discountInput.value = Math.max(0, val - 1);
            updateTotals();
        });
    }

    const btnDiscountPlus = document.getElementById('btnDiscountPlus');
    if (btnDiscountPlus) {
        btnDiscountPlus.addEventListener('click', function() {
            let val = parseInt(discountInput.value, 10) || 0;
            discountInput.value = Math.min(100, val + 1);
            updateTotals();
        });
    }

    // ── Estado y Manejo de Pagos Mixtos y Vueltos ──
    let payments = [
        { method: 'cash', amount: 0, reference: '', cash_received: null, change_given: 0 }
    ];
    let userModifiedPayments = false;

    function getCurrentTotal() {
        let subtotal = 0;
        cart.forEach(item => {
            subtotal += item.quantity * item.unit_price;
        });
        const pct = parseFloat(discountInput.value) || 0;
        const discountMoney = Math.round(subtotal * (pct / 100));
        return Math.max(subtotal - discountMoney, 0);
    }

    function syncSinglePayment(total) {
        if (payments.length === 1) {
            payments[0].amount = total;
            if (payments[0].method === 'cash') {
                if (payments[0].cash_received !== null && payments[0].cash_received !== undefined && payments[0].cash_received !== '') {
                    payments[0].change_given = Math.max(0, payments[0].cash_received - total);
                } else {
                    payments[0].change_given = 0;
                }
            }
        }
    }

    function getSmartShortcuts(amount) {
        const list = [{ label: 'Exacto', val: 'exact' }];
        if (amount <= 0) {
            [10000, 20000, 50000, 100000].forEach(v => list.push({ label: '$' + formatNumber(v), val: v }));
            return list;
        }
        const candidates = new Set();
        const next10k = Math.ceil(amount / 10000) * 10000;
        if (next10k > amount) candidates.add(next10k);
        const next20k = Math.ceil(amount / 20000) * 20000;
        if (next20k > amount) candidates.add(next20k);
        const next50k = Math.ceil(amount / 50000) * 50000;
        if (next50k > amount) candidates.add(next50k);
        const next100k = Math.ceil(amount / 100000) * 100000;
        if (next100k > amount) candidates.add(next100k);

        const sorted = Array.from(candidates).sort((a, b) => a - b).slice(0, 3);
        sorted.forEach(val => {
            list.push({ label: '$' + formatNumber(val), val: val });
        });
        return list;
    }

    function renderPaymentLines() {
        const container = document.getElementById('payment_lines_container');
        if (!container) return;
        container.innerHTML = '';
        const total = getCurrentTotal();
        const hasCashSelected = payments.some(item => item.method === 'cash');

        payments.forEach((p, idx) => {
            const card = document.createElement('div');
            card.className = 'card p-2 bg-light border-0 rounded-3 shadow-none mb-2';

            // Fila principal: Selector de método, input de monto y botón de eliminar
            const row = document.createElement('div');
            row.className = 'row g-2 align-items-center';

            // Columna selector de método
            const colMethod = document.createElement('div');
            colMethod.className = 'col-sm-5';
            const select = document.createElement('select');
            select.className = 'form-select form-select-sm';

            const options = [
                { val: 'cash', text: '💵 Efectivo' },
                { val: 'transfer', text: '📲 Transferencia / Nequi' },
                { val: 'card', text: '💳 Tarjeta' },
                { val: 'other', text: '✨ Otro' }
            ];

            options.forEach(opt => {
                const optEl = document.createElement('option');
                optEl.value = opt.val;
                optEl.textContent = opt.text;
                if (p.method === opt.val) {
                    optEl.selected = true;
                } else if (opt.val === 'cash' && hasCashSelected) {
                    optEl.disabled = true;
                }
                select.appendChild(optEl);
            });

            select.addEventListener('change', function() {
                userModifiedPayments = true;
                p.method = this.value;
                if (p.method === 'cash') {
                    p.cash_received = null;
                    p.change_given = 0;
                } else {
                    p.cash_received = null;
                    p.change_given = null;
                }
                renderPaymentLines();
                updatePaymentsFeedback();
            });
            colMethod.appendChild(select);
            row.appendChild(colMethod);

            // Columna de monto
            const colAmount = document.createElement('div');
            colAmount.className = 'col-sm-5';
            const inputGroup = document.createElement('div');
            inputGroup.className = 'input-group input-group-sm';
            const spanPrefix = document.createElement('span');
            spanPrefix.className = 'input-group-text';
            spanPrefix.textContent = '$';
            const inputAmount = document.createElement('input');
            inputAmount.type = 'number';
            inputAmount.className = 'form-control text-end fw-semibold font-monospace';
            inputAmount.min = '0';
            inputAmount.step = 'any';
            inputAmount.value = p.amount > 0 ? p.amount : (payments.length === 1 && total > 0 ? total : '');
            inputAmount.placeholder = '0';

            inputAmount.addEventListener('input', function() {
                userModifiedPayments = true;
                p.amount = parseFloat(this.value) || 0;
                if (p.method === 'cash') {
                    if (p.cash_received !== undefined && p.cash_received !== null && p.cash_received !== '') {
                        p.change_given = Math.max(0, p.cash_received - p.amount);
                    } else {
                        p.change_given = 0;
                    }
                    if (typeof updateChangeDisplay === 'function') {
                        updateChangeDisplay();
                    }
                }
                updatePaymentsFeedback();
            });
            inputGroup.appendChild(spanPrefix);
            inputGroup.appendChild(inputAmount);
            colAmount.appendChild(inputGroup);

            // Botón rápido para auto-ajustar al saldo restante cuando hay más de 1 método
            if (payments.length > 1) {
                const otherSum = payments.reduce((acc, curr, i) => i !== idx ? acc + (curr.amount || 0) : acc, 0);
                const remainingForThis = Math.max(0, Math.round((total - otherSum) * 100) / 100);
                if (remainingForThis !== p.amount) {
                    const btnAdjust = document.createElement('button');
                    btnAdjust.type = 'button';
                    btnAdjust.className = 'btn btn-link btn-xs p-0 text-decoration-none text-primary mt-1 d-block text-end w-100';
                    btnAdjust.style.fontSize = '0.72rem';
                    btnAdjust.innerHTML = `<i class="bi bi-magic me-1"></i>Ajustar al restante ($${formatNumber(remainingForThis)})`;
                    btnAdjust.title = 'Ajustar este método para completar el total de la venta';
                    btnAdjust.addEventListener('click', function() {
                        p.amount = remainingForThis;
                        inputAmount.value = p.amount;
                        if (p.method === 'cash') {
                            p.cash_received = null;
                            p.change_given = 0;
                        }
                        renderPaymentLines();
                        updatePaymentsFeedback();
                    });
                    colAmount.appendChild(btnAdjust);
                }
            }

            row.appendChild(colAmount);

            // Columna de eliminar línea (si hay más de 1)
            const colDelete = document.createElement('div');
            colDelete.className = 'col-sm-2 text-end';
            if (payments.length > 1) {
                const btnDel = document.createElement('button');
                btnDel.type = 'button';
                btnDel.className = 'btn btn-sm btn-outline-danger border-0';
                btnDel.title = 'Eliminar método';
                btnDel.innerHTML = '<i class="bi bi-trash3"></i>';
                btnDel.addEventListener('click', function() {
                    payments.splice(idx, 1);
                    userModifiedPayments = true;
                    renderPaymentLines();
                    updatePaymentsFeedback();
                });
                colDelete.appendChild(btnDel);
            }
            row.appendChild(colDelete);
            card.appendChild(row);

            // Declarar updateChangeDisplay en alcance común para la tarjeta de efectivo
            let updateChangeDisplay = null;

            // Sección específica para Efectivo (Paga con, Atajos y Vueltos)
            if (p.method === 'cash') {
                const cashSection = document.createElement('div');
                cashSection.className = 'mt-2 pt-2 border-top';

                const receivedRow = document.createElement('div');
                receivedRow.className = 'row g-2 align-items-center mb-1';
                const labelCol = document.createElement('div');
                labelCol.className = 'col-auto';
                labelCol.innerHTML = '<span class="small text-muted fw-semibold">Paga con / Recibido:</span>';
                receivedRow.appendChild(labelCol);

                const recInputCol = document.createElement('div');
                recInputCol.className = 'col';
                const recGroup = document.createElement('div');
                recGroup.className = 'input-group input-group-sm';
                const recPrefix = document.createElement('span');
                recPrefix.className = 'input-group-text';
                recPrefix.textContent = '$';
                const recInput = document.createElement('input');
                recInput.type = 'number';
                recInput.className = 'form-control text-end font-monospace fw-semibold';
                recInput.min = '0';
                recInput.step = 'any';
                recInput.value = (p.cash_received !== undefined && p.cash_received !== null) ? p.cash_received : '';
                recInput.placeholder = p.amount > 0 ? ('$' + formatNumber(p.amount) + ' (o atajo)') : '0';

                recGroup.appendChild(recPrefix);
                recGroup.appendChild(recInput);
                recInputCol.appendChild(recGroup);
                receivedRow.appendChild(recInputCol);
                cashSection.appendChild(receivedRow);

                // Botones de atajos rápidos inteligentes basados en el monto a pagar
                const shortcutsRow = document.createElement('div');
                shortcutsRow.className = 'd-flex flex-wrap gap-1 mb-2';

                function renderShortcuts() {
                    shortcutsRow.innerHTML = '';
                    const shortcuts = getSmartShortcuts(p.amount);
                    shortcuts.forEach(sc => {
                        const btnSc = document.createElement('button');
                        btnSc.type = 'button';
                        btnSc.className = 'btn btn-outline-secondary btn-sm py-0 px-2 rounded-pill';
                        btnSc.style.fontSize = '0.75rem';
                        btnSc.textContent = sc.label;
                        btnSc.addEventListener('click', function() {
                            const targetVal = sc.val === 'exact' ? p.amount : sc.val;
                            recInput.value = targetVal;
                            p.cash_received = targetVal;
                            p.change_given = Math.max(0, targetVal - p.amount);
                            updateChangeDisplay();
                            updatePaymentsFeedback();
                        });
                        shortcutsRow.appendChild(btnSc);
                    });
                }
                renderShortcuts();
                cashSection.appendChild(shortcutsRow);

                // Fila de Vuelto / Cambio o estado de entrega de efectivo
                const changeRow = document.createElement('div');
                changeRow.className = 'd-flex justify-content-between align-items-center mt-1 flex-wrap gap-1';

                updateChangeDisplay = function() {
                    changeRow.innerHTML = '';
                    if (p.amount <= 0) {
                        return;
                    }

                    if (p.cash_received === null || p.cash_received === undefined) {
                        const note = document.createElement('span');
                        note.className = 'small text-muted font-monospace';
                        note.innerHTML = '<i class="bi bi-info-circle me-1"></i>Exacto o ingrese con cuánto paga el cliente';
                        changeRow.appendChild(note);
                        return;
                    }

                    const diff = p.cash_received - p.amount;

                    if (diff >= 0) {
                        const label = document.createElement('span');
                        label.className = 'small text-muted';
                        label.textContent = 'Vuelto / Cambio:';
                        changeRow.appendChild(label);

                        const badge = document.createElement('span');
                        badge.className = 'badge bg-success font-monospace cash-change-badge';
                        badge.textContent = 'Vuelto: $' + formatNumber(diff);
                        changeRow.appendChild(badge);
                    } else {
                        const shortAmt = Math.abs(diff);
                        const alertWrapper = document.createElement('div');
                        alertWrapper.className = 'd-flex align-items-center justify-content-between w-100 flex-wrap gap-1';

                        const label = document.createElement('span');
                        label.className = 'badge bg-danger-subtle text-danger border border-danger-subtle font-monospace text-wrap text-start py-1';
                        label.innerHTML = `<i class="bi bi-exclamation-triangle-fill me-1"></i>Efectivo menor al asignado (faltan $${formatNumber(shortAmt)})`;
                        alertWrapper.appendChild(label);

                        if (p.cash_received > 0) {
                            const btnSetAmount = document.createElement('button');
                            btnSetAmount.type = 'button';
                            btnSetAmount.className = 'btn btn-link btn-xs p-0 text-decoration-none text-primary fw-semibold';
                            btnSetAmount.style.fontSize = '0.75rem';
                            btnSetAmount.innerHTML = `<i class="bi bi-arrow-down-left me-1"></i>Cobrar solo $${formatNumber(p.cash_received)} en efectivo`;
                            btnSetAmount.title = `Fijar $${formatNumber(p.cash_received)} como el monto a cobrar en efectivo`;
                            btnSetAmount.addEventListener('click', function() {
                                p.amount = p.cash_received;
                                p.change_given = 0;
                                renderPaymentLines();
                                updatePaymentsFeedback();
                            });
                            alertWrapper.appendChild(btnSetAmount);
                        }

                        changeRow.appendChild(alertWrapper);
                    }
                };
                updateChangeDisplay();

                recInput.addEventListener('input', function() {
                    const rawVal = this.value.trim();
                    if (rawVal === '') {
                        p.cash_received = null;
                        p.change_given = 0;
                    } else {
                        p.cash_received = parseFloat(rawVal) || 0;
                        p.change_given = Math.max(0, p.cash_received - p.amount);
                    }
                    updateChangeDisplay();
                    updatePaymentsFeedback();
                });

                cashSection.appendChild(changeRow);
                card.appendChild(cashSection);
            } else {
                // Input de referencia para transferencias/tarjetas/otros
                const refSection = document.createElement('div');
                refSection.className = 'mt-2 pt-2 border-top';
                const refInput = document.createElement('input');
                refInput.type = 'text';
                refInput.className = 'form-control form-control-sm';
                refInput.maxLength = 60;
                refInput.placeholder = 'Referencia / # aprobación / Nequi (opcional)';
                refInput.value = p.reference || '';
                refInput.addEventListener('input', function() {
                    p.reference = this.value;
                    syncHiddenPaymentInputs();
                });
                refSection.appendChild(refInput);
                card.appendChild(refSection);
            }

            container.appendChild(card);
        });

        // Deshabilitar botón de agregar si ya hay 5 líneas
        const btnAdd = document.getElementById('btnAddPaymentLine');
        if (btnAdd) {
            btnAdd.disabled = payments.length >= 5;
        }
    }

    function updatePaymentsFeedback() {
        const feedback = document.getElementById('payment_balance_feedback');
        if (!feedback) return;
        const total = getCurrentTotal();
        let sum = 0;
        let cashValid = true;

        payments.forEach(p => {
            sum += (p.amount || 0);
            if (p.method === 'cash') {
                const rec = p.cash_received !== undefined && p.cash_received !== null ? p.cash_received : p.amount;
                if (rec < p.amount) {
                    cashValid = false;
                }
            }
        });

        const diff = Math.round(total * 100) - Math.round(sum * 100);

        if (total === 0) {
            feedback.className = 'small p-2 rounded-3 text-center fw-semibold bg-light text-muted';
            feedback.textContent = 'Venta con 100% de descuento ($0)';
        } else if (diff > 0) {
            const missing = diff / 100;
            feedback.className = 'small p-2 rounded-3 fw-semibold bg-danger-subtle text-danger border border-danger-subtle d-flex align-items-center justify-content-between flex-wrap gap-2';
            feedback.innerHTML = `
                <span><i class="bi bi-exclamation-triangle-fill me-1"></i>Falta asignar a los métodos: <strong>$${formatNumber(missing)}</strong></span>
            `;
            if (payments.length > 0) {
                const btnBalance = document.createElement('button');
                btnBalance.type = 'button';
                btnBalance.className = 'btn btn-outline-danger btn-sm py-0 px-2 rounded-pill';
                btnBalance.style.fontSize = '0.75rem';
                btnBalance.textContent = '+ Asignar al último método';
                btnBalance.addEventListener('click', function() {
                    const lastP = payments[payments.length - 1];
                    lastP.amount = Math.round(((lastP.amount || 0) + missing) * 100) / 100;
                    if (lastP.method === 'cash') {
                        lastP.cash_received = Math.max(lastP.cash_received || 0, lastP.amount);
                        lastP.change_given = Math.max(0, lastP.cash_received - lastP.amount);
                    }
                    renderPaymentLines();
                    updatePaymentsFeedback();
                });
                feedback.appendChild(btnBalance);
            }
        } else if (diff < 0) {
            const excess = Math.abs(diff) / 100;
            feedback.className = 'small p-2 rounded-3 fw-semibold bg-warning-subtle text-warning-emphasis border border-warning-subtle d-flex align-items-center justify-content-between flex-wrap gap-2';
            feedback.innerHTML = `
                <span><i class="bi bi-exclamation-circle-fill me-1"></i>Suma de métodos ($${formatNumber(sum)}) supera el total ($${formatNumber(total)}) por <strong>$${formatNumber(excess)}</strong></span>
            `;
            if (payments.length > 0) {
                const btnBalance = document.createElement('button');
                btnBalance.type = 'button';
                btnBalance.className = 'btn btn-outline-warning btn-sm py-0 px-2 rounded-pill text-dark';
                btnBalance.style.fontSize = '0.75rem';
                btnBalance.textContent = 'Ajustar al total';
                btnBalance.addEventListener('click', function() {
                    let remainingExcess = excess;
                    for (let i = payments.length - 1; i >= 0; i--) {
                        if (payments[i].amount >= remainingExcess) {
                            payments[i].amount = Math.round((payments[i].amount - remainingExcess) * 100) / 100;
                            if (payments[i].method === 'cash') {
                                payments[i].change_given = Math.max(0, (payments[i].cash_received || payments[i].amount) - payments[i].amount);
                            }
                            remainingExcess = 0;
                            break;
                        }
                    }
                    renderPaymentLines();
                    updatePaymentsFeedback();
                });
                feedback.appendChild(btnBalance);
            }
        } else {
            if (cashValid) {
                feedback.className = 'small p-2 rounded-3 text-center fw-semibold bg-success-subtle text-success border border-success-subtle';
                feedback.innerHTML = `<i class="bi bi-check-circle-fill me-1"></i>Monto exacto cubierto ($${formatNumber(total)})`;
            } else {
                feedback.className = 'small p-2 rounded-3 text-center fw-semibold bg-danger-subtle text-danger border border-danger-subtle';
                feedback.innerHTML = `<i class="bi bi-exclamation-triangle-fill me-1"></i>Monto total cubierto, pero el efectivo entregado es menor al asignado a esa línea`;
            }
        }

        // Estado del botón submit
        const isCartValid = cart.length > 0;
        const isAmountCovered = total === 0 || diff === 0;
        const allAmountsPositive = total === 0 || payments.every(p => p.amount > 0);

        if (isCartValid && isAmountCovered && allAmountsPositive && cashValid) {
            btnSubmit.disabled = false;
        } else {
            btnSubmit.disabled = true;
        }

        syncHiddenPaymentInputs();
    }

    function syncHiddenPaymentInputs() {
        const hiddenContainer = document.getElementById('hidden_payments_inputs');
        if (!hiddenContainer) return;
        hiddenContainer.innerHTML = '';

        const hiddenPaymentMethod = document.getElementById('hidden_payment_method');
        if (hiddenPaymentMethod) {
            hiddenPaymentMethod.value = payments.length === 1 ? payments[0].method : 'mixed';
        }

        payments.forEach((p, idx) => {
            const isCash = p.method === 'cash';
            const hasExplicitRec = p.cash_received !== undefined && p.cash_received !== null && p.cash_received !== '';
            const cashRec = isCash ? (hasExplicitRec ? p.cash_received : p.amount) : '';
            const change = isCash ? Math.max(0, (hasExplicitRec ? p.cash_received : p.amount) - p.amount) : '';

            hiddenContainer.innerHTML += `
                <input type="hidden" name="payments[${idx}][method]" value="${escapeHtml(p.method)}">
                <input type="hidden" name="payments[${idx}][amount]" value="${p.amount}">
                <input type="hidden" name="payments[${idx}][reference]" value="${escapeHtml(p.reference || '')}">
                <input type="hidden" name="payments[${idx}][cash_received]" value="${cashRec}">
                <input type="hidden" name="payments[${idx}][change_given]" value="${change}">
            `;
        });
    }

    // Botón agregar método de pago
    const btnAddLine = document.getElementById('btnAddPaymentLine');
    if (btnAddLine) {
        btnAddLine.addEventListener('click', function() {
            if (payments.length >= 5) return;
            userModifiedPayments = true;

            const hasCash = payments.some(p => p.method === 'cash');
            const newMethod = hasCash ? 'transfer' : 'cash';

            let sum = 0;
            payments.forEach(p => sum += (p.amount || 0));
            const total = getCurrentTotal();
            const remaining = Math.max(0, total - sum);

            payments.push({
                method: newMethod,
                amount: remaining,
                reference: '',
                cash_received: null,
                change_given: 0
            });

            renderPaymentLines();
            updatePaymentsFeedback();
        });
    }

    const saleForm = document.getElementById('saleForm');
    if (saleForm) {
        saleForm.addEventListener('submit', function() {
            syncHiddenPaymentInputs();
        });
    }

    // ── Búsqueda y Selección de Cliente (POS) ──
    const customerIdInput = document.getElementById('customer_id');
    const customerChipName = document.getElementById('customer_chip_name');
    const customerChipDoc = document.getElementById('customer_chip_doc');
    const customerChipIcon = document.getElementById('customer_chip_icon');
    const btnRemoveCustomer = document.getElementById('btn_remove_customer');
    const customerSearchInput = document.getElementById('customer_search');
    const customerSearchResults = document.getElementById('customer_search_results');

    const defaultCustomerName = @json($defaultCustomerName ?? config('sales.default_customer_name'));
    const defaultCustomerDoc = @json($defaultCustomerDocument ?? config('sales.default_customer_document'));

    let highlightedCustomerIndex = -1;

    function selectCustomer(id, name, doc) {
        if (id) {
            customerIdInput.value = id;
            customerChipName.textContent = name;
            customerChipDoc.textContent = doc ? `Doc: ${doc}` : '';
            customerChipIcon.className = 'bi bi-person-check-fill text-success fs-5 me-2 flex-shrink-0';
            btnRemoveCustomer.classList.remove('d-none');
        } else {
            customerIdInput.value = '';
            customerChipName.textContent = defaultCustomerName;
            customerChipDoc.textContent = defaultCustomerDoc;
            customerChipIcon.className = 'bi bi-person text-secondary fs-5 me-2 flex-shrink-0';
            btnRemoveCustomer.classList.add('d-none');
        }
        customerSearchInput.value = '';
        customerSearchResults.classList.add('d-none');
        customerSearchResults.innerHTML = '';
        highlightedCustomerIndex = -1;

        // Pasar el foco al siguiente campo lógico (búsqueda de productos)
        const productSearch = document.getElementById('productSearch');
        if (productSearch) {
            productSearch.focus();
        }
    }

    btnRemoveCustomer.addEventListener('click', function() {
        selectCustomer('', '', '');
    });

    let customerSearchTimeout = null;
    let activeCustomerAbort = null;
    const customerSearchCache = new Map();

    function updateCustomerHighlight(items) {
        items.forEach((item, idx) => {
            if (idx === highlightedCustomerIndex) {
                item.classList.add('active', 'bg-primary', 'text-white');
                item.querySelectorAll('.text-dark, .text-muted').forEach(el => {
                    el.classList.add('text-white');
                    el.classList.remove('text-dark', 'text-muted');
                });
                item.scrollIntoView({ block: 'nearest' });
            } else {
                item.classList.remove('active', 'bg-primary', 'text-white');
                item.querySelectorAll('.text-white').forEach(el => {
                    el.classList.remove('text-white');
                });
            }
        });
    }

    customerSearchInput.addEventListener('keydown', function(e) {
        const items = customerSearchResults.querySelectorAll('button.customer-result-item');
        const isVisible = !customerSearchResults.classList.contains('d-none') && items.length > 0;

        if (e.key === 'Enter' || e.keyCode === 13) {
            e.preventDefault(); // Evitar incondicionalmente el submit accidental del formulario
            if (isVisible) {
                const targetBtn = (highlightedCustomerIndex >= 0 && items[highlightedCustomerIndex])
                    ? items[highlightedCustomerIndex]
                    : items[0];
                if (targetBtn) {
                    targetBtn.click();
                }
            } else {
                const productSearch = document.getElementById('productSearch');
                if (productSearch) {
                    productSearch.focus();
                }
            }
        } else if (e.key === 'ArrowDown' || e.keyCode === 40) {
            if (isVisible) {
                e.preventDefault();
                highlightedCustomerIndex = (highlightedCustomerIndex + 1) % items.length;
                updateCustomerHighlight(items);
            }
        } else if (e.key === 'ArrowUp' || e.keyCode === 38) {
            if (isVisible) {
                e.preventDefault();
                highlightedCustomerIndex = (highlightedCustomerIndex - 1 + items.length) % items.length;
                updateCustomerHighlight(items);
            }
        } else if (e.key === 'Escape' || e.keyCode === 27) {
            customerSearchResults.classList.add('d-none');
            highlightedCustomerIndex = -1;
        }
    });

    customerSearchInput.addEventListener('input', function() {
        const query = this.value.trim();
        clearTimeout(customerSearchTimeout);
        highlightedCustomerIndex = -1;

        if (query.length < 3) {
            if (activeCustomerAbort) {
                activeCustomerAbort.abort();
                activeCustomerAbort = null;
            }
            customerSearchResults.classList.add('d-none');
            customerSearchResults.innerHTML = '';
            return;
        }

        const cacheKey = query.toLowerCase();
        if (customerSearchCache.has(cacheKey)) {
            renderCustomerSearchResults(customerSearchCache.get(cacheKey));
            return;
        }

        customerSearchTimeout = setTimeout(() => {
            if (activeCustomerAbort) {
                activeCustomerAbort.abort();
            }
            activeCustomerAbort = new AbortController();

            fetch(`{{ route('customers.search') }}?q=${encodeURIComponent(query)}`, {
                signal: activeCustomerAbort.signal,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                activeCustomerAbort = null;
                customerSearchCache.set(cacheKey, data);
                renderCustomerSearchResults(data);
            })
            .catch(err => {
                if (err.name !== 'AbortError') {
                    activeCustomerAbort = null;
                    customerSearchResults.classList.add('d-none');
                }
            });
        }, 180);
    });

    function renderCustomerSearchResults(data) {
        customerSearchResults.innerHTML = '';
        highlightedCustomerIndex = -1;

        if (!Array.isArray(data) || data.length === 0) {
            const emptyItem = document.createElement('div');
            emptyItem.className = 'list-group-item text-muted small py-2';
            emptyItem.textContent = 'No se encontraron clientes con ese documento o nombre';
            customerSearchResults.appendChild(emptyItem);
        } else {
            data.forEach((cust, idx) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center py-2 customer-result-item';
                btn.dataset.index = idx;

                const infoDiv = document.createElement('div');
                infoDiv.className = 'text-truncate me-2 text-start';

                const nameSpan = document.createElement('span');
                nameSpan.className = 'fw-semibold d-block text-truncate text-dark';
                nameSpan.textContent = cust.name;

                const docSpan = document.createElement('small');
                docSpan.className = 'text-muted font-monospace';
                docSpan.textContent = cust.document ? `Doc: ${cust.document}` : '';

                infoDiv.appendChild(nameSpan);
                infoDiv.appendChild(docSpan);

                btn.appendChild(infoDiv);

                if (cust.phone) {
                    const phoneSpan = document.createElement('small');
                    phoneSpan.className = 'badge bg-light text-secondary border';
                    phoneSpan.textContent = cust.phone;
                    btn.appendChild(phoneSpan);
                }

                btn.addEventListener('mouseenter', function() {
                    highlightedCustomerIndex = idx;
                    const items = customerSearchResults.querySelectorAll('button.customer-result-item');
                    updateCustomerHighlight(items);
                });

                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    selectCustomer(cust.id, cust.name, cust.document);
                });

                customerSearchResults.appendChild(btn);
            });
        }
        customerSearchResults.classList.remove('d-none');
    }

    // Cerrar resultados al hacer click afuera
    document.addEventListener('click', function(e) {
        if (!customerSearchInput.contains(e.target) && !customerSearchResults.contains(e.target)) {
            customerSearchResults.classList.add('d-none');
            highlightedCustomerIndex = -1;
        }
    });

    // Modal de creación rápida de cliente
    const btnSaveQuickCustomer = document.getElementById('btn_save_quick_customer');
    const quickCustomerErrors = document.getElementById('quickCustomerErrors');
    const quickCustomerModalEl = document.getElementById('quickCustomerModal');

    btnSaveQuickCustomer.addEventListener('click', function() {
        quickCustomerErrors.classList.add('d-none');
        quickCustomerErrors.innerHTML = '';

        const docVal = document.getElementById('quick_customer_document').value.trim();
        const nameVal = document.getElementById('quick_customer_name').value.trim();
        const phoneVal = document.getElementById('quick_customer_phone').value.trim();
        const emailVal = document.getElementById('quick_customer_email').value.trim();

        const csrfToken = document.querySelector('input[name="_token"]')?.value;

        btnSaveQuickCustomer.disabled = true;

        fetch('{{ route("customers.store") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                document: docVal,
                name: nameVal,
                phone: phoneVal,
                email: emailVal,
                is_active: true
            })
        })
        .then(async response => {
            const data = await response.json();
            btnSaveQuickCustomer.disabled = false;

            if (!response.ok) {
                const ul = document.createElement('ul');
                ul.className = 'mb-0 ps-3';

                if (data.errors) {
                    Object.values(data.errors).forEach(errArr => {
                        errArr.forEach(errText => {
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

                quickCustomerErrors.appendChild(ul);
                quickCustomerErrors.classList.remove('d-none');
                return;
            }

            // Éxito: seleccionar el cliente creado
            selectCustomer(data.id, data.name, data.document);

            // Limpiar inputs del modal
            document.getElementById('quick_customer_document').value = '';
            document.getElementById('quick_customer_name').value = '';
            document.getElementById('quick_customer_phone').value = '';
            document.getElementById('quick_customer_email').value = '';

            const modalInstance = bootstrap.Modal.getInstance(quickCustomerModalEl);
            if (modalInstance) {
                modalInstance.hide();
            }
        })
        .catch(err => {
            btnSaveQuickCustomer.disabled = false;
            const p = document.createElement('p');
            p.className = 'mb-0';
            p.textContent = 'Ocurrió un error al procesar la solicitud. Intente nuevamente.';
            quickCustomerErrors.appendChild(p);
            quickCustomerErrors.classList.remove('d-none');
        });
    });

    // Restricciones en tiempo real para el modal de cliente rápido
    const quickDocInput = document.getElementById('quick_customer_document');
    if (quickDocInput) {
        quickDocInput.addEventListener('input', function() {
            let val = this.value.replace(/[^0-9-]/g, '');
            const parts = val.split('-');
            if (parts.length > 2) {
                val = parts[0] + '-' + parts.slice(1).join('');
            }
            this.value = val;
        });
    }

    const quickPhoneInput = document.getElementById('quick_customer_phone');
    if (quickPhoneInput) {
        quickPhoneInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^\d\s+\-()]/g, '');
        });
    }

    // ── Utilities ──
    function formatNumber(n) {
        return parseFloat(n).toLocaleString('es-CO', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }
});
</script>
@endsection
