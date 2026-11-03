<?php

namespace Tests\Feature\Security;

use App\Enums\ExpenseCategory;
use App\Enums\PaymentMethod;
use App\Models\Business;
use App\Models\Category;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Dashboard\DashboardService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BlockDSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected Role $employeeRole;
    protected Business $businessA;
    protected Business $businessB;
    protected User $adminA;
    protected User $employeeA;
    protected User $adminB;
    protected Supplier $supplierA;
    protected Supplier $supplierB;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->adminRole = Role::firstOrCreate(
            ['slug' => Role::ROLE_ADMIN],
            ['name' => 'Administrador', 'description' => 'Acceso y administración total']
        );

        $this->employeeRole = Role::firstOrCreate(
            ['slug' => Role::ROLE_EMPLOYEE],
            ['name' => 'Empleado', 'description' => 'Vendedor']
        );

        $this->businessA = Business::create([
            'name' => 'Comercial Cali A',
            'nit' => '900111222-1',
            'status' => 'active',
        ]);

        $this->businessB = Business::create([
            'name' => 'Comercial Cali B',
            'nit' => '900333444-2',
            'status' => 'active',
        ]);

        $this->adminA = User::factory()->create(['name' => 'Admin A', 'email' => 'adminA@cali.com']);
        $this->businessA->users()->attach($this->adminA->id, ['role_id' => $this->adminRole->id, 'is_active' => true]);

        $this->employeeA = User::factory()->create(['name' => 'Empleado A', 'email' => 'empleadoA@cali.com']);
        $this->businessA->users()->attach($this->employeeA->id, ['role_id' => $this->employeeRole->id, 'is_active' => true]);

        $this->adminB = User::factory()->create(['name' => 'Admin B', 'email' => 'adminB@cali.com']);
        $this->businessB->users()->attach($this->adminB->id, ['role_id' => $this->adminRole->id, 'is_active' => true]);

        $this->supplierA = Supplier::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Distribuidora del Valle',
            'contact_name' => 'Carlos Perez',
            'phone' => '3151234567',
        ]);

        $this->supplierB = Supplier::withoutGlobalScopes()->create([
            'business_id' => $this->businessB->id,
            'name' => 'Proveedor Bogotá',
            'contact_name' => 'Bogota Contact',
            'phone' => '3119876543',
        ]);
    }

    /**
     * Helper to authenticate as user with tenant session.
     */
    protected function actingAsTenant(User $user, Business $business): self
    {
        return $this->actingAs($user)->withSession([
            'current_business_id' => $business->id,
            'business_role' => $user->isCurrentAdmin() ? Role::ROLE_ADMIN : Role::ROLE_EMPLOYEE,
        ]);
    }

    /**
     * 1. CRUD completo de gastos por parte del Administrador.
     */
    public function test_admin_can_perform_full_crud_on_expenses(): void
    {
        // Index
        $response = $this->actingAsTenant($this->adminA, $this->businessA)->get(route('expenses.index'));
        $response->assertOk();
        $response->assertViewIs('expenses.index');

        // Create view
        $response = $this->actingAsTenant($this->adminA, $this->businessA)->get(route('expenses.create'));
        $response->assertOk();
        $response->assertViewIs('expenses.create');

        // Store
        $file = UploadedFile::fake()->create('factura_001.pdf', 500, 'application/pdf');
        $storeData = [
            'supplier_id' => $this->supplierA->id,
            'invoice_number' => 'FAC-9901',
            'issue_date' => '2026-09-01',
            'due_date' => '2026-09-15',
            'category' => ExpenseCategory::Merchandise->value,
            'description' => 'Compra de cajas de zapatos',
            'amount' => 1250000.50,
            'status' => 'pending',
            'attachment' => $file,
        ];

        $response = $this->actingAsTenant($this->adminA, $this->businessA)->post(route('expenses.store'), $storeData);
        $response->assertRedirect(route('expenses.index'));
        $response->assertSessionHas('success');
        $response->assertSessionMissing('status');

        $indexResponse = $this->actingAsTenant($this->adminA, $this->businessA)
            ->withSession(['success' => session('success')])
            ->get(route('expenses.index'));
        $this->assertSame(
            1,
            substr_count($indexResponse->getContent(), 'Gasto registrado exitosamente'),
            'La notificación de éxito al registrar gasto debe renderizarse exactamente 1 vez en el HTML.'
        );

        $this->assertDatabaseHas('expenses', [
            'business_id' => $this->businessA->id,
            'supplier_id' => $this->supplierA->id,
            'invoice_number' => 'FAC-9901',
            'amount' => 1250000.50,
            'status' => 'pending',
            'category' => 'merchandise',
            'attachment_original_name' => 'factura_001.pdf',
        ]);

        $expense = Expense::where('invoice_number', 'FAC-9901')->first();
        $this->assertNotNull($expense);
        $this->assertNotNull($expense->attachment_path);
        Storage::disk('local')->assertExists($expense->attachment_path);

        // Show
        $response = $this->actingAsTenant($this->adminA, $this->businessA)->get(route('expenses.show', $expense));
        $response->assertOk();
        $response->assertViewIs('expenses.show');

        // Edit
        $response = $this->actingAsTenant($this->adminA, $this->businessA)->get(route('expenses.edit', $expense));
        $response->assertOk();
        $response->assertViewIs('expenses.edit');

        // Update
        $updateData = [
            'supplier_id' => $this->supplierA->id,
            'invoice_number' => 'FAC-9901-MOD',
            'issue_date' => '2026-09-01',
            'due_date' => '2026-09-20',
            'category' => ExpenseCategory::Supplies->value,
            'description' => 'Insumos de oficina modificados',
            'amount' => 1300000.00,
            'status' => 'pending',
        ];

        $response = $this->actingAsTenant($this->adminA, $this->businessA)->put(route('expenses.update', $expense), $updateData);
        $response->assertRedirect(route('expenses.index'));

        $this->assertDatabaseHas('expenses', [
            'id' => $expense->id,
            'invoice_number' => 'FAC-9901-MOD',
            'category' => 'supplies',
            'amount' => 1300000.00,
        ]);

        // Soft Delete
        $response = $this->actingAsTenant($this->adminA, $this->businessA)->delete(route('expenses.destroy', $expense));
        $response->assertRedirect(route('expenses.index'));

        $this->assertSoftDeleted('expenses', ['id' => $expense->id]);
    }

    /**
     * 2. Validaciones de negocio: monto <= 0, fecha de vencimiento < emisión, campos obligatorios al pagar.
     */
    public function test_expense_validations_reject_invalid_inputs(): void
    {
        // Monto <= 0
        $response = $this->actingAsTenant($this->adminA, $this->businessA)->post(route('expenses.store'), [
            'issue_date' => '2026-09-10',
            'category' => ExpenseCategory::Utilities->value,
            'amount' => 0,
            'status' => 'pending',
        ]);
        $response->assertSessionHasErrors('amount');

        $responseNegative = $this->actingAsTenant($this->adminA, $this->businessA)->post(route('expenses.store'), [
            'issue_date' => '2026-09-10',
            'category' => ExpenseCategory::Utilities->value,
            'amount' => -50000,
            'status' => 'pending',
        ]);
        $responseNegative->assertSessionHasErrors('amount');

        // due_date < issue_date
        $responseDate = $this->actingAsTenant($this->adminA, $this->businessA)->post(route('expenses.store'), [
            'issue_date' => '2026-09-20',
            'due_date' => '2026-09-10',
            'category' => ExpenseCategory::Utilities->value,
            'amount' => 85000,
            'status' => 'pending',
        ]);
        $responseDate->assertSessionHasErrors('due_date');

        // status = paid requires payment_method and paid_at
        $responsePaid = $this->actingAsTenant($this->adminA, $this->businessA)->post(route('expenses.store'), [
            'issue_date' => '2026-09-20',
            'category' => ExpenseCategory::Rent->value,
            'amount' => 800000,
            'status' => 'paid',
        ]);
        $responsePaid->assertSessionHasErrors(['payment_method', 'paid_at']);

        // Cross-tenant supplier rejection
        $responseCrossSupplier = $this->actingAsTenant($this->adminA, $this->businessA)->post(route('expenses.store'), [
            'supplier_id' => $this->supplierB->id,
            'issue_date' => '2026-09-20',
            'category' => ExpenseCategory::Supplies->value,
            'amount' => 50000,
            'status' => 'pending',
        ]);
        $responseCrossSupplier->assertSessionHasErrors('supplier_id');
    }

    /**
     * 3. Constraint de unicidad compuesta: (business_id, supplier_id, invoice_number).
     */
    public function test_composite_unique_constraint_on_supplier_and_invoice_number(): void
    {
        Expense::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'supplier_id' => $this->supplierA->id,
            'invoice_number' => 'INV-UNIQUE-100',
            'issue_date' => '2026-09-15',
            'category' => ExpenseCategory::Merchandise->value,
            'amount' => 200000,
            'status' => 'pending',
            'created_by' => $this->adminA->id,
        ]);

        // Attempt duplicate invoice for same supplier in businessA
        $response = $this->actingAsTenant($this->adminA, $this->businessA)->post(route('expenses.store'), [
            'supplier_id' => $this->supplierA->id,
            'invoice_number' => 'INV-UNIQUE-100',
            'issue_date' => '2026-09-16',
            'category' => ExpenseCategory::Merchandise->value,
            'amount' => 300000,
            'status' => 'pending',
        ]);

        $response->assertSessionHasErrors('invoice_number');

        // But businessB can use the same invoice number for its own supplier
        $responseB = $this->actingAsTenant($this->adminB, $this->businessB)->post(route('expenses.store'), [
            'supplier_id' => $this->supplierB->id,
            'invoice_number' => 'INV-UNIQUE-100',
            'issue_date' => '2026-09-16',
            'category' => ExpenseCategory::Merchandise->value,
            'amount' => 450000,
            'status' => 'pending',
        ]);

        $responseB->assertRedirect(route('expenses.index'));
        $this->assertDatabaseHas('expenses', [
            'business_id' => $this->businessB->id,
            'invoice_number' => 'INV-UNIQUE-100',
        ]);
    }

    /**
     * 4. Vendedor (Empleado) recibe 403 Forbidden en todos los endpoints de gastos.
     */
    public function test_sellers_receive_403_forbidden_on_all_expense_endpoints(): void
    {
        $expense = Expense::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'issue_date' => '2026-09-15',
            'category' => ExpenseCategory::Utilities->value,
            'amount' => 120000,
            'status' => 'pending',
            'created_by' => $this->adminA->id,
        ]);

        $client = $this->actingAsTenant($this->employeeA, $this->businessA);

        $client->get(route('expenses.index'))->assertForbidden();
        $client->get(route('expenses.create'))->assertForbidden();
        $client->post(route('expenses.store'), [
            'issue_date' => '2026-09-15',
            'category' => ExpenseCategory::Utilities->value,
            'amount' => 50000,
            'status' => 'pending',
        ])->assertForbidden();

        $client->get(route('expenses.show', $expense))->assertForbidden();
        $client->get(route('expenses.edit', $expense))->assertForbidden();
        $client->put(route('expenses.update', $expense), [
            'issue_date' => '2026-09-15',
            'category' => ExpenseCategory::Utilities->value,
            'amount' => 60000,
            'status' => 'pending',
        ])->assertForbidden();

        $client->delete(route('expenses.destroy', $expense))->assertForbidden();
        $client->post(route('expenses.pay', $expense), [
            'paid_at' => '2026-09-15',
            'payment_method' => PaymentMethod::Cash->value,
        ])->assertForbidden();

        $client->get(route('expenses.attachment', $expense))->assertForbidden();
    }

    /**
     * 5. Aislamiento Cross-Tenant estricto en gastos y descargas de adjuntos.
     */
    public function test_cross_tenant_access_to_expenses_and_attachments_is_strictly_blocked(): void
    {
        $fakeFile = UploadedFile::fake()->create('secreto_a.pdf', 200, 'application/pdf');
        $storedPath = $fakeFile->store('expenses', 'local');

        $expenseA = Expense::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'issue_date' => '2026-09-15',
            'category' => ExpenseCategory::Payroll->value,
            'amount' => 2500000,
            'status' => 'pending',
            'attachment_path' => $storedPath,
            'attachment_original_name' => 'secreto_a.pdf',
            'created_by' => $this->adminA->id,
        ]);

        // Admin B attempts to access Expense A show
        $response = $this->actingAsTenant($this->adminB, $this->businessB)->get(route('expenses.show', $expenseA));
        $this->assertTrue(in_array($response->status(), [403, 404]));

        // Admin B attempts to download Expense A attachment
        $responseAttach = $this->actingAsTenant($this->adminB, $this->businessB)->get(route('expenses.attachment', $expenseA));
        $this->assertTrue(in_array($responseAttach->status(), [403, 404]));

        // Admin B attempts to pay Expense A
        $responsePay = $this->actingAsTenant($this->adminB, $this->businessB)->post(route('expenses.pay', $expenseA), [
            'paid_at' => '2026-09-15',
            'payment_method' => PaymentMethod::Transfer->value,
        ]);
        $this->assertTrue(in_array($responsePay->status(), [403, 404]));
    }

    /**
     * 6. Seguridad en archivos adjuntos: rechazo de archivos maliciosos (SVG, HTML, PHP, doble extensión).
     */
    public function test_attachments_strictly_reject_malicious_files(): void
    {
        // SVG
        $svgFile = UploadedFile::fake()->create('malicioso.svg', 100, 'image/svg+xml');
        $response = $this->actingAsTenant($this->adminA, $this->businessA)->post(route('expenses.store'), [
            'issue_date' => '2026-09-15',
            'category' => ExpenseCategory::Other->value,
            'amount' => 10000,
            'status' => 'pending',
            'attachment' => $svgFile,
        ]);
        $response->assertSessionHasErrors('attachment');

        // HTML
        $htmlFile = UploadedFile::fake()->create('phishing.html', 100, 'text/html');
        $responseHtml = $this->actingAsTenant($this->adminA, $this->businessA)->post(route('expenses.store'), [
            'issue_date' => '2026-09-15',
            'category' => ExpenseCategory::Other->value,
            'amount' => 10000,
            'status' => 'pending',
            'attachment' => $htmlFile,
        ]);
        $responseHtml->assertSessionHasErrors('attachment');

        // PHP
        $phpFile = UploadedFile::fake()->create('shell.php', 100, 'application/x-php');
        $responsePhp = $this->actingAsTenant($this->adminA, $this->businessA)->post(route('expenses.store'), [
            'issue_date' => '2026-09-15',
            'category' => ExpenseCategory::Other->value,
            'amount' => 10000,
            'status' => 'pending',
            'attachment' => $phpFile,
        ]);
        $responsePhp->assertSessionHasErrors('attachment');

        // Doble extensión (factura.php.pdf)
        $doubleExt = UploadedFile::fake()->create('factura.php.pdf', 100, 'application/pdf');
        $responseDouble = $this->actingAsTenant($this->adminA, $this->businessA)->post(route('expenses.store'), [
            'issue_date' => '2026-09-15',
            'category' => ExpenseCategory::Other->value,
            'amount' => 10000,
            'status' => 'pending',
            'attachment' => $doubleExt,
        ]);
        $responseDouble->assertSessionHasErrors('attachment');
    }

    /**
     * 7. Descarga segura con headers nosniff y limpieza de archivos físicos al eliminar o actualizar.
     */
    public function test_attachment_download_has_nosniff_header_and_cleans_up_on_delete(): void
    {
        $file = UploadedFile::fake()->create('factura_legal.pdf', 300, 'application/pdf');

        $this->actingAsTenant($this->adminA, $this->businessA)->post(route('expenses.store'), [
            'issue_date' => '2026-09-15',
            'category' => ExpenseCategory::Utilities->value,
            'amount' => 90000,
            'status' => 'pending',
            'attachment' => $file,
        ]);

        $expense = Expense::where('business_id', $this->businessA->id)->latest('id')->first();
        $this->assertNotNull($expense->attachment_path);
        Storage::disk('local')->assertExists($expense->attachment_path);

        // Download has X-Content-Type-Options: nosniff
        $response = $this->actingAsTenant($this->adminA, $this->businessA)->get(route('expenses.attachment', $expense));
        $response->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');

        // Update with remove_attachment cleans up file
        $oldPath = $expense->attachment_path;
        $this->actingAsTenant($this->adminA, $this->businessA)->put(route('expenses.update', $expense), [
            'issue_date' => '2026-09-15',
            'category' => ExpenseCategory::Utilities->value,
            'amount' => 90000,
            'status' => 'pending',
            'remove_attachment' => '1',
        ]);

        $expense->refresh();
        $this->assertNull($expense->attachment_path);
        Storage::disk('local')->assertMissing($oldPath);
    }

    /**
     * 8. Acción rápida "Marcar como pagada".
     */
    public function test_quick_mark_as_paid_action(): void
    {
        $expense = Expense::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'issue_date' => '2026-09-01',
            'category' => ExpenseCategory::Rent->value,
            'amount' => 1500000,
            'status' => 'pending',
            'created_by' => $this->adminA->id,
        ]);

        $response = $this->actingAsTenant($this->adminA, $this->businessA)->post(route('expenses.pay', $expense), [
            'paid_at' => '2026-09-05',
            'payment_method' => PaymentMethod::Transfer->value,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $expense->refresh();
        $this->assertEquals('paid', $expense->status);
        $this->assertEquals('2026-09-05', $expense->paid_at->toDateString());
        $this->assertEquals(PaymentMethod::Transfer, $expense->payment_method);
    }

    /**
     * 9. Regla Contable: Registrar gasto de categoría mercancía NO altera inventario ni movimientos.
     */
    public function test_merchandise_expense_does_not_alter_inventory_stock(): void
    {
        $category = Category::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Vestuario',
        ]);

        $product = Product::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'category_id' => $category->id,
            'name' => 'Camisa Formal',
            'sku' => 'CAM-001',
            'cost_price' => 30000,
            'selling_price' => 60000,
            'stock' => 15,
            'min_stock' => 5,
            'is_active' => true,
        ]);

        $initialStock = $product->stock;
        $initialMovementsCount = \App\Models\InventoryMovement::count();

        // Register merchandise expense
        $this->actingAsTenant($this->adminA, $this->businessA)->post(route('expenses.store'), [
            'supplier_id' => $this->supplierA->id,
            'invoice_number' => 'FAC-MERC-01',
            'issue_date' => '2026-09-15',
            'category' => ExpenseCategory::Merchandise->value,
            'description' => 'Factura por 20 camisas formales recibidas',
            'amount' => 600000,
            'status' => 'paid',
            'paid_at' => '2026-09-15',
            'payment_method' => PaymentMethod::Cash->value,
        ]);

        $product->refresh();
        $this->assertEquals($initialStock, $product->stock, 'Product stock must not change when recording an expense');
        $this->assertEquals($initialMovementsCount, \App\Models\InventoryMovement::count(), 'No inventory movements should be generated');
    }

    /**
     * 10. Dashboard Financiero con Carbon::setTestNow y encriptación/ocultamiento de métricas a vendedores.
     */
    public function test_dashboard_financial_metrics_calculation_and_seller_masking(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-28 10:00:00', 'America/Bogota'));

        // Venta completada en septiembre 2026 (mes actual)
        Sale::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->adminA->id,
            'invoice_number' => 'FAC-DASH-01',
            'sale_date' => '2026-09-10 14:00:00',
            'total' => 500000.00,
            'status' => 'completed',
            'payment_method' => 'cash',
        ]);

        // Venta anulada en septiembre 2026 (debe excluirse del total)
        Sale::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->adminA->id,
            'invoice_number' => 'FAC-DASH-02',
            'sale_date' => '2026-09-11 11:00:00',
            'total' => 200000.00,
            'status' => 'cancelled',
            'payment_method' => 'cash',
        ]);

        // Gasto pagado en septiembre 2026
        Expense::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'issue_date' => '2026-09-05',
            'category' => ExpenseCategory::Rent->value,
            'amount' => 150000.00,
            'status' => 'paid',
            'paid_at' => '2026-09-05',
            'payment_method' => PaymentMethod::Transfer->value,
            'created_by' => $this->adminA->id,
        ]);

        // Gasto pendiente en septiembre 2026
        Expense::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'issue_date' => '2026-09-20',
            'category' => ExpenseCategory::Utilities->value,
            'amount' => 50000.00,
            'status' => 'pending',
            'created_by' => $this->adminA->id,
        ]);

        // Gasto de otro mes (agosto 2026) que no debe sumarse
        Expense::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'issue_date' => '2026-08-25',
            'category' => ExpenseCategory::Other->value,
            'amount' => 99999.00,
            'status' => 'paid',
            'paid_at' => '2026-08-25',
            'payment_method' => PaymentMethod::Cash->value,
            'created_by' => $this->adminA->id,
        ]);

        // DashboardService directo: Admin
        $dashboardService = app(DashboardService::class);
        $adminMetrics = $dashboardService->getMetrics($this->businessA->id, true);

        $this->assertEquals(500000.00, $adminMetrics['month_sales_total']);
        $this->assertEquals(200000.00, $adminMetrics['month_expenses_total']);
        $this->assertEquals(50000.00, $adminMetrics['month_expenses_pending']);
        $this->assertEquals(300000.00, $adminMetrics['estimated_net_profit']); // 500.000 - 200.000

        // DashboardService directo: Seller (no-admin)
        $sellerMetrics = $dashboardService->getMetrics($this->businessA->id, false);
        $this->assertArrayNotHasKey('today_sales_total', $sellerMetrics);
        $this->assertArrayNotHasKey('month_sales_total', $sellerMetrics);
        $this->assertArrayNotHasKey('month_expenses_total', $sellerMetrics);
        $this->assertArrayNotHasKey('month_expenses_pending', $sellerMetrics);
        $this->assertArrayNotHasKey('estimated_net_profit', $sellerMetrics);

        // Petición Web: Admin ve las tarjetas financieras
        $responseAdminWeb = $this->actingAsTenant($this->adminA, $this->businessA)->get(route('dashboard'));
        $responseAdminWeb->assertOk();
        $responseAdminWeb->assertSee('Ventas del Mes');
        $responseAdminWeb->assertSee('Gastos / Compras del mes');
        $responseAdminWeb->assertSee('Utilidad neta estimada');
        $responseAdminWeb->assertSee('$500.000');
        $responseAdminWeb->assertSee('$200.000');

        // Petición Web: Vendedor NO ve las tarjetas financieras ni los totales
        $responseSellerWeb = $this->actingAsTenant($this->employeeA, $this->businessA)->get(route('dashboard'));
        $responseSellerWeb->assertOk();
        $responseSellerWeb->assertDontSee('Gastos / Compras del mes');
        $responseSellerWeb->assertDontSee('Utilidad neta estimada');
        $responseSellerWeb->assertDontSee('$500.000');
        $responseSellerWeb->assertDontSee('$200.000');

        // API Endpoint: Admin recibe data financiera
        $responseAdminApi = $this->actingAsTenant($this->adminA, $this->businessA)->get(route('api.dashboard'));
        $responseAdminApi->assertOk();
        $this->assertEquals(500000, $responseAdminApi->json('metrics.month_sales_total'));
        $this->assertEquals(300000, $responseAdminApi->json('metrics.estimated_net_profit'));

        // API Endpoint: Vendedor NO recibe data financiera en el payload JSON
        $responseSellerApi = $this->actingAsTenant($this->employeeA, $this->businessA)->get(route('api.dashboard'));
        $responseSellerApi->assertOk();
        $jsonSeller = $responseSellerApi->json('metrics');
        $this->assertArrayNotHasKey('month_sales_total', $jsonSeller);
        $this->assertArrayNotHasKey('month_expenses_total', $jsonSeller);
        $this->assertArrayNotHasKey('estimated_net_profit', $jsonSeller);

        Carbon::setTestNow(); // Reset clock
    }
}
