@extends('layouts.app')

@section('title', 'Asistente de Consultas IA')

@section('content')
<style>
    @media (max-width: 767.98px) {
        .chat-conversation-card {
            min-height: 54vh;
            max-height: 60vh;
            display: flex;
            flex-direction: column;
            border-radius: 1rem !important;
            border: 1px solid #e2e8f0 !important;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05) !important;
            overflow: hidden;
        }
        .chat-messages-body {
            overflow-y: auto;
            flex: 1 1 auto;
            background-color: #F8FAFC !important;
            padding: 0.75rem 0.65rem !important;
            border-top: 1px solid #f1f5f9;
            border-bottom: 1px solid #f1f5f9;
        }
        .chat-header-compact {
            padding: 0.5rem 0.75rem !important;
            background-color: #ffffff !important;
        }
        .chat-footer-compact {
            padding: 0.5rem !important;
            background-color: #ffffff !important;
        }
        .ai-avatar {
            width: 28px !important;
            height: 28px !important;
            min-width: 28px !important;
            font-size: 0.8rem !important;
        }
        .ai-avatar i {
            font-size: 0.8rem !important;
        }
        .ai-message-bubble {
            max-width: 92% !important;
            padding: 0.65rem 0.85rem !important;
            border-radius: 1rem !important;
        }
        .user-message-bubble {
            max-width: 90% !important;
            padding: 0.65rem 0.85rem !important;
            border-radius: 1rem !important;
        }
        .chat-input-capsule {
            background-color: #f1f5f9 !important;
            border-radius: 2rem !important;
            padding: 0.2rem 0.35rem 0.2rem 0.75rem !important;
            border: 1px solid #e2e8f0 !important;
        }
        .chat-input-capsule input {
            border: none !important;
            background: transparent !important;
            box-shadow: none !important;
            font-size: 0.9rem !important;
            padding-left: 0.25rem !important;
        }
        .chat-input-capsule input:focus {
            box-shadow: none !important;
        }
        .chat-send-btn-compact {
            width: 44px !important;
            height: 44px !important;
            min-width: 44px !important;
            min-height: 44px !important;
            padding: 0 !important;
            border-radius: 50% !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
        }
        .chat-header-title {
            font-size: 1.1rem !important;
            margin-bottom: 0.2rem !important;
        }
        .chat-header-badge {
            font-size: 0.72rem !important;
            padding: 0.25rem 0.5rem !important;
        }
    }
    @media (min-width: 768px) {
        .chat-conversation-card {
            min-height: 520px;
            height: 600px;
        }
        .chat-messages-body {
            overflow-y: auto;
            flex-grow: 1;
        }
        .ai-avatar {
            width: 38px;
            height: 38px;
            min-width: 38px;
        }
        .ai-message-bubble {
            max-width: 85%;
        }
        .user-message-bubble {
            max-width: 80%;
        }
    }
    .chat-messages-body {
        overscroll-behavior-y: contain;
        scroll-behavior: smooth;
    }
    .scrollbar-thin {
        scrollbar-width: thin;
        -webkit-overflow-scrolling: touch;
    }
    .scrollbar-thin::-webkit-scrollbar {
        height: 4px;
    }
    .scrollbar-thin::-webkit-scrollbar-thumb {
        background-color: rgba(0, 0, 0, 0.2);
        border-radius: 4px;
    }
    .quick-prompt-btn {
        font-size: 0.85rem;
        background-color: #ffffff;
        border-color: #dee2e6;
        color: #495057;
        transition: all 0.2s ease-in-out;
    }
    .quick-prompt-btn:hover, .quick-prompt-btn:active {
        background-color: #f8f9fa;
        border-color: #0d6efd;
        color: #0d6efd;
    }
</style>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center py-1 py-md-3 mb-2 mb-md-3 gap-2">
    <div>
        <h3 class="fw-bold mb-1 fs-6 fs-md-3 chat-header-title">
            <i class="bi bi-stars text-primary me-1"></i> Asistente de Consultas
        </h3>
        <p class="text-muted small mb-0 d-none d-md-block">
            Pregunta en lenguaje natural sobre ventas, inventario, clientes y catálogo de <strong>{{ $currentBusiness->name }}</strong>.
        </p>
    </div>
    <div class="d-flex flex-wrap gap-1 gap-md-2 align-items-center">
        <span class="badge bg-success-subtle text-success rounded-pill px-2 px-md-3 py-1 py-md-2 chat-header-badge">
            <i class="bi bi-shield-check me-1"></i> Solo Lectura
        </span>
        <span class="badge bg-primary-subtle text-primary rounded-pill px-2 px-md-3 py-1 py-md-2 chat-header-badge">
            <i class="bi bi-building-check me-1"></i> Tenant Aislado
        </span>
        @if(!$enabled)
            <span class="badge bg-warning-subtle text-dark rounded-pill px-2 px-md-3 py-1 py-md-2 chat-header-badge">
                <i class="bi bi-power me-1"></i> Desactivado en .env
            </span>
        @endif
    </div>
</div>

@if(!$enabled)
    <div class="alert alert-warning border-0 shadow-sm rounded-4 p-4 mb-4">
        <div class="d-flex align-items-start gap-3">
            <i class="bi bi-exclamation-triangle-fill fs-2 text-warning"></i>
            <div>
                <h5 class="fw-bold mb-1">Módulo de IA Desactivado</h5>
                <p class="mb-2 text-muted">
                    El asistente se encuentra desactivado mediante la variable <code>AI_MODULE_ENABLED=false</code> en la configuración.
                    Tal como establece la arquitectura del proyecto, <strong>el núcleo del sistema funciona al 100% de manera independiente</strong>.
                </p>
                <small class="text-secondary">
                    Para activarlo, define <code>AI_MODULE_ENABLED=true</code> y proporciona una <code>GEMINI_API_KEY</code> en tu archivo <code>.env</code>.
                </small>
            </div>
        </div>
    </div>
@endif

<div class="row g-4">
    <div class="col-lg-8">
        {{-- Contenedor del Chat --}}
        <div class="card card-custom bg-white border-0 shadow-sm d-flex flex-column chat-conversation-card">
            <div class="card-header bg-transparent border-0 pt-3 px-4 d-flex justify-content-between align-items-center chat-header-compact">
                <span class="small fw-semibold text-muted text-uppercase">Conversación</span>
                <button type="button" class="btn btn-sm btn-link text-secondary text-decoration-none" id="clearChatBtn">
                    <i class="bi bi-trash me-1"></i> Limpiar
                </button>
            </div>

            <div class="card-body px-4 chat-messages-body" id="chatMessages">
                {{-- Mensaje Inicial de Bienvenida --}}
                <div class="d-flex gap-2 gap-md-3 mb-3 mb-md-4">
                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 ai-avatar">
                        <i class="bi bi-stars fs-5"></i>
                    </div>
                    <div class="bg-light p-3 rounded-4 shadow-sm ai-message-bubble">
                        <p class="mb-1 text-dark">
                            ¡Hola! Soy tu asistente de consultas de <strong>{{ $currentBusiness->name }}</strong>.
                        </p>
                        <p class="mb-0 small text-muted">
                            Puedes preguntarme sobre productos agotados, existencias críticas, volumen de ventas de hoy o del mes, clientes registrados y más.
                        </p>
                    </div>
                </div>
            </div>

            {{-- Formulario de Entrada Inmediata --}}
            <div class="card-footer bg-white border-0 p-2 p-md-3 chat-footer-compact">
                {{-- Botonera de Consultas Rápidas (Quick Chips) en Móvil y Escritorio --}}
                <div class="quick-chips-wrapper mb-2 px-1">
                    <div class="d-flex flex-nowrap gap-2 overflow-x-auto pb-1" style="scrollbar-width: none; -ms-overflow-style: none;">
                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill text-nowrap px-3 py-1 quick-chip-btn flex-shrink-0 d-inline-flex align-items-center" data-query="Ventas de hoy vs ayer" style="font-size: 0.85rem; min-height: 38px;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1 flex-shrink-0" style="pointer-events: none;"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"></polyline><polyline points="16 7 22 7 22 13"></polyline></svg>
                            <span>Ventas de hoy vs ayer</span>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill text-nowrap px-3 py-1 quick-chip-btn flex-shrink-0 d-inline-flex align-items-center" data-query="¿Cuánto vendimos en el turno de la tarde comparado con la semana pasada?" style="font-size: 0.85rem; min-height: 38px;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1 flex-shrink-0" style="pointer-events: none;"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                            <span>Turno tarde vs semana pasada</span>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill text-nowrap px-3 py-1 quick-chip-btn flex-shrink-0 d-inline-flex align-items-center" data-query="{{ $business && $business->isRestaurant() ? '¿Cuáles son los 3 platos con menor margen de ganancia?' : '¿Cuáles son los 3 productos con menor margen de ganancia?' }}" style="font-size: 0.85rem; min-height: 38px;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1 flex-shrink-0" style="pointer-events: none;"><rect width="16" height="20" x="4" y="2" rx="2"></rect><line x1="8" x2="16" y1="6" y2="6"></line><line x1="16" x2="16" y1="14" y2="18"></line><path d="M16 10h.01"></path><path d="M12 10h.01"></path><path d="M8 10h.01"></path><path d="M12 14h.01"></path><path d="M8 14h.01"></path><path d="M12 18h.01"></path><path d="M8 18h.01"></path></svg>
                            <span>{{ $business && $business->isRestaurant() ? '3 platos con menor margen' : '3 productos con menor margen' }}</span>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill text-nowrap px-3 py-1 quick-chip-btn flex-shrink-0 d-inline-flex align-items-center" data-query="¿Qué productos tienen bajo stock o requieren reposición?" style="font-size: 0.85rem; min-height: 38px;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1 flex-shrink-0" style="pointer-events: none;"><path d="m7.5 4.27 9 5.15"></path><path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"></path><path d="m3.3 7 8.7 5 8.7-5"></path><path d="M12 22V12"></path></svg>
                            <span>Stock crítico para proveedor</span>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill text-nowrap px-3 py-1 quick-chip-btn flex-shrink-0 d-inline-flex align-items-center" data-query="¿Cuáles son los 5 productos más vendidos?" style="font-size: 0.85rem; min-height: 38px;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="me-1 flex-shrink-0" style="pointer-events: none;"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"></path><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"></path><path d="M4 22h16"></path><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"></path><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"></path><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"></path></svg>
                            <span>Top 5 más vendidos</span>
                        </button>
                    </div>
                </div>

                <div class="chat-input-wrapper">
                    <form id="queryForm" class="d-flex gap-2 align-items-center chat-input-capsule">
                        @csrf
                        <input type="text" id="userInput" class="form-control rounded-pill px-3 px-md-4 py-2 shadow-sm"
                               placeholder="Escribe tu consulta aquí..."
                               autocomplete="off" maxlength="500"
                               style="min-height: 44px;">
                        <button type="submit" class="btn btn-primary rounded-pill px-3 px-md-4 fw-semibold d-inline-flex align-items-center justify-content-center gap-2 shadow-sm flex-shrink-0 chat-send-btn-compact" 
                                id="sendBtn"
                                style="min-height: 44px;"
                                aria-label="Enviar consulta"
                                title="Enviar consulta">
                            <span class="d-none d-md-inline">Enviar</span>
                            <i class="bi bi-send-fill"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- Preguntas Frecuentes en Móvil (Chips Horizontales con Desplazamiento) --}}
        <div class="quick-prompts-section my-3 d-block d-lg-none">
            <div class="d-flex align-items-center gap-1 text-muted small fw-bold text-uppercase mb-2">
                <i class="bi bi-lightbulb-fill text-warning"></i>
                <span>Preguntas Frecuentes</span>
            </div>
            <div class="d-flex flex-nowrap gap-2 overflow-x-auto pb-2 scrollbar-thin">
                @foreach ($suggestions as $suggestion)
                    <button type="button" 
                            class="btn btn-sm btn-outline-secondary rounded-pill text-nowrap px-3 py-2 suggestion-btn quick-prompt-btn flex-shrink-0" 
                            data-query="{{ $suggestion }}"
                            style="min-height: 44px;">
                        <i class="bi bi-arrow-return-right me-1 text-primary"></i>{{ $suggestion }}
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Garantías de Seguridad en Móvil (Colapsable) --}}
        <div class="mobile-security-section mb-3 d-block d-lg-none">
            <div class="card bg-light border-0 rounded-3 p-2 px-3">
                <a class="small text-muted text-decoration-none fw-semibold d-inline-flex align-items-center justify-content-between w-100 py-1" 
                   data-bs-toggle="collapse" 
                   href="#securityDetails" 
                   role="button" 
                   aria-expanded="false" 
                   aria-controls="securityDetails"
                   style="min-height: 44px;">
                    <span class="d-inline-flex align-items-center">
                        <i class="bi bi-shield-check text-success me-2 fs-6"></i>
                        <span>Ver Garantías de Seguridad</span>
                    </span>
                    <i class="bi bi-chevron-down text-muted small"></i>
                </a>
                <div class="collapse pt-2" id="securityDetails">
                    <ul class="list-unstyled small text-muted mb-0 d-flex flex-column gap-2 pt-2 border-top">
                        <li><i class="bi bi-check-circle-fill text-success me-1"></i> <strong>Solo Lectura:</strong> Imposible alterar o borrar datos por error.</li>
                        <li><i class="bi bi-check-circle-fill text-success me-1"></i> <strong>Aislamiento Estricto:</strong> Solo accede a datos de {{ $currentBusiness->name }}.</li>
                        <li><i class="bi bi-check-circle-fill text-success me-1"></i> <strong>Sin SQL arbitrario:</strong> Intenciones verificadas contra una whitelist fija.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4 d-none d-lg-block">
        {{-- Sugerencias Rápidas en Desktop --}}
        <div class="card card-custom bg-white border-0 shadow-sm p-4 mb-4">
            <h6 class="fw-bold mb-3 text-dark">
                <i class="bi bi-lightbulb text-warning me-1"></i> Preguntas Frecuentes
            </h6>
            <div class="d-flex flex-column gap-2" id="suggestionChips">
                @foreach ($suggestions as $suggestion)
                    <button type="button" class="btn btn-sm btn-outline-secondary text-start rounded-pill py-2 px-3 suggestion-btn text-truncate" data-query="{{ $suggestion }}" title="{{ $suggestion }}" style="min-height: 40px;">
                        <i class="bi bi-arrow-return-right me-1 text-primary"></i> {{ $suggestion }}
                    </button>
                @endforeach
            </div>
        </div>

        {{-- Reglas de Seguridad Visibles al Usuario en Desktop --}}
        <div class="card card-custom bg-light border-0 p-3">
            <h6 class="fw-bold small text-muted text-uppercase mb-2">Garantías de Seguridad</h6>
            <ul class="list-unstyled small text-muted mb-0 d-flex flex-column gap-2">
                <li><i class="bi bi-check-circle-fill text-success me-1"></i> <strong>Solo Lectura:</strong> Imposible alterar o borrar datos por error.</li>
                <li><i class="bi bi-check-circle-fill text-success me-1"></i> <strong>Aislamiento Estricto:</strong> Solo accede a datos de {{ $currentBusiness->name }}.</li>
                <li><i class="bi bi-check-circle-fill text-success me-1"></i> <strong>Sin SQL arbitrario:</strong> Intenciones verificadas contra una whitelist fija.</li>
            </ul>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('queryForm');
    const input = document.getElementById('userInput');
    const sendBtn = document.getElementById('sendBtn');
    const chatContainer = document.getElementById('chatMessages');
    const clearBtn = document.getElementById('clearChatBtn');
    const suggestionBtns = document.querySelectorAll('.suggestion-btn');

    const scrollToBottom = () => {
        chatContainer.scrollTop = chatContainer.scrollHeight;
    };

    const appendUserMessage = (text) => {
        const wrapper = document.createElement('div');
        wrapper.className = 'd-flex justify-content-end mb-3';
        wrapper.innerHTML = `
            <div class="bg-primary text-white p-3 rounded-4 shadow-sm user-message-bubble">
                <p class="mb-0">${escapeHtml(text)}</p>
            </div>
        `;
        chatContainer.appendChild(wrapper);
        scrollToBottom();
    };

    const columnLabels = {
        'name': 'NOMBRE',
        'sku': 'CÓDIGO / SKU',
        'category': 'CATEGORÍA',
        'stock': 'STOCK',
        'sale_price': 'PRECIO VENTA',
        'price': 'PRECIO VENTA',
        'document': 'DOCUMENTO / NIT',
        'identification_number': 'DOCUMENTO / NIT',
        'document_number': 'DOCUMENTO / NIT',
        'phone': 'TELÉFONO',
        'email': 'CORREO',
        'total': 'TOTAL',
        'status': 'ESTADO',
        'invoice': 'N° FACTURA',
        'invoice_number': 'N° FACTURA',
        'date': 'FECHA',
        'customer': 'CLIENTE',
        'customer_name': 'CLIENTE',
        'payment_method': 'MÉTODO DE PAGO',
        'min_stock': 'STOCK MÍNIMO',
        'deficit': 'DÉFICIT / REPOSICIÓN',
        'order_number': 'N° COMANDA / PEDIDO',
        'type': 'TIPO',
        'table': 'MESA',
        'capacity': 'CAPACIDAD',
        'opened_at': 'HORA APERTURA',
        'address': 'DIRECCIÓN',
        'quantity_sold': 'CANTIDAD VENDIDA',
        'revenue_generated': 'TOTAL RECAUDADO',
        'description': 'DESCRIPCIÓN',
        'supplier': 'PROVEEDOR',
        'contact_name': 'CONTACTO',
        'city': 'CIUDAD',
        'period': 'PERÍODO',
        'sales_count': 'VENTAS REGISTRADAS',
        'total_revenue': 'TOTAL INGRESOS',
        'average_ticket': 'TICKET PROMEDIO',
        'total_products': 'TOTAL PRODUCTOS',
        'total_units': 'TOTAL UNIDADES',
        'total_cost_valuation': 'VALOR AL COSTO',
        'total_retail_valuation': 'VALOR COMERCIAL',
        'potential_profit': 'UTILIDAD PROYECTADA',
        'potential_margin': 'MARGEN POTENCIAL',
        'total_tips': 'PROPINAS RECAUDADAS',
        'orders_with_tip': 'PEDIDOS CON PROPINA',
        'cost': 'COSTO',
        'cost_price': 'COSTO',
        'unit_margin': 'MARGEN ($)',
        'margin_amount': 'MARGEN ($)',
        'margin_percent': 'MARGEN (%)',
        'difference': 'DIFERENCIA ($)',
        'variation': 'VARIACIÓN (%)',
        'difference_revenue': 'DIFERENCIA ($)',
        'difference_count': 'DIFERENCIA (VENTAS)',
        'percentage_variation': 'VARIACIÓN (%)',
    };

    const getColumnLabel = (key) => {
        return columnLabels[key.toLowerCase()] || key.replace(/_/g, ' ').toUpperCase();
    };

    const appendAssistantMessage = (summary, data = []) => {
        const wrapper = document.createElement('div');
        wrapper.className = 'd-flex gap-2 gap-md-3 mb-3 mb-md-4';

        let dataHtml = '';
        if (Array.isArray(data) && data.length > 0) {
            const firstItem = data[0];
            const keys = Object.keys(firstItem).filter(k => k !== 'id');

            dataHtml = `
                <div class="table-responsive mt-2 bg-white rounded-3 border">
                    <table class="table table-sm table-hover mb-0 small">
                        <thead class="table-light">
                            <tr>
                                ${keys.map(k => `<th>${escapeHtml(getColumnLabel(k))}</th>`).join('')}
                            </tr>
                        </thead>
                        <tbody>
                            ${data.map(row => `
                                <tr>
                                    ${keys.map(k => `<td>${escapeHtml(String(row[k] ?? ''))}</td>`).join('')}
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>
            `;
        }

        wrapper.innerHTML = `
            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 ai-avatar">
                <i class="bi bi-stars fs-5"></i>
            </div>
            <div class="bg-light p-3 rounded-4 shadow-sm ai-message-bubble">
                <p class="mb-1 text-dark">${escapeHtml(summary)}</p>
                ${dataHtml}
            </div>
        `;
        chatContainer.appendChild(wrapper);
        scrollToBottom();
    };

    const appendLoading = () => {
        const loadingDiv = document.createElement('div');
        loadingDiv.id = 'chatLoading';
        loadingDiv.className = 'd-flex gap-2 gap-md-3 mb-3';
        loadingDiv.innerHTML = `
            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 ai-avatar">
                <div class="spinner-border spinner-border-sm text-light" role="status"></div>
            </div>
            <div class="bg-light p-3 rounded-4 text-muted small ai-message-bubble">
                Analizando tu consulta con seguridad...
            </div>
        `;
        chatContainer.appendChild(loadingDiv);
        scrollToBottom();
    };

    const removeLoading = () => {
        const loading = document.getElementById('chatLoading');
        if (loading) loading.remove();
    };

    const escapeHtml = (str) => {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    };

    const sendQuery = async (queryText) => {
        if (!queryText.trim()) return;

        appendUserMessage(queryText);
        input.value = '';
        input.disabled = true;
        sendBtn.disabled = true;
        appendLoading();

        try {
            const response = await fetch("{{ route('ai.ask') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ query: queryText })
            });

            removeLoading();

            if (!response.ok) {
                appendAssistantMessage("El asistente de consultas no está disponible temporalmente. Puedes utilizar los módulos tradicionales de clientes, ventas e inventario.");
                return;
            }

            const resData = await response.json();
            appendAssistantMessage(resData.summary, resData.data);
        } catch (error) {
            removeLoading();
            appendAssistantMessage("El asistente de consultas no está disponible temporalmente. Puedes utilizar los módulos tradicionales de clientes, ventas e inventario.");
        } finally {
            input.disabled = false;
            sendBtn.disabled = false;
            input.focus();
        }
    };

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        sendQuery(input.value);
    });

    suggestionBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const text = btn.getAttribute('data-query') || btn.innerText.trim();
            input.value = text;
            input.focus();
            input.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });
    });

    document.querySelectorAll('.quick-chip-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const text = btn.getAttribute('data-query') || btn.innerText.trim();
            input.value = text;
            sendQuery(text);
        });
    });

    clearBtn.addEventListener('click', async () => {
        try {
            await fetch("{{ route('ai.clear-context') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                }
            });
        } catch (err) {
            console.error('Error al reiniciar contexto:', err);
        }

        chatContainer.innerHTML = `
            <div class="d-flex gap-3 mb-4">
                <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px;">
                    <i class="bi bi-stars fs-5"></i>
                </div>
                <div class="bg-light p-3 rounded-4 shadow-sm" style="max-width: 85%;">
                    <p class="mb-0 small text-muted">Historial y contexto reiniciados. Formula tu pregunta cuando desees.</p>
                </div>
            </div>
        `;
    });
});
</script>
@endpush
