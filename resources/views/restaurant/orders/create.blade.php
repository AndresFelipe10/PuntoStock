@extends('layouts.app')

@section('title', 'Abrir Comanda')

@section('content')
<div class="container py-4" style="max-width: 800px;">
    <div class="d-flex align-items-center mb-4">
        <a href="{{ route('restaurant.tables.index') }}" class="btn btn-outline-secondary btn-sm me-3">
            <i class="bi bi-arrow-left"></i>
        </a>
        <h1 class="h3 fw-bold mb-0">Abrir Nueva Comanda</h1>
    </div>

    @if($tables->isEmpty())
        <div class="alert alert-warning shadow-sm rounded-4 p-4 text-center">
            <i class="bi bi-exclamation-triangle fs-3 d-block mb-2"></i>
            <h5 class="fw-bold">No hay mesas libres disponibles</h5>
            <p class="mb-3">Todas las mesas se encuentran ocupadas o no hay mesas activas configuradas en el salón.</p>
            <a href="{{ route('restaurant.tables.index') }}" class="btn btn-primary">
                Ver Salón de Mesas
            </a>
        </div>
    @else
        <div class="card border-0 shadow-sm rounded-4">
            <div class="card-body p-4">
                <form action="{{ route('restaurant.orders.store') }}" method="POST" id="orderForm">
                    @csrf

                    <div class="row g-3 mb-4">
                        <div class="col-12 col-md-6">
                            <label for="table_id" class="form-label fw-bold">Seleccionar Mesa <span class="text-danger">*</span></label>
                            <select class="form-select @error('table_id') is-invalid @enderror" id="table_id" name="table_id" required>
                                <option value="" disabled {{ old('table_id', $selectedTableId) ? '' : 'selected' }}>-- Selecciona una mesa disponible --</option>
                                @foreach($tables as $table)
                                    <option value="{{ $table->id }}" {{ (string) old('table_id', $selectedTableId) === (string) $table->id ? 'selected' : '' }}>
                                        {{ $table->name }} (Capacidad: {{ $table->capacity }} personas)
                                    </option>
                                @endforeach
                            </select>
                            @error('table_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label for="customer_name" class="form-label fw-bold mb-0">Cliente / Referencia (Opcional)</label>
                                @if(auth()->check())
                                    <span class="badge bg-light text-muted border py-1 px-2" title="Nombre del Mesero (Opcional)">
                                        <i class="bi bi-person-badge me-1"></i>Nombre del Mesero (Opcional): {{ auth()->user()->name }}
                                    </span>
                                @endif
                            </div>
                            <input type="text" class="form-control @error('customer_name') is-invalid @enderror" 
                                   id="customer_name" 
                                   name="customer_name" 
                                   maxlength="80"
                                   autocomplete="off"
                                   value="{{ old('customer_name') }}" 
                                   placeholder="Ej: Consumidor Final, Familia Gómez...">
                            @error('customer_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-3">
                            <label for="guest_count" class="form-label fw-bold">Número de Personas (Opcional)</label>
                            <input type="number" name="guest_count" id="guest_count" class="form-control @error('guest_count') is-invalid @enderror" min="1" max="99" value="{{ old('guest_count') }}" placeholder="Ej: 2, 4">
                            @error('guest_count')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <label for="notes" class="form-label fw-bold">Observaciones Generales</label>
                            <textarea class="form-control @error('notes') is-invalid @enderror" id="notes" name="notes" rows="2" maxlength="500" placeholder="Opcional: Ubicación de mesa, preferencia especial, etc.">{{ old('notes') }}</textarea>
                            @error('notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    {{-- Sección de Ítems Iniciales --}}
                    <div class="border-top pt-4 mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="fw-bold mb-0">Platos o Productos Iniciales (Opcional)</h5>
                            <button type="button" class="btn btn-outline-primary fw-semibold px-3 py-2 d-inline-flex align-items-center gap-2" id="btn-add-item" style="min-height: 44px;">
                                <i class="bi bi-plus-circle fs-5"></i>
                                <span>Agregar plato</span>
                            </button>
                        </div>

                        {{-- Slot contextual para alerta de stock (Mobile-First, no altera la barra superior) --}}
                        <div id="stock-error-container" class="w-100"></div>

                        <div id="items-container">
                            {{-- Fila inicial vacía --}}
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                        <a href="{{ route('restaurant.tables.index') }}" class="btn btn-light px-4 py-2 d-inline-flex align-items-center" style="min-height: 44px;">Cancelar</a>
                        <button type="submit" class="btn btn-primary px-4 py-2 fw-bold d-inline-flex align-items-center shadow-sm" style="min-height: 44px;">
                            <i class="bi bi-check-lg fs-5 me-2"></i> Abrir Comanda
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>

@php
    $groupedProducts = $products->groupBy(fn($p) => $p->category?->name ?? 'General / Sin Categoría');
@endphp

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
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label class="form-label small fw-semibold mb-0">Cantidad <span class="text-danger">*</span></label>
                    <span class="stock-hint badge rounded-pill bg-light text-secondary border d-none" style="font-size: 0.7rem; font-weight: 500;"></span>
                </div>
                <div class="input-group input-group-sm" style="max-width: 130px;">
                    <button type="button" class="btn btn-outline-secondary btn-qty-minus fw-bold px-2">−</button>
                    <input type="number" name="items[INDEX][quantity]" class="form-control text-center input-qty input-stepper" value="1" min="1" max="999" required>
                    <button type="button" class="btn btn-outline-secondary btn-qty-plus fw-bold px-2">+</button>
                </div>
            </div>
            <div class="col-6 col-md-4">
                <label class="form-label small fw-semibold">Observación / Modificación</label>
                <input type="text" class="form-control form-select-sm" name="items[INDEX][notes]" placeholder="Ej: Sin cebolla, término 3/4">
            </div>
            <div class="col-12 col-md-1 text-end pt-md-4">
                <button type="button" class="btn btn-sm btn-outline-danger remove-item-btn">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        </div>
    </div>
</template>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const container = document.getElementById('items-container');
    const template = document.getElementById('item-row-template');
    const addBtn = document.getElementById('btn-add-item');
    let itemIndex = 0;

    function showStockErrorBanner(productName, availableStock, requestedQuantity, targetContainer) {
        let container = targetContainer || document.getElementById('stock-error-container');
        if (!container) {
            const form = document.getElementById('orderForm') || document.querySelector('form');
            if (form) {
                container = form.querySelector('#stock-error-container');
                if (!container) {
                    container = document.createElement('div');
                    container.id = 'stock-error-container';
                    container.className = 'w-100 mb-3';
                    const itemsContainer = document.getElementById('items-container') || form.firstElementChild;
                    if (itemsContainer) {
                        itemsContainer.parentNode.insertBefore(container, itemsContainer);
                    } else {
                        form.prepend(container);
                    }
                }
            }
        }

        const availableFormatted = (parseFloat(availableStock) == parseInt(availableStock)) ? parseInt(availableStock) : availableStock;
        const requestedFormatted = (parseFloat(requestedQuantity) == parseInt(requestedQuantity)) ? parseInt(requestedQuantity) : requestedQuantity;

        let banner = document.getElementById('stock-error-banner');
        if (banner) {
            const textSpan = banner.querySelector('.stock-error-message');
            if (textSpan) {
                textSpan.textContent = `Stock insuficiente para '${productName}'. Existencias disponibles: ${availableFormatted}, Cantidad solicitada: ${requestedFormatted}.`;
            }
            banner.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            return;
        }

        banner = document.createElement('div');
        banner.id = 'stock-error-banner';
        banner.className = 'alert shadow-sm';
        banner.setAttribute('role', 'alert');
        banner.style.cssText = 'width: 100%; box-sizing: border-box; background-color: #fef2f2; border: 1px solid #fecaca; border-left: 4px solid #ef4444; border-radius: 10px; padding: 12px 16px; margin-bottom: 16px; display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; color: #991b1b; font-size: 0.875rem; line-height: 1.4;';

        banner.innerHTML = `
            <div style="display: flex; align-items: flex-start; gap: 10px; flex: 1; min-width: 0;">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="bi bi-exclamation-triangle-fill flex-shrink-0" viewBox="0 0 16 16" style="margin-top: 2px; color: #ef4444;">
                    <path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/>
                </svg>
                <div style="flex: 1; min-width: 0; word-break: break-word;">
                    <strong style="display: block; font-weight: 700; margin-bottom: 2px;">Error al procesar:</strong>
                    <span class="stock-error-message">Stock insuficiente para '${productName}'. Existencias disponibles: ${availableFormatted}, Cantidad solicitada: ${requestedFormatted}.</span>
                </div>
            </div>
            <button type="button" class="btn-close" style="font-size: 10px; padding: 4px; margin: 0; opacity: 0.7; flex-shrink: 0;" aria-label="Close" onclick="this.closest('#stock-error-banner').remove();"></button>
        `;

        if (container) {
            container.appendChild(banner);
        }
        banner.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function updateStockHint(row, opt) {
        const stockHint = row.querySelector('.stock-hint');
        const qtyInput = row.querySelector('.input-qty');
        if (!stockHint) return;

        if (!opt || !opt.value) {
            stockHint.classList.add('d-none');
            if (qtyInput) qtyInput.removeAttribute('max');
            return;
        }

        const isStd = opt.getAttribute('data-is-standard') === '1';
        const stock = parseFloat(opt.getAttribute('data-stock') || '0');
        const formattedStock = (stock == parseInt(stock)) ? parseInt(stock) : stock;

        if (isStd) {
            stockHint.textContent = stock <= 0 ? 'Agotado' : `${formattedStock} disp.`;
            stockHint.className = stock <= 0 
                ? 'stock-hint badge rounded-pill bg-danger-subtle text-danger border border-danger-subtle ms-1'
                : 'stock-hint badge rounded-pill bg-light text-secondary border ms-1';
            stockHint.classList.remove('d-none');
            if (qtyInput) {
                qtyInput.setAttribute('max', stock > 0 ? stock : 1);
            }
        } else {
            stockHint.classList.add('d-none');
            if (qtyInput) {
                qtyInput.removeAttribute('max');
            }
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

    function attachQuantityControls(row) {
        const btnMinus = row.querySelector('.btn-qty-minus');
        const btnPlus = row.querySelector('.btn-qty-plus');
        const qtyInput = row.querySelector('.input-qty');
        const select = row.querySelector('.product-select');
        if (!btnMinus || !btnPlus || !qtyInput) return;

        if (select && select.selectedIndex >= 0) {
            updateStockHint(row, select.options[select.selectedIndex]);
        }

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
                const cleanName = opt.getAttribute('data-name') || rawText.replace(/\s*—.*$/, '').trim();
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
                leftDiv.className = 'pe-2 d-flex align-items-center gap-2 text-truncate';

                const nameSpan = document.createElement('span');
                nameSpan.className = 'fw-semibold text-dark';
                nameSpan.textContent = p.name;
                leftDiv.appendChild(nameSpan);

                const priceSpan = document.createElement('span');
                priceSpan.className = 'text-muted small';
                priceSpan.textContent = p.price ? '$' + Number(p.price).toLocaleString('es-CO', {maximumFractionDigits: 0}) : '';
                leftDiv.appendChild(priceSpan);

                if (p.category) {
                    const catBadge = document.createElement('span');
                    catBadge.className = 'badge bg-light text-secondary border';
                    catBadge.style.fontSize = '0.7rem';
                    catBadge.textContent = p.category;
                    leftDiv.appendChild(catBadge);
                }

                const rightDiv = document.createElement('div');
                rightDiv.className = 'flex-shrink-0';

                if (p.isStandard) {
                    const stockBadge = document.createElement('span');
                    const formattedStk = (p.stock == parseInt(p.stock)) ? parseInt(p.stock) : p.stock;
                    if (p.stock <= 0) {
                        stockBadge.className = 'badge rounded-pill border';
                        stockBadge.style.cssText = 'background-color: #fef2f2; color: #dc2626; border-color: #fecaca; font-size: 0.75rem; padding: 3px 9px; font-weight: 500;';
                        stockBadge.textContent = 'Agotado';
                    } else {
                        stockBadge.className = 'badge rounded-pill border';
                        stockBadge.style.cssText = 'background-color: #f1f5f9; color: #475569; border-color: #e2e8f0; font-size: 0.75rem; padding: 3px 9px; font-weight: 500;';
                        stockBadge.textContent = `${formattedStk} disp.`;
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
            updateStockHint(row, opt);
            if (opt && opt.value) {
                searchInput.value = opt.textContent.trim();
                const qtyInput = row.querySelector('.input-qty');
                if (qtyInput) {
                    let currentVal = parseInt(qtyInput.value) || 1;
                    const adjusted = checkRowStock(row, currentVal);
                    qtyInput.value = adjusted;
                }
            }
            dropdown.classList.add('d-none');
            highlightedIndex = -1;
        });
    }

    // Inicializar controles en filas existentes si las hubiera
    document.querySelectorAll('.item-row').forEach(row => {
        attachQuantityControls(row);
        attachProductSearch(row);
    });

    function addItemRow() {
        if (!template || !container) return;
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
    }

    if (addBtn) {
        addBtn.addEventListener('click', addItemRow);
    }
});
</script>
@endsection
