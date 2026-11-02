@extends('layouts.app')

@section('title', 'Nuevo Pedido Domicilio / Para Llevar')

@section('content')
<div class="container py-4 pt-2 pt-md-3" style="max-width: 900px;">
    <div class="d-flex align-items-center mb-4 mt-1">
        <a href="{{ route('restaurant.orders.deliveries') }}" class="btn btn-outline-secondary btn-sm me-3">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <h1 class="h3 fw-bold mb-0" style="line-height: 1.35; padding-top: 3px;">Nuevo Pedido a Domicilio / Para Llevar</h1>
            <p class="text-muted small mb-0">Registra pedidos de entrega o recogida en el restaurante con tirilla de despacho.</p>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4">
            <form action="{{ route('restaurant.orders.store-delivery') }}" method="POST" id="deliveryForm">
                @csrf

                {{-- Selector Tipo de Pedido --}}
                <div class="mb-4">
                    <label class="form-label fw-bold d-block">Tipo de Pedido <span class="text-danger">*</span></label>
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="order_type" id="type_delivery" value="delivery" {{ old('order_type', 'delivery') === 'delivery' ? 'checked' : '' }}>
                        <label class="btn btn-outline-primary py-2 fw-semibold" for="type_delivery">
                            <i class="bi bi-bicycle me-1"></i> Domicilio
                        </label>

                        <input type="radio" class="btn-check" name="order_type" id="type_takeout" value="takeout" {{ old('order_type') === 'takeout' ? 'checked' : '' }}>
                        <label class="btn btn-outline-primary py-2 fw-semibold" for="type_takeout">
                            <i class="bi bi-bag-check me-1"></i> Para Llevar / Recoger
                        </label>
                    </div>
                </div>

                {{-- Datos del Cliente --}}
                <div class="border rounded-3 p-3 mb-4 bg-light">
                    <h5 class="fw-bold mb-3 text-dark">
                        <i class="bi bi-person-fill text-primary me-2"></i>Datos del Cliente y Entrega
                    </h5>

                    <input type="hidden" name="customer_id" id="customer_id" value="{{ old('customer_id') }}">

                    {{-- Buscador y Autocompletado de Cliente Frecuente (Selector Dual Ergonómico) --}}
                    <x-customer-lookup :customers="$customers" :selected-id="old('customer_id')" />

                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label for="customer_name" class="form-label fw-bold small">Nombre del Cliente <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('customer_name') is-invalid @enderror" id="customer_name" name="customer_name" value="{{ old('customer_name') }}" placeholder="Ej: María Camila López" minlength="2" maxlength="80" autocomplete="off" required>
                            @error('customer_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="delivery_phone" class="form-label fw-bold small" id="phone_label">Teléfono / WhatsApp <span class="text-danger delivery-req">*</span></label>
                            <input type="tel" class="form-control font-monospace @error('delivery_phone') is-invalid @enderror" id="delivery_phone" name="delivery_phone" value="{{ old('delivery_phone') }}" placeholder="Ej: 315 123 4567" minlength="7" maxlength="15" inputmode="tel" autocomplete="off">
                            @error('delivery_phone')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12" id="address_group">
                            <label for="delivery_address" class="form-label fw-bold small">Dirección Exacta de Entrega <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('delivery_address') is-invalid @enderror" id="delivery_address" name="delivery_address" value="{{ old('delivery_address') }}" placeholder="Ej: Cra 100 # 15-20, Apto 302, Barrio San Fernando" minlength="5" maxlength="150" autocomplete="off">
                            @error('delivery_address')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-8">
                            <label for="delivery_notes" class="form-label fw-bold small">Instrucciones de Entrega / Referencia</label>
                            <input type="text" class="form-control @error('delivery_notes') is-invalid @enderror" id="delivery_notes" name="delivery_notes" value="{{ old('delivery_notes') }}" placeholder="Ej: Timbre blanco, dejar en portería, edificio frente al parque" maxlength="255">
                            @error('delivery_notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-4" id="fee_group">
                            <label for="delivery_fee" class="form-label fw-bold small">Costo de Domicilio ($)</label>
                            <input type="number" step="500" min="0" max="9999999" class="form-control @error('delivery_fee') is-invalid @enderror" id="delivery_fee" name="delivery_fee" value="{{ old('delivery_fee', '0') }}">
                            @error('delivery_fee')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Sección de Ítems --}}
                <div class="border-top pt-3 mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold mb-0 text-dark">
                            <i class="bi bi-cart-plus-fill text-primary me-2"></i>Platos o Productos
                        </h5>
                        <button type="button" class="btn btn-outline-primary fw-semibold px-3 py-2 d-inline-flex align-items-center gap-2" id="btn-add-item" style="min-height: 44px;">
                            <i class="bi bi-plus-circle fs-5"></i>
                            <span>Agregar otro plato</span>
                        </button>
                    </div>

                    @php
                        $groupedProducts = $products->groupBy(fn($p) => $p->category?->name ?? 'General / Sin Categoría');
                    @endphp

                    <div id="items-container">
                        {{-- Primera línea predeterminada --}}
                        <div class="card bg-light border-0 mb-3 item-row p-3 rounded-3">
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
                                                    <option value="{{ $prod->id }}" data-category="{{ $categoryName }}" data-price="{{ $prod->sale_price }}">
                                                        {{ $prod->name }} (${{ number_format($prod->sale_price, 2) }})
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
                                        <input type="number" name="items[0][quantity]" class="form-control text-center input-qty input-stepper" value="1" min="1" max="9999" required>
                                        <button type="button" class="btn btn-outline-secondary btn-qty-plus fw-bold px-2">+</button>
                                    </div>
                                </div>
                                <div class="col-6 col-md-4">
                                    <label class="form-label small fw-semibold">Observación</label>
                                    <input type="text" class="form-control form-select-sm" name="items[0][notes]" placeholder="Ej: Sin cebolla, extra salsa" maxlength="255">
                                </div>
                                <div class="col-12 col-md-1 text-end pt-md-4">
                                    <button type="button" class="btn btn-sm btn-outline-danger remove-item-btn" disabled>
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-4">
                    <label for="notes" class="form-label fw-bold small">Notas Generales de la Orden</label>
                    <textarea class="form-control" id="notes" name="notes" rows="2" maxlength="500" placeholder="Opcional: Paga con billete de $50.000, llevar cambio de $20.000">{{ old('notes') }}</textarea>
                </div>

                <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                    <a href="{{ route('restaurant.orders.deliveries') }}" class="btn btn-light px-4 py-2 d-inline-flex align-items-center" style="min-height: 44px;">Cancelar</a>
                    <button type="submit" class="btn btn-primary px-4 py-2 fw-bold d-inline-flex align-items-center shadow-sm" style="min-height: 44px;">
                        <i class="bi bi-check-lg fs-5 me-2"></i> Registrar Pedido
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<template id="item-row-template">
    <div class="card bg-light border-0 mb-3 item-row p-3 rounded-3">
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
                                <option value="{{ $prod->id }}" data-category="{{ $categoryName }}" data-price="{{ $prod->sale_price }}">
                                    {{ $prod->name }} (${{ number_format($prod->sale_price, 2) }})
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
                    <input type="number" name="items[INDEX][quantity]" class="form-control text-center input-qty input-stepper" value="1" min="1" max="9999" required>
                    <button type="button" class="btn btn-outline-secondary btn-qty-plus fw-bold px-2">+</button>
                </div>
            </div>
            <div class="col-6 col-md-4">
                <label class="form-label small fw-semibold">Observación</label>
                <input type="text" class="form-control form-select-sm" name="items[INDEX][notes]" placeholder="Ej: Sin cebolla, extra salsa" maxlength="255">
            </div>
            <div class="col-12 col-md-1 text-end pt-md-4">
                <button type="button" class="btn btn-sm btn-outline-danger remove-item-btn">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        </div>
    </div>
</template>

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
                    <label for="quick_customer_phone" class="form-label small fw-semibold text-muted">Teléfono / WhatsApp <span class="text-muted">(opcional)</span></label>
                    <input type="tel" id="quick_customer_phone" class="form-control font-monospace" placeholder="Ej: 3151234567" minlength="7" maxlength="15" inputmode="tel" autocomplete="off">
                    <div class="form-text small">Solo números y símbolos telefónicos (+, -).</div>
                </div>

                <div class="mb-3">
                    <label for="quick_customer_address" class="form-label small fw-semibold text-muted">Dirección de Entrega <span class="text-muted">(opcional)</span></label>
                    <input type="text" id="quick_customer_address" class="form-control" placeholder="Ej: Cra 100 # 15-20, Apto 302" minlength="5" maxlength="150" autocomplete="off">
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
    const typeDelivery = document.getElementById('type_delivery');
    const typeTakeout = document.getElementById('type_takeout');
    const addressGroup = document.getElementById('address_group');
    const feeGroup = document.getElementById('fee_group');
    const deliveryAddress = document.getElementById('delivery_address');
    const deliveryFee = document.getElementById('delivery_fee');

    function syncOrderType() {
        if (typeTakeout.checked) {
            addressGroup.style.display = 'none';
            feeGroup.style.display = 'none';
            deliveryAddress.removeAttribute('required');
            deliveryFee.value = '0';
        } else {
            addressGroup.style.display = 'block';
            feeGroup.style.display = 'block';
            deliveryAddress.setAttribute('required', 'required');
        }
    }

    if (typeDelivery && typeTakeout) {
        typeDelivery.addEventListener('change', syncOrderType);
        typeTakeout.addEventListener('change', syncOrderType);
        syncOrderType();
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
            let val = parseInt(qtyInput.value) || 1;
            qtyInput.value = val + 1;
            qtyInput.dispatchEvent(new Event('input', { bubbles: true }));
            qtyInput.dispatchEvent(new Event('change', { bubbles: true }));
        });
    }

    // Manejo de filtrado rápido y combobox instantáneo de platos
    function attachProductSearch(row) {
        const searchInput = row.querySelector('.product-search-input');
        const select = row.querySelector('.product-select');
        const dropdown = row.querySelector('.product-dropdown-list');
        if (!searchInput || !select || !dropdown) return;

        function normalizeStr(str) {
            return (str || '')
                .normalize("NFD")
                .replace(/[\u0300-\u036f]/g, "")
                .toLowerCase();
        }

        const productsList = [];
        select.querySelectorAll('option').forEach(opt => {
            if (opt.value) {
                const rawText = opt.textContent.trim();
                const category = opt.getAttribute('data-category') || opt.closest('optgroup')?.label || 'General';
                const price = opt.getAttribute('data-price') || '';
                const cleanName = rawText.replace(/\s*\(\$[\d,\.]+\)\s*$/, '').trim();
                productsList.push({
                    id: opt.value,
                    fullName: rawText,
                    name: cleanName,
                    category: category,
                    price: price
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
                rightDiv.className = 'text-end fw-bold text-primary small';
                rightDiv.textContent = p.price ? '$' + Number(p.price).toLocaleString('es-CO', {minimumFractionDigits: 2}) : '';

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
            }
            dropdown.classList.add('d-none');
            highlightedIndex = -1;
        });
    }

    // Inicializar controles en filas existentes
    document.querySelectorAll('.item-row').forEach(row => {
        attachQuantityControls(row);
        attachProductSearch(row);
    });

    // Dynamic items
    const container = document.getElementById('items-container');
    const template = document.getElementById('item-row-template');
    const addBtn = document.getElementById('btn-add-item');
    let itemIndex = 1;

    if (addBtn && template && container) {
        addBtn.addEventListener('click', function () {
            const clone = template.content.cloneNode(true);
            const row = clone.querySelector('.item-row');
            row.innerHTML = row.innerHTML.replace(/INDEX/g, itemIndex);
            
            row.querySelector('.remove-item-btn').addEventListener('click', function () {
                row.remove();
            });

            attachQuantityControls(row);
            attachProductSearch(row);

            container.appendChild(clone);
            itemIndex++;
        });
    }

    // Autocompletado reactivo y selector dual de clientes frecuentes
    const customerSearchInput = document.getElementById('customer_search_input');
    const customerSearchResults = document.getElementById('customer_search_results');
    const customerSelectDropdown = document.getElementById('customer_select_dropdown');
    const btnClearCustomer = document.getElementById('btn_clear_customer');
    const customerIdInput = document.getElementById('customer_id');
    const customerNameInput = document.getElementById('customer_name');
    const deliveryPhoneInput = document.getElementById('delivery_phone');
    const selectedCustomerBadge = document.getElementById('selected_customer_badge');
    const selectedCustomerNameLabel = document.getElementById('selected_customer_name_label');

    let searchTimeout = null;
    let highlightedCustomerIndex = -1;

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

    // Sincronización del Selector Desplegable Complementario
    if (customerSelectDropdown) {
        customerSelectDropdown.addEventListener('change', function () {
            const selectedOpt = this.options[this.selectedIndex];
            const customerId = this.value;

            if (customerId && selectedOpt) {
                const name = selectedOpt.dataset.name || '';
                const phone = selectedOpt.dataset.phone || '';
                const address = selectedOpt.dataset.address || '';

                if (customerIdInput) customerIdInput.value = customerId;
                if (customerNameInput) customerNameInput.value = name;
                if (deliveryPhoneInput && phone) deliveryPhoneInput.value = phone;
                if (deliveryAddress && address) deliveryAddress.value = address;

                if (customerSearchInput) customerSearchInput.value = name;
                if (selectedCustomerNameLabel) selectedCustomerNameLabel.textContent = name;
                if (selectedCustomerBadge) selectedCustomerBadge.classList.remove('d-none');
                if (btnClearCustomer) btnClearCustomer.classList.remove('d-none');
                if (customerSearchResults) customerSearchResults.classList.add('d-none');
                highlightedCustomerIndex = -1;

                // Foco al siguiente campo lógico según ergonomía POS
                if (deliveryAddress && !deliveryAddress.value) {
                    deliveryAddress.focus();
                } else if (deliveryPhoneInput && !deliveryPhoneInput.value) {
                    deliveryPhoneInput.focus();
                }
            } else {
                // Opción vacía seleccionada: limpiar customer_id y permitir ingreso manual
                if (customerIdInput) customerIdInput.value = '';
                if (customerSearchInput) customerSearchInput.value = '';
                if (selectedCustomerBadge) selectedCustomerBadge.classList.add('d-none');
                if (btnClearCustomer) btnClearCustomer.classList.add('d-none');
                if (customerSearchResults) customerSearchResults.classList.add('d-none');
                highlightedCustomerIndex = -1;
            }
        });
    }

    if (customerSearchInput && customerSearchResults) {
        customerSearchInput.addEventListener('keydown', function (e) {
            const items = customerSearchResults.querySelectorAll('button.customer-result-item');
            const isVisible = !customerSearchResults.classList.contains('d-none') && items.length > 0;

            if (e.key === 'Enter' || e.keyCode === 13) {
                e.preventDefault(); // Prevenir incondicionalmente el envío del formulario
                if (isVisible) {
                    const targetBtn = (highlightedCustomerIndex >= 0 && items[highlightedCustomerIndex])
                        ? items[highlightedCustomerIndex]
                        : items[0];
                    if (targetBtn) {
                        targetBtn.click();
                    }
                } else {
                    if (deliveryAddress && !deliveryAddress.value) {
                        deliveryAddress.focus();
                    } else if (deliveryPhoneInput && !deliveryPhoneInput.value) {
                        deliveryPhoneInput.focus();
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

        customerSearchInput.addEventListener('input', function () {
            clearTimeout(searchTimeout);
            highlightedCustomerIndex = -1;
            const query = this.value.trim();

            if (query.length < 3) {
                customerSearchResults.innerHTML = '';
                customerSearchResults.classList.add('d-none');
                return;
            }

            searchTimeout = setTimeout(() => {
                fetch(`{{ route('customers.search') }}?q=${encodeURIComponent(query)}`)
                    .then(res => res.json())
                    .then(customers => {
                        customerSearchResults.innerHTML = '';
                        highlightedCustomerIndex = -1;
                        if (!customers || customers.length === 0) {
                            const emptyDiv = document.createElement('div');
                            emptyDiv.className = 'list-group-item text-muted small py-2 px-3';
                            emptyDiv.textContent = 'No se encontraron clientes coincidentes';
                            customerSearchResults.appendChild(emptyDiv);
                            customerSearchResults.classList.remove('d-none');
                            return;
                        }

                        customers.forEach((cust, idx) => {
                            const btn = document.createElement('button');
                            btn.type = 'button';
                            btn.className = 'list-group-item list-group-item-action py-2 px-3 text-start border-bottom customer-result-item';
                            btn.dataset.index = idx;

                            const headerDiv = document.createElement('div');
                            headerDiv.className = 'd-flex justify-content-between align-items-center';

                            const nameStrong = document.createElement('strong');
                            nameStrong.className = 'text-dark';
                            nameStrong.textContent = cust.name;
                            headerDiv.appendChild(nameStrong);

                            const docBadge = document.createElement('span');
                            docBadge.className = 'badge bg-light text-dark border font-monospace';
                            docBadge.textContent = cust.document || 'Sin doc';
                            headerDiv.appendChild(docBadge);

                            btn.appendChild(headerDiv);

                            if (cust.phone || cust.address) {
                                const detailDiv = document.createElement('div');
                                detailDiv.className = 'small text-muted mt-1';

                                if (cust.phone) {
                                    const phoneIcon = document.createElement('i');
                                    phoneIcon.className = 'bi bi-telephone me-1';
                                    detailDiv.appendChild(phoneIcon);
                                    detailDiv.appendChild(document.createTextNode(cust.phone));
                                }

                                if (cust.phone && cust.address) {
                                    detailDiv.appendChild(document.createTextNode(' • '));
                                }

                                if (cust.address) {
                                    const geoIcon = document.createElement('i');
                                    geoIcon.className = 'bi bi-geo-alt me-1';
                                    detailDiv.appendChild(geoIcon);
                                    detailDiv.appendChild(document.createTextNode(cust.address));
                                }

                                btn.appendChild(detailDiv);
                            }

                            btn.addEventListener('mouseenter', function () {
                                highlightedCustomerIndex = idx;
                                const items = customerSearchResults.querySelectorAll('button.customer-result-item');
                                updateCustomerHighlight(items);
                            });

                            btn.addEventListener('click', function (e) {
                                e.preventDefault();
                                e.stopPropagation();

                                if (customerIdInput) customerIdInput.value = cust.id;
                                if (customerNameInput) customerNameInput.value = cust.name;
                                if (deliveryPhoneInput && cust.phone) deliveryPhoneInput.value = cust.phone;
                                if (deliveryAddress && cust.address) deliveryAddress.value = cust.address;

                                if (customerSearchInput) customerSearchInput.value = cust.name;
                                if (selectedCustomerNameLabel) selectedCustomerNameLabel.textContent = cust.name;
                                if (selectedCustomerBadge) selectedCustomerBadge.classList.remove('d-none');
                                if (btnClearCustomer) btnClearCustomer.classList.remove('d-none');

                                // Sincronizar selector desplegable complementario
                                if (customerSelectDropdown) {
                                    customerSelectDropdown.value = cust.id;
                                }

                                customerSearchResults.classList.add('d-none');
                                highlightedCustomerIndex = -1;

                                // Foco al siguiente campo lógico
                                if (deliveryAddress && !deliveryAddress.value) {
                                    deliveryAddress.focus();
                                } else if (deliveryPhoneInput && !deliveryPhoneInput.value) {
                                    deliveryPhoneInput.focus();
                                } else if (deliveryAddress) {
                                    deliveryAddress.focus();
                                }
                            });

                            customerSearchResults.appendChild(btn);
                        });

                        customerSearchResults.classList.remove('d-none');
                    })
                    .catch(() => {
                        customerSearchResults.classList.add('d-none');
                    });
            }, 250);
        });

        if (btnClearCustomer) {
            btnClearCustomer.addEventListener('click', function () {
                if (customerIdInput) customerIdInput.value = '';
                if (customerNameInput) customerNameInput.value = '';
                if (deliveryPhoneInput) deliveryPhoneInput.value = '';
                if (deliveryAddress) deliveryAddress.value = '';
                if (customerSearchInput) customerSearchInput.value = '';

                // Resetear selector desplegable
                if (customerSelectDropdown) {
                    customerSelectDropdown.value = '';
                }

                if (selectedCustomerBadge) selectedCustomerBadge.classList.add('d-none');
                btnClearCustomer.classList.add('d-none');
                customerSearchResults.classList.add('d-none');
                highlightedCustomerIndex = -1;

                if (customerNameInput) customerNameInput.focus();
            });
        }

        document.addEventListener('click', function (e) {
            if (!customerSearchInput.contains(e.target) && !customerSearchResults.contains(e.target)) {
                customerSearchResults.classList.add('d-none');
            }
        });
    }

    // Sincronización inicial si hay un cliente preseleccionado (ej. tras error de validación)
    if (customerIdInput && customerIdInput.value && customerSelectDropdown) {
        customerSelectDropdown.value = customerIdInput.value;
        const opt = customerSelectDropdown.options[customerSelectDropdown.selectedIndex];
        if (opt && opt.value) {
            if (selectedCustomerNameLabel) selectedCustomerNameLabel.textContent = opt.dataset.name || '';
            if (selectedCustomerBadge) selectedCustomerBadge.classList.remove('d-none');
            if (btnClearCustomer) btnClearCustomer.classList.remove('d-none');
            if (customerSearchInput && !customerSearchInput.value) customerSearchInput.value = opt.dataset.name || '';
        }
    }

    // Manejo de Modal de Creación Rápida de Cliente
    const quickCustomerModalEl = document.getElementById('quickCustomerModal');
    const btnSaveQuickCustomer = document.getElementById('btn_save_quick_customer');
    const quickCustomerErrors = document.getElementById('quickCustomerErrors');

    if (btnSaveQuickCustomer && quickCustomerModalEl) {
        btnSaveQuickCustomer.addEventListener('click', function () {
            if (quickCustomerErrors) {
                quickCustomerErrors.classList.add('d-none');
                quickCustomerErrors.innerHTML = '';
            }

            const docInput = document.getElementById('quick_customer_document');
            const nameInput = document.getElementById('quick_customer_name');
            const phoneInput = document.getElementById('quick_customer_phone');
            const addressInput = document.getElementById('quick_customer_address');
            const emailInput = document.getElementById('quick_customer_email');

            const docVal = (docInput ? docInput.value : '').trim();
            const nameVal = (nameInput ? nameInput.value : '').trim();
            const phoneVal = (phoneInput ? phoneInput.value : '').trim();
            const addressVal = (addressInput ? addressInput.value : '').trim();
            const emailVal = (emailInput ? emailInput.value : '').trim();

            btnSaveQuickCustomer.disabled = true;
            btnSaveQuickCustomer.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando...';

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
                || document.querySelector('input[name="_token"]')?.value
                || '{{ csrf_token() }}';

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
                    phone: phoneVal || null,
                    address: addressVal || null,
                    email: emailVal || null,
                    is_active: true
                })
            })
            .then(async function (response) {
                const data = await response.json();
                btnSaveQuickCustomer.disabled = false;
                btnSaveQuickCustomer.innerHTML = '<i class="bi bi-box-arrow-in-down me-1"></i> Guardar Cliente';

                if (!response.ok) {
                    if (quickCustomerErrors) {
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
                        quickCustomerErrors.appendChild(ul);
                        quickCustomerErrors.classList.remove('d-none');
                    }
                    return;
                }

                // 1. Agregar nueva opción al select complementario si no existe y seleccionarla
                if (customerSelectDropdown) {
                    let opt = customerSelectDropdown.querySelector('option[value="' + data.id + '"]');
                    if (!opt) {
                        opt = document.createElement('option');
                        opt.value = data.id;
                        opt.dataset.name = data.name;
                        opt.dataset.document = data.document || '';
                        opt.dataset.phone = data.phone || '';
                        opt.dataset.address = data.address || addressVal || '';
                        opt.textContent = data.name + (data.phone ? ' · ' + data.phone : '') + (data.document ? ' (' + data.document + ')' : '');
                        customerSelectDropdown.appendChild(opt);
                    }
                    customerSelectDropdown.value = data.id;
                }

                // 2. Autocompletar datos del pedido
                if (customerIdInput) customerIdInput.value = data.id;
                if (customerNameInput) customerNameInput.value = data.name;
                if (deliveryPhoneInput && (data.phone || phoneVal)) {
                    deliveryPhoneInput.value = data.phone || phoneVal;
                }
                if (deliveryAddress && (data.address || addressVal) && typeDelivery.checked) {
                    deliveryAddress.value = data.address || addressVal;
                }

                // 3. Sincronizar badge y buscador
                if (customerSearchInput) customerSearchInput.value = data.name;
                if (selectedCustomerNameLabel) selectedCustomerNameLabel.textContent = data.name;
                if (selectedCustomerBadge) selectedCustomerBadge.classList.remove('d-none');
                if (btnClearCustomer) btnClearCustomer.classList.remove('d-none');
                if (customerSearchResults) customerSearchResults.classList.add('d-none');

                // 4. Limpiar formulario modal
                if (docInput) docInput.value = '';
                if (nameInput) nameInput.value = '';
                if (phoneInput) phoneInput.value = '';
                if (addressInput) addressInput.value = '';
                if (emailInput) emailInput.value = '';

                // 5. Cerrar modal
                const modalInstance = bootstrap.Modal.getInstance(quickCustomerModalEl);
                if (modalInstance) {
                    modalInstance.hide();
                }

                // 6. Foco inteligente al siguiente campo lógico
                if (deliveryAddress && !deliveryAddress.value && typeDelivery.checked) {
                    deliveryAddress.focus();
                } else if (deliveryPhoneInput && !deliveryPhoneInput.value) {
                    deliveryPhoneInput.focus();
                }
            })
            .catch(function () {
                btnSaveQuickCustomer.disabled = false;
                btnSaveQuickCustomer.innerHTML = '<i class="bi bi-box-arrow-in-down me-1"></i> Guardar Cliente';
                if (quickCustomerErrors) {
                    const p = document.createElement('p');
                    p.className = 'mb-0';
                    p.textContent = 'Ocurrió un error al registrar el cliente. Intente nuevamente.';
                    quickCustomerErrors.appendChild(p);
                    quickCustomerErrors.classList.remove('d-none');
                }
            });
        });
    }
});
</script>
@endsection
