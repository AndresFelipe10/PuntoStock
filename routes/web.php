<?php

use App\Http\Controllers\AiAssistantController;
use App\Http\Controllers\Api\ProductSearchController;
use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\InventoryAlertController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\Restaurant\RestaurantOrderController;
use App\Http\Controllers\Restaurant\RestaurantTableController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\Superadmin\BusinessController as SuperadminBusinessController;
use App\Http\Controllers\Superadmin\DashboardController as SuperadminDashboardController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return auth()->user()->is_superadmin
            ? redirect()->route('superadmin.dashboard')
            : redirect()->route('dashboard');
    }

    return redirect()->route('login');
});

// Rutas públicas legales y normativas (Redirección canónica 301 hacia dominio oficial puntostockpos.com)
Route::get('/terminos-y-condiciones', [LegalController::class, 'terms'])->name('legal.terms');
Route::get('/politica-de-privacidad', [LegalController::class, 'privacy'])->name('legal.privacy');

// Rutas de invitados (no autenticados)
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);

    Route::get('/forgot-password', [PasswordResetController::class, 'createForgot'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])->middleware('throttle:10,1')->name('password.email');

    Route::get('/reset-password/{token}', [PasswordResetController::class, 'createReset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'updatePassword'])->middleware('throttle:10,1')->name('password.update');
});

// Cierre de sesión seguro y accesible (GET y POST, sin CSRF forzado)
Route::match(['get', 'post'], '/logout', [LoginController::class, 'destroy'])->name('logout');

// Rutas protegidas por autenticación
Route::middleware('auth')->group(function () {
    // Cambio obligatorio de contraseña
    Route::get('/password/change', [ChangePasswordController::class, 'show'])->name('password.change');
    Route::post('/password/change', [ChangePasswordController::class, 'update'])->name('password.change.update');

    // Módulo Superadministrador de Plataforma
    Route::middleware('superadmin')->prefix('superadmin')->name('superadmin.')->group(function () {
        Route::get('/', [SuperadminDashboardController::class, 'index'])->name('dashboard');

        Route::get('/businesses', [SuperadminBusinessController::class, 'index'])->name('businesses.index');
        Route::get('/businesses/create', [SuperadminBusinessController::class, 'create'])->name('businesses.create');
        Route::post('/businesses', [SuperadminBusinessController::class, 'store'])->name('businesses.store');
        Route::get('/businesses/{business}/edit', [SuperadminBusinessController::class, 'edit'])->name('businesses.edit');
        Route::put('/businesses/{business}', [SuperadminBusinessController::class, 'update'])->name('businesses.update');
        Route::post('/businesses/{business}/toggle-status', [SuperadminBusinessController::class, 'toggleStatus'])->name('businesses.toggleStatus');
        Route::post('/businesses/{business}/reset-password', [SuperadminBusinessController::class, 'resetAdminPassword'])->name('businesses.resetPassword');
        Route::post('/businesses/{business}/renew-subscription', [SuperadminBusinessController::class, 'renewSubscription'])->name('businesses.renewSubscription');
        Route::get('/businesses/{business}/users', [SuperadminBusinessController::class, 'users'])->name('businesses.users');
        Route::get('/businesses/{business}/users/create', [SuperadminBusinessController::class, 'createUser'])->name('businesses.users.create');
        Route::post('/businesses/{business}/users', [SuperadminBusinessController::class, 'storeUser'])->name('businesses.users.store');
        Route::post('/businesses/{business}/users/{user}/toggle-status', [SuperadminBusinessController::class, 'toggleUserStatus'])->name('businesses.toggleUserStatus');
    });

    // Rutas que requieren tenant activo (negocio)
    Route::middleware('tenant')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('/api/dashboard', [DashboardController::class, 'api'])->name('api.dashboard');

        // Catálogo (Lectura para Administrador y Empleado)
        Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::get('/products/template/download', [ProductController::class, 'downloadTemplate'])->name('products.template.download');
        Route::post('/products/import', [ProductController::class, 'import'])->name('products.import');
        Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');

        // Módulo Restaurante: Salón, Mesas y Comandas (Solo comercios tipo Restaurante)
        Route::middleware('restaurant')->prefix('restaurant')->name('restaurant.')->group(function () {
            // Salón y Mesas (Lectura para Administrador y Empleado)
            Route::get('/tables', [RestaurantTableController::class, 'index'])->name('tables.index');

            // Mesas (Escritura y Eliminación exclusiva Administrador)
            Route::middleware('role:admin')->group(function () {
                Route::get('/tables/create', [RestaurantTableController::class, 'create'])->name('tables.create');
                Route::post('/tables', [RestaurantTableController::class, 'store'])->name('tables.store');
                Route::get('/tables/{table}/edit', [RestaurantTableController::class, 'edit'])->name('tables.edit');
                Route::put('/tables/{table}', [RestaurantTableController::class, 'update'])->name('tables.update');
                Route::delete('/tables/{table}', [RestaurantTableController::class, 'destroy'])->name('tables.destroy');

                // Anulación de comandas y eliminación de ítems (Exclusivo Administrador)
                Route::delete('/orders/{order}/items/{item}', [RestaurantOrderController::class, 'removeItem'])->name('orders.items.destroy');
                Route::post('/orders/{order}/cancel', [RestaurantOrderController::class, 'cancel'])->name('orders.cancel');
            });

            // Comandas (Lectura, Apertura y Adición para Administrador y Empleado)
            Route::get('/orders', [RestaurantOrderController::class, 'index'])->name('orders.index');
            Route::get('/orders/create', [RestaurantOrderController::class, 'create'])->name('orders.create');
            Route::post('/orders', [RestaurantOrderController::class, 'store'])->name('orders.store');
            Route::get('/orders/{order}', [RestaurantOrderController::class, 'show'])->name('orders.show');
            Route::post('/orders/{order}/items', [RestaurantOrderController::class, 'addItems'])->name('orders.items.store');
            Route::match(['get', 'post'], '/orders/{order}/kitchen-ticket', [RestaurantOrderController::class, 'kitchenTicket'])->name('orders.kitchen-ticket');
            Route::match(['get', 'post'], '/orders/{order}/pre-bill', [RestaurantOrderController::class, 'preBill'])->name('orders.prebill');
            Route::match(['get', 'post'], '/orders/{order}/print-precount', [RestaurantOrderController::class, 'preBill'])->name('orders.print-precount');
            Route::post('/orders/{order}/cancel-empty', [RestaurantOrderController::class, 'cancelEmpty'])->name('orders.cancel-empty');
            Route::post('/orders/{order}/change-table', [RestaurantOrderController::class, 'changeTable'])->name('orders.change-table');

            // Cobro y Facturación de Comanda (Cierre estándar o de Saldo Pendiente)
            Route::post('/orders/{order}/charge', [RestaurantOrderController::class, 'charge'])->name('orders.charge');
            Route::post('/orders/{order}/bill', [RestaurantOrderController::class, 'bill'])->name('orders.bill');

            // División de Cuenta por Platos (Split Bill)
            Route::post('/orders/{order}/split-payment', [RestaurantOrderController::class, 'splitPayment'])->name('orders.split-payment');
            Route::get('/orders/{order}/print-split-precount', [RestaurantOrderController::class, 'printSplitPrecount'])->name('orders.print-split-precount');
            Route::get('/orders/{order}/print-split-ticket/{payment}', [RestaurantOrderController::class, 'printSplitTicket'])->name('orders.print-split-ticket');

            // Domicilios y Despacho
            Route::get('/deliveries', [RestaurantOrderController::class, 'deliveries'])->name('orders.deliveries');
            Route::get('/orders-delivery/create', [RestaurantOrderController::class, 'createDelivery'])->name('orders.create-delivery');
            Route::post('/orders-delivery', [RestaurantOrderController::class, 'storeDelivery'])->name('orders.store-delivery');
            Route::post('/deliveries/{order}/pickup', [RestaurantOrderController::class, 'pickup'])->name('deliveries.pickup');
            Route::post('/orders/{order}/status', [RestaurantOrderController::class, 'updateStatus'])->name('orders.status.update');
            Route::get('/orders/{order}/dispatch-ticket', [RestaurantOrderController::class, 'dispatchTicket'])->name('orders.dispatch-ticket');
        });

        // Inventario (Lectura y Registro de Movimientos para Administrador y Empleado)
        Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::get('/inventory/alerts', [InventoryAlertController::class, 'index'])->name('inventory.alerts');
        Route::get('/inventory/create', [InventoryController::class, 'create'])->name('inventory.create');
        Route::post('/inventory', [InventoryController::class, 'store'])->name('inventory.store');

        // Directorio de Clientes (Lectura y creación para Administrador y Empleado)
        Route::get('/customers/search', [CustomerController::class, 'search'])->middleware('throttle:60,1')->name('customers.search');
        Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('/customers/create', [CustomerController::class, 'create'])->name('customers.create');
        Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
        Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');

        // Directorio de Proveedores (Lectura para Administrador y Empleado)
        Route::get('/suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
        Route::get('/suppliers/{supplier}', [SupplierController::class, 'show'])->name('suppliers.show');

        // Ventas (Lectura y Registro para Administrador y Empleado)
        Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
        Route::get('/sales/create', [SaleController::class, 'create'])->name('sales.create');
        Route::post('/sales', [SaleController::class, 'store'])->name('sales.store');
        Route::get('/sales/{sale}', [SaleController::class, 'show'])->name('sales.show');
        Route::get('/sales/{sale}/print/invoice', [SaleController::class, 'printInvoice'])->name('sales.print.invoice');
        Route::get('/sales/{sale}/print/receipt', [SaleController::class, 'printReceipt'])->name('sales.print.receipt');

        // Cuadre de Caja (Lectura para Administrador y Empleado)
        Route::get('/cash-register', [ReportController::class, 'cashRegister'])->name('reports.cash-register');

        // API interna para búsqueda de productos (POS autocomplete)
        Route::get('/api/products/search', ProductSearchController::class)->middleware('throttle:60,1')->name('api.products.search');

        // Asistente de Consultas en Lenguaje Natural (IA)
        Route::redirect('/ai', '/ai/assistant');
        Route::get('/ai/assistant', [AiAssistantController::class, 'index'])->name('ai.index');
        Route::post('/ai/ask', [AiAssistantController::class, 'ask'])->middleware('throttle:30,1')->name('ai.ask');
        Route::post('/ai/clear-context', [AiAssistantController::class, 'clearContext'])->name('ai.clear-context');

        // Rutas exclusivas para el rol Administrador del emprendimiento
        Route::middleware('role:admin')->group(function () {
            // Mi Negocio (Edición exclusiva de su propio negocio)
            Route::get('/my-business', [BusinessController::class, 'edit'])->name('businesses.edit');
            Route::put('/my-business', [BusinessController::class, 'update'])->name('businesses.update');

            // Gestión de Colaboradores
            Route::get('/users', [UserController::class, 'index'])->name('users.index');
            Route::get('/users/invite', [UserController::class, 'create'])->name('users.create');
            Route::post('/users', [UserController::class, 'store'])->name('users.store');
            Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
            Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
            Route::post('/users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggleActive');

            // Gestión de Categorías (Escritura)
            Route::get('/categories/create', [CategoryController::class, 'create'])->name('categories.create');
            Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
            Route::get('/categories/{category}/edit', [CategoryController::class, 'edit'])->name('categories.edit');
            Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
            Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

            // Gestión de Productos (Escritura)
            Route::get('/products-create', [ProductController::class, 'create'])->name('products.create');
            Route::post('/products', [ProductController::class, 'store'])->name('products.store');
            Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
            Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
            Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');

            // Gestión de Clientes (Edición y Eliminación)
            Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
            Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
            Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');

            // Gestión de Proveedores (Creación, Edición y Eliminación)
            Route::get('/suppliers-create', [SupplierController::class, 'create'])->name('suppliers.create');
            Route::post('/suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
            Route::get('/suppliers/{supplier}/edit', [SupplierController::class, 'edit'])->name('suppliers.edit');
            Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
            Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');

            // Gestión de Gastos y Facturas de Compra (Exclusivo Administrador)
            Route::get('/expenses', [ExpenseController::class, 'index'])->name('expenses.index');
            Route::get('/expenses/create', [ExpenseController::class, 'create'])->name('expenses.create');
            Route::post('/expenses', [ExpenseController::class, 'store'])->name('expenses.store');
            Route::get('/expenses/{expense}', [ExpenseController::class, 'show'])->name('expenses.show');
            Route::get('/expenses/{expense}/edit', [ExpenseController::class, 'edit'])->name('expenses.edit');
            Route::put('/expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
            Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');
            Route::post('/expenses/{expense}/pay', [ExpenseController::class, 'markAsPaid'])->name('expenses.pay');
            Route::get('/expenses/{expense}/attachment', [ExpenseController::class, 'downloadAttachment'])->name('expenses.attachment');

            // Anulación de Ventas (Solo Administrador)
            Route::delete('/sales/{sale}', [SaleController::class, 'destroy'])->name('sales.destroy');

            // Módulo de Reportes (Solo Administrador)
            Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
            Route::get('/reports/sales', [ReportController::class, 'sales'])->name('reports.sales');
            Route::get('/reports/inventory', [ReportController::class, 'inventory'])->name('reports.inventory');
            Route::get('/reports/top-products', [ReportController::class, 'topProducts'])->name('reports.top-products');
        });
    });
});
