@props([
    'customers' => [],
    'selectedId' => null,
    'inputName' => 'customer_id',
    'inputId' => 'customer_id',
    'searchId' => 'customer_search_input',
    'selectId' => 'customer_select_dropdown',
    'resultsId' => 'customer_search_results',
    'clearBtnId' => 'btn_clear_customer',
    'badgeId' => 'selected_customer_badge',
    'badgeNameId' => 'selected_customer_name_label',
    'placeholder' => 'Escribe al menos 3 caracteres para buscar clientes registrados...',
    'selectPlaceholder' => '-- O selecciona directamente de la lista de clientes --',
    'withQuickButton' => true,
])

@php
    $selectedCustomer = null;
    $currentVal = old($inputName, $selectedId);
    if ($currentVal && count($customers ?? [])) {
        $selectedCustomer = collect($customers)->firstWhere('id', (int) $currentVal);
    }
@endphp

{{-- Buscador y Autocompletado de Cliente Frecuente --}}
<div class="mb-3 p-2 bg-white rounded-3 border customer-lookup-container">
    <div class="d-flex justify-content-between align-items-center mb-1">
        <label for="{{ $searchId }}" class="form-label small fw-bold text-primary mb-0">
            <i class="bi bi-search me-1"></i> Buscar Cliente Frecuente (Nombre, Documento o Teléfono)
        </label>
        <button type="button" class="btn btn-link btn-sm text-muted text-decoration-none py-0 px-1 {{ $selectedCustomer ? '' : 'd-none' }}" id="{{ $clearBtnId }}">
            <i class="bi bi-x-circle me-1"></i> Limpiar / Nuevo cliente manual
        </button>
    </div>
    <div class="position-relative">
        <div class="input-group input-group-sm">
            <span class="input-group-text bg-light text-muted border-end-0">
                <i class="bi bi-person-search"></i>
            </span>
            <input type="text" 
                   id="{{ $searchId }}" 
                   class="form-control border-start-0" 
                   value="{{ $selectedCustomer?->name ?? '' }}"
                   placeholder="{{ $placeholder }}" 
                   autocomplete="off">
        </div>
        <div id="{{ $resultsId }}" class="list-group position-absolute w-100 shadow-lg border rounded-3 overflow-auto d-none" style="max-height: 220px; z-index: 1060; top: 100%; left: 0; background: #fff;"></div>
    </div>

    <!-- Selector Desplegable Complementario -->
    <div class="mt-2 {{ $withQuickButton ? 'd-flex align-items-stretch gap-2' : '' }}">
        <select id="{{ $selectId }}" class="form-select border-secondary border-opacity-25 rounded-3 {{ $withQuickButton ? 'flex-grow-1' : '' }}">
            <option value="">{{ $selectPlaceholder }}</option>
            @foreach($customers ?? [] as $customer)
                <option value="{{ $customer->id }}"
                    data-name="{{ $customer->name }}"
                    data-document="{{ $customer->document ?? '' }}"
                    data-phone="{{ $customer->phone ?? '' }}"
                    data-address="{{ $customer->address ?? '' }}"
                    {{ (string) $currentVal === (string) $customer->id ? 'selected' : '' }}>
                    {{ $customer->name }} {{ $customer->phone ? '· ' . $customer->phone : '' }} {{ $customer->document ? '(' . $customer->document . ')' : '' }}
                </option>
            @endforeach
        </select>
        @if($withQuickButton)
            <button type="button"
                    class="btn btn-outline-primary bg-white text-primary d-inline-flex align-items-center justify-content-center px-3 flex-shrink-0 btn-open-quick-customer"
                    id="btnOpenQuickCustomer"
                    data-bs-toggle="modal"
                    data-bs-target="#quickCustomerModal"
                    title="Crear Cliente Rápido"
                    aria-label="Crear Cliente Rápido"
                    style="min-width: 44px; min-height: 42px; border-radius: 0.5rem;">
                <i class="bi bi-person-plus fs-5" style="line-height: 1;"></i>
            </button>
        @endif
    </div>

    <div id="{{ $badgeId }}" class="mt-2 {{ $selectedCustomer ? '' : 'd-none' }}">
        <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle px-2 py-1">
            <i class="bi bi-check-circle-fill me-1"></i> Cliente vinculado: <span id="{{ $badgeNameId }}" class="fw-bold">{{ $selectedCustomer?->name ?? '' }}</span>
        </span>
    </div>
</div>
