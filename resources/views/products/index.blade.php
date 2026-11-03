@extends('layouts.app')

@php
    $isRestaurant = $currentBusiness->isRestaurant();
@endphp

@section('title', $isRestaurant ? 'Menú / Productos' : 'Catálogo de Productos')

@section('content')
@if(session('import_result'))
    <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm p-4 mb-4 rounded-3" role="alert" style="background-color: #E6F4EA; border-left: 5px solid #137333 !important;">
        <div class="d-flex gap-3">
            <div class="text-success fs-3">
                <i class="bi bi-check-circle-fill"></i>
            </div>
            <div class="flex-grow-1">
                <h5 class="alert-heading fw-bold text-success-emphasis mb-1">Importación de Catálogo Finalizada</h5>
                <p class="mb-2 text-dark" style="font-size: 0.95rem;">
                    Se registraron con éxito <strong>{{ session('import_result')['created_count'] }}</strong> producto(s) nuevo(s)
                    y se actualizaron <strong>{{ session('import_result')['updated_count'] }}</strong> existente(s).
                </p>
                @if(session('import_result')['skipped_count'] > 0 || !empty(session('import_result')['errors']))
                    <div class="mt-3">
                        <a class="text-decoration-none fw-semibold text-success" data-bs-toggle="collapse" href="#importErrorsCollapse" role="button" aria-expanded="false" aria-controls="importErrorsCollapse" style="font-size: 0.875rem;">
                            <i class="bi bi-chevron-down me-1"></i> Ver detalles y advertencias ({{ session('import_result')['skipped_count'] }})
                        </a>
                        <div class="collapse mt-2" id="importErrorsCollapse">
                            <div class="card card-body bg-white border-0 p-3" style="max-height: 200px; overflow-y: auto;">
                                <ul class="list-unstyled mb-0" style="font-size: 0.85rem;">
                                    @foreach(session('import_result')['errors'] as $error)
                                        <li class="text-danger mb-1 d-flex gap-2">
                                            <i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i>
                                            <span>{{ $error }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div>
        <h3 class="fw-bold mb-1">
            {{ $isRestaurant ? 'Menú / Productos' : 'Catálogo de Productos' }}
        </h3>
        <p class="text-muted small mb-0">
            {{ $isRestaurant 
                ? 'Gestión de platos, bebidas y precios para la carta de ' . $currentBusiness->name . '.' 
                : 'Gestión de inventario y precios para ' . $currentBusiness->name . '.' }}
        </p>
    </div>
    @can('create', App\Models\Product::class)
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <button type="button"
                    class="btn btn-outline-primary btn-import-excel rounded-pill px-3 py-2 fw-semibold d-inline-flex align-items-center gap-2 shadow-sm"
                    data-bs-toggle="modal"
                    data-bs-target="#importProductsModal"
                    id="btnOpenImportModal">
                <i class="bi bi-file-earmark-spreadsheet fs-5"></i>
                <span>Importar Excel</span>
            </button>
            <a href="{{ route('products.create') }}" class="btn btn-primary rounded-pill px-4 fw-semibold">
                <i class="bi bi-plus-circle-fill me-1"></i> {{ $isRestaurant ? 'Nuevo Plato / Producto' : 'Registrar Producto' }}
            </a>
        </div>
    @endcan
</div>

@if ($isRestaurant)
    {{-- Pestañas de Navegación del Catálogo (Restaurante Híbrido) --}}
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <ul class="nav nav-pills gap-2 mb-0">
            <li class="nav-item">
                <a class="nav-link rounded-pill px-4 {{ ($activeTab ?? 'all') === 'menu' ? 'active shadow-sm' : 'bg-white text-secondary shadow-sm' }}" 
                   href="{{ route('products.index', array_merge(request()->except('page'), ['tab' => 'menu'])) }}">
                    <i class="bi bi-book-half me-1"></i> Carta y Menú de Cocina
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link rounded-pill px-4 {{ ($activeTab ?? 'all') === 'merchandise' ? 'active shadow-sm' : 'bg-white text-secondary shadow-sm' }}" 
                   href="{{ route('products.index', array_merge(request()->except('page'), ['tab' => 'merchandise'])) }}">
                    <i class="bi bi-boxes me-1"></i> Mercancía y Mostrador
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link rounded-pill px-4 {{ ($activeTab ?? 'all') === 'all' ? 'active shadow-sm' : 'bg-white text-secondary shadow-sm' }}" 
                   href="{{ route('products.index', array_merge(request()->except('page'), ['tab' => 'all'])) }}">
                    <i class="bi bi-grid-fill me-1"></i> Todo el Catálogo
                </a>
            </li>
        </ul>
        @if (($activeTab ?? 'all') === 'merchandise')
            <div>
                <a href="{{ route('sales.create') }}" class="btn btn-outline-primary btn-sm rounded-pill px-3 shadow-sm">
                    <i class="bi bi-cart-plus me-1"></i> Vender Mercancía
                </a>
            </div>
        @endif
    </div>
@endif

<!-- Filtros de Búsqueda -->
<div class="card card-custom p-3 bg-white mb-4">
    <form method="GET" action="{{ route('products.index') }}" class="row g-2 align-items-center">
        @if ($isRestaurant)
            <input type="hidden" name="tab" value="{{ $activeTab ?? 'all' }}">
        @endif

        <div class="{{ $isRestaurant ? 'col-md-5' : 'col-md-4' }}">
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0 text-muted"><i class="bi bi-search"></i></span>
                <input type="text" 
                       name="search" 
                       class="form-control border-start-0 ps-0" 
                       placeholder="{{ $isRestaurant ? 'Buscar plato, código, suplemento...' : 'Buscar por SKU, nombre...' }}" 
                       value="{{ $search }}">
            </div>
        </div>

        <div class="col-md-3">
            <select name="category_id" class="form-select">
                <option value="">Todas las categorías</option>
                @foreach ($categories as $cat)
                    <option value="{{ $cat->id }}" {{ $selectedCategory == $cat->id ? 'selected' : '' }}>
                        {{ $cat->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="{{ $isRestaurant ? 'col-md-2' : 'col-md-2' }}">
            <select name="status" class="form-select">
                <option value="">Todos los estados</option>
                <option value="1" {{ $selectedStatus === '1' ? 'selected' : '' }}>Activos</option>
                <option value="0" {{ $selectedStatus === '0' ? 'selected' : '' }}>Inactivos</option>
            </select>
        </div>

        <div class="{{ $isRestaurant ? 'col-md-1' : 'col-md-2' }}">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="low_stock" value="1" id="lowStockCheck" {{ $lowStockOnly ? 'checked' : '' }}>
                <label class="form-check-label small fw-semibold text-danger" for="lowStockCheck">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i> Stock
                </label>
            </div>
        </div>

        <div class="col-md-1 text-end">
            <button type="submit" class="btn btn-outline-primary rounded-pill w-100">Filtrar</button>
        </div>
    </form>
</div>

<!-- Tabla de Productos -->
<div class="card card-custom p-0 bg-white overflow-hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr class="small text-uppercase text-muted">
                    <th class="ps-4">{{ $isRestaurant ? 'Código / SKU' : 'SKU' }}</th>
                    <th>{{ $isRestaurant ? 'Plato / Mercancía' : 'Producto' }}</th>
                    @if ($isRestaurant)
                        <th>Tipo</th>
                    @endif
                    <th>Categoría</th>
                    <th>Precio Compra</th>
                    <th>Precio Venta</th>
                    <th>Stock</th>
                    <th>Estado</th>
                    <th class="text-end pe-4">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($products as $product)
                    @php
                        $isLowStock = $product->stock <= $product->min_stock;
                    @endphp
                    <tr>
                        <td class="ps-4">
                            <span class="badge bg-light text-dark font-monospace border px-2.5 py-1.5">
                                {{ $product->sku }}
                            </span>
                        </td>
                        <td>
                            <div class="fw-semibold text-dark">{{ $product->name }}</div>
                            @if ($product->description)
                                <div class="text-muted small text-truncate" style="max-width: 280px;">{{ $product->description }}</div>
                            @endif
                        </td>
                        @if ($isRestaurant)
                            <td>
                                @if ($product->isDish())
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill">
                                        <i class="bi bi-book-half me-1"></i>Plato Menú
                                    </span>
                                @else
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill">
                                        <i class="bi bi-boxes me-1"></i>Mercancía
                                    </span>
                                @endif
                            </td>
                        @endif
                        <td>
                            @if ($product->category)
                                <span class="badge bg-light text-dark border rounded-pill">
                                    {{ $product->category->name }}
                                </span>
                            @else
                                <span class="text-muted small">Sin categoría</span>
                            @endif
                        </td>
                        <td class="text-secondary small">
                            @if ($isRestaurant && $product->isDish())
                                <span class="text-muted">—</span>
                            @else
                                ${{ number_format($product->cost_price, 2) }}
                            @endif
                        </td>
                        <td class="fw-bold text-success">
                            ${{ number_format($product->sale_price, 2) }}
                        </td>
                        <td>
                            @if ($isRestaurant && $product->isDish())
                                <span class="badge bg-light text-muted border">
                                    <i class="bi bi-cup-hot me-1"></i>Cocina
                                </span>
                            @else
                                @if ($isLowStock)
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2.5 py-1 rounded-pill" title="Stock mínimo: {{ $product->min_stock }}">
                                        <i class="bi bi-exclamation-circle-fill me-1"></i> {{ $product->stock }} unid.
                                    </span>
                                @else
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2.5 py-1 rounded-pill">
                                        <i class="bi bi-check-circle-fill me-1"></i> {{ $product->stock }} unid.
                                    </span>
                                @endif
                            @endif
                        </td>
                        <td>
                            @if ($product->is_active)
                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1">
                                    Activo
                                </span>
                            @else
                                <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-2.5 py-1">
                                    Inactivo
                                </span>
                            @endif
                        </td>
                        <td class="text-end pe-4">
                            <div class="d-inline-flex gap-1">
                                <a href="{{ route('products.show', $product) }}" class="btn btn-light btn-sm rounded-pill px-2.5 text-secondary" title="Detalle">
                                    <i class="bi bi-eye"></i>
                                </a>

                                @can('update', $product)
                                    <a href="{{ route('products.edit', $product) }}" class="btn btn-outline-secondary btn-sm rounded-pill px-2.5" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                @endcan

                                @can('delete', $product)
                                    <button type="button"
                                            class="btn btn-outline-danger btn-sm rounded-pill px-2.5 btn-delete-product"
                                            data-bs-toggle="modal"
                                            data-bs-target="#deleteProductModal"
                                            data-action-url="{{ route('products.destroy', $product) }}"
                                            data-product-name="{{ $product->name }}"
                                            data-product-sku="{{ $product->sku }}"
                                            title="Eliminar">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ $isRestaurant ? '9' : '8' }}" class="text-center py-5 text-muted">
                            <i class="bi bi-box-seam display-4 d-block mb-2 text-secondary opacity-50"></i>
                            No se encontraron {{ $isRestaurant ? 'platos o productos en el menú' : 'productos' }} con los filtros seleccionados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($products->hasPages())
        <div class="p-3 border-top">
            {{ $products->links() }}
        </div>
    @endif
</div>

{{-- Modal Corporativo de Eliminación de Plato / Producto --}}
<div class="modal fade" id="deleteProductModal" tabindex="-1" aria-labelledby="deleteProductModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow rounded-4 overflow-hidden">
            <div class="modal-header bg-danger text-white py-3 px-4">
                <h5 class="modal-title fw-bold d-flex align-items-center" id="deleteProductModalLabel">
                    <i class="bi bi-trash3-fill me-2 fs-5"></i> Eliminar Plato / Producto
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <div class="text-danger mb-3">
                    <i class="bi bi-exclamation-triangle-fill display-4"></i>
                </div>
                <h5 class="fw-bold text-dark mb-2">¿Estás seguro de eliminar este producto?</h5>
                <div class="alert alert-light border rounded-3 p-3 text-start small mb-3">
                    <div class="mb-1">
                        <span class="text-muted">Nombre:</span>
                        <strong class="text-dark d-block text-truncate" id="deleteProductName">-</strong>
                    </div>
                    <div>
                        <span class="text-muted">Código / SKU:</span>
                        <strong class="text-dark" id="deleteProductSku">-</strong>
                    </div>
                </div>
                <p class="text-muted small mb-0">
                    Esta acción eliminará el registro del sistema y <strong>no se puede deshacer</strong>.
                </p>
            </div>
            <div class="modal-footer bg-light px-4 py-3 justify-content-end gap-2 border-0">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">
                    Cancelar
                </button>
                <form id="deleteProductForm" method="POST" action="">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger rounded-pill px-4 fw-bold">
                        <i class="bi bi-trash3 me-1"></i> Sí, Eliminar
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

@can('create', App\Models\Product::class)
{{-- Modal Corporativo de Importación Masiva Excel / CSV --}}
<div class="modal fade" id="importProductsModal" tabindex="-1" aria-labelledby="importProductsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header border-bottom-0 pb-0">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-file-earmark-excel-fill text-success fs-3"></i>
                    <h5 class="modal-title fw-bold" id="importProductsModalLabel">
                        {{ $isRestaurant ? 'Importar Menú y Productos desde Excel' : 'Importar Catálogo e Inventario desde Excel' }}
                    </h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            
            <form action="{{ route('products.import') }}" method="POST" enctype="multipart/form-data" id="formImportProducts">
                @csrf
                <div class="modal-body py-3">
                    {{-- Paso 1: Descargar Plantilla --}}
                    <div class="card border-0 bg-light rounded-3 p-3 mb-3">
                        <div class="d-flex align-items-start gap-2">
                            <span class="badge bg-primary rounded-circle p-2 px-2.5">1</span>
                            <div class="flex-grow-1">
                                <h6 class="fw-bold mb-1">Descargar Plantilla Exclusiva</h6>
                                <p class="text-muted small mb-2">
                                    @if($isRestaurant)
                                        Descarga la plantilla oficial para Restaurantes. Incluye la columna <strong>Modalidad</strong> para diferenciar platos de <strong>Carta / Cocina</strong> y artículos de <strong>Mercancía</strong>.
                                    @else
                                        Descarga la plantilla oficial para Comercio Retail con columnas listas para precios, costos, códigos SKU y existencias de inventario.
                                    @endif
                                </p>
                                <a href="{{ route('products.template.download', ['type' => $isRestaurant ? 'restaurant' : 'retail']) }}" 
                                   class="btn btn-outline-success btn-sm rounded-pill fw-semibold d-inline-flex align-items-center gap-2"
                                   data-no-disable="true">
                                    <i class="bi bi-download"></i>
                                    <span>Descargar Plantilla Oficial (.xlsx)</span>
                                </a>
                            </div>
                        </div>
                    </div>

                    {{-- Paso 2: Subir Archivo --}}
                    <div class="card border-0 bg-light rounded-3 p-3">
                        <div class="d-flex align-items-start gap-2">
                            <span class="badge bg-primary rounded-circle p-2 px-2.5">2</span>
                            <div class="flex-grow-1">
                                <h6 class="fw-bold mb-1">Subir Archivo Completado</h6>
                                <p class="text-muted small mb-2">Selecciona tu archivo de Excel (.xlsx, .xls) o archivo separado por comas (.csv).</p>
                                <input type="file" name="file" accept=".xlsx,.xls,.csv" class="form-control mb-3" required id="importFileInput">
                                
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" role="switch" name="update_existing" id="update_existing" value="1" checked>
                                    <label class="form-check-label small fw-semibold" for="update_existing">
                                        Actualizar precios y sumar/ajustar stock si el Código SKU o nombre ya existe en mi catálogo
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold" id="btnSubmitImport">
                        <i class="bi bi-cloud-upload me-1"></i> Procesar e Importar Catálogo
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endcan

<script>
document.addEventListener('DOMContentLoaded', function () {
    const deleteModal = document.getElementById('deleteProductModal');
    if (!deleteModal) return;

    deleteModal.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        if (!button) return;

        const actionUrl = button.getAttribute('data-action-url');
        const productName = button.getAttribute('data-product-name');
        const productSku = button.getAttribute('data-product-sku');

        const form = document.getElementById('deleteProductForm');
        const nameEl = document.getElementById('deleteProductName');
        const skuEl = document.getElementById('deleteProductSku');

        if (form) form.action = actionUrl;
        if (nameEl) nameEl.textContent = productName || '-';
        if (skuEl) skuEl.textContent = productSku || 'N/A';
    });
});
</script>
@endsection
