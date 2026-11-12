<?php

namespace Tests\Feature\Products;

use App\Models\Business;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ProductBulkImportTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected Role $employeeRole;
    protected Business $retailBusiness;
    protected Business $restaurantBusiness;
    protected User $retailAdmin;
    protected User $restaurantAdmin;
    protected User $retailEmployee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminRole = Role::create([
            'name' => 'Administrador',
            'slug' => Role::ROLE_ADMIN,
        ]);

        $this->employeeRole = Role::create([
            'name' => 'Empleado',
            'slug' => Role::ROLE_EMPLOYEE,
        ]);

        $this->retailBusiness = Business::create([
            'name' => 'Retail Cali Fitness',
            'business_type' => 'retail',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->restaurantBusiness = Business::create([
            'name' => 'Restaurante Cali Criollo',
            'business_type' => 'restaurant',
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->retailAdmin = User::factory()->create(['name' => 'Admin Retail']);
        $this->retailBusiness->users()->attach($this->retailAdmin->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $this->restaurantAdmin = User::factory()->create(['name' => 'Admin Restaurante']);
        $this->restaurantBusiness->users()->attach($this->restaurantAdmin->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $this->retailEmployee = User::factory()->create(['name' => 'Empleado Retail']);
        $this->retailBusiness->users()->attach($this->retailEmployee->id, [
            'role_id' => $this->employeeRole->id,
            'is_active' => true,
        ]);
    }

    /**
     * Helper para crear un UploadedFile XLSX real en memoria/disco temporal.
     */
    protected function createExcelFile(string $filename, array $rows, int $headerRow = 4): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($rows as $rowIndex => $rowData) {
            $excelRow = $rowIndex + 1;
            foreach ($rowData as $colIndex => $value) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
                $sheet->setCellValue("{$colLetter}{$excelRow}", $value);
            }
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'test_import_') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempPath);

        return new UploadedFile(
            $tempPath,
            $filename,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    /**
     * Helper para crear un UploadedFile CSV real en disco temporal.
     */
    protected function createCsvFile(string $filename, array $rows): UploadedFile
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'test_import_') . '.csv';
        $fp = fopen($tempPath, 'w');

        foreach ($rows as $row) {
            fputcsv($fp, $row);
        }
        fclose($fp);

        return new UploadedFile(
            $tempPath,
            $filename,
            'text/csv',
            null,
            true
        );
    }

    /**
     * Test 1: Descarga de plantilla según giro de negocio (Retail vs Restaurante).
     */
    public function test_template_download_returns_correct_file_for_retail_and_restaurant(): void
    {
        // 1. Retail
        $responseRetail = $this->actingAs($this->retailAdmin)
            ->withSession(['current_business_id' => $this->retailBusiness->id])
            ->get(route('products.template.download'));

        $responseRetail->assertOk();
        $this->assertStringContainsString(
            'Plantilla_Comercio_Retail_PuntoStock.xlsx',
            $responseRetail->headers->get('content-disposition') ?? ''
        );

        // 2. Restaurante
        $responseRestaurant = $this->actingAs($this->restaurantAdmin)
            ->withSession(['current_business_id' => $this->restaurantBusiness->id])
            ->get(route('products.template.download'));

        $responseRestaurant->assertOk();
        $this->assertStringContainsString(
            'Plantilla_Restaurante_PuntoStock.xlsx',
            $responseRestaurant->headers->get('content-disposition') ?? ''
        );

        // 3. Query param explícito ?type=retail o ?type=restaurant
        $responseExplicit = $this->actingAs($this->restaurantAdmin)
            ->withSession(['current_business_id' => $this->restaurantBusiness->id])
            ->get(route('products.template.download', ['type' => 'retail']));

        $responseExplicit->assertOk();
        $this->assertStringContainsString(
            'Plantilla_Comercio_Retail_PuntoStock.xlsx',
            $responseExplicit->headers->get('content-disposition') ?? ''
        );

        // 4. Negocio con tipo predeterminado resuelve a Retail
        $commerceBiz = Business::create([
            'name' => 'Tienda Ropa Express',
            'business_type' => 'retail',
            'status' => 'active',
            'is_active' => true,
        ]);
        $commerceUser = User::create([
            'name' => 'Commerce Admin',
            'email' => 'commerce_admin_' . uniqid() . '@puntostock.co',
            'password' => bcrypt('password123'),
        ]);
        $commerceBiz->users()->attach($commerceUser->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $responseCommerce = $this->actingAs($commerceUser)
            ->withSession(['current_business_id' => $commerceBiz->id])
            ->get(route('products.template.download'));

        $responseCommerce->assertOk();
        $this->assertStringContainsString(
            'Plantilla_Comercio_Retail_PuntoStock.xlsx',
            $responseCommerce->headers->get('content-disposition') ?? ''
        );

        // 5. Query param explícito ?type=commerce resuelve a Retail
        $responseCommerceType = $this->actingAs($this->restaurantAdmin)
            ->withSession(['current_business_id' => $this->restaurantBusiness->id])
            ->get(route('products.template.download', ['type' => 'commerce']));

        $responseCommerceType->assertOk();
        $this->assertStringContainsString(
            'Plantilla_Comercio_Retail_PuntoStock.xlsx',
            $responseCommerceType->headers->get('content-disposition') ?? ''
        );
    }

    /**
     * Test 2: Importación Exclusiva de Comercio Retail (8 Columnas).
     */
    public function test_retail_bulk_import_creates_category_and_products_with_inventory(): void
    {
        $rows = [
            ['PUNTOSTOCK — COMERCIO RETAIL'],
            ['Instrucciones...'],
            [],
            ['Nombre del Producto *', 'Categoría *', 'Precio de Venta *', 'Precio de Compra / Costo', 'Stock Inicial *', 'Stock Mínimo', 'Código / SKU', 'Descripción / Notas'],
            ['Proteína Whey Gold 2lb', 'Suplementos', '$ 130.000', '$ 85.000', '15', '3', 'SUP-001', 'Sabor Vainilla'],
            ['Creatina Pura 300g', 'Suplementos', '100.000', '60.000', '25', '5', 'SUP-002', 'Micronizada'],
        ];

        $file = $this->createExcelFile('catalogo_retail.xlsx', $rows);

        $response = $this->actingAs($this->retailAdmin)
            ->withSession(['current_business_id' => $this->retailBusiness->id])
            ->post(route('products.import'), [
                'file' => $file,
                'update_existing' => '1',
            ]);

        $response->assertRedirect(route('products.index'));
        $response->assertSessionHas('import_result');
        $response->assertSessionMissing('status');

        $result = session('import_result');
        $this->assertSame(2, $result['created_count']);
        $this->assertSame(0, $result['errors'] ? count($result['errors']) : 0);

        // Validar que se creó la categoría para este negocio
        $category = Category::where('business_id', $this->retailBusiness->id)
            ->where('name', 'Suplementos')
            ->first();
        $this->assertNotNull($category);

        // Validar producto 1
        $p1 = Product::where('business_id', $this->retailBusiness->id)
            ->where('sku', 'SUP-001')
            ->first();
        $this->assertNotNull($p1);
        $this->assertSame('Proteína Whey Gold 2lb', $p1->name);
        $this->assertEquals(130000.00, $p1->sale_price);
        $this->assertEquals(85000.00, $p1->cost_price);
        $this->assertEquals(15.0, $p1->stock);
        $this->assertSame('standard', $p1->product_type);

        // Validar movimiento de inventario inicial
        $movement = InventoryMovement::where('business_id', $this->retailBusiness->id)
            ->where('product_id', $p1->id)
            ->first();
        $this->assertNotNull($movement);
        $this->assertEquals(15.0, $movement->quantity);
    }

    /**
     * Test 3: Importación Exclusiva de Restaurante (9 Columnas con Carta y Mercancía).
     */
    public function test_restaurant_bulk_import_handles_dish_and_merchandise_correctly(): void
    {
        $rows = [
            ['PUNTOSTOCK — RESTAURANTES'],
            ['Instrucciones...'],
            [],
            ['Nombre del Plato o Producto *', 'Modalidad *', 'Categoría *', 'Precio de Venta *', 'Precio de Compra / Costo', 'Stock Inicial', 'Stock Mínimo', 'Código / SKU', 'Descripción / Acompañamientos'],
            ['Pollo Asado Familiar', 'Carta', 'Platos Fuertes', '$ 25.000', '', '', '', 'MNU-100', 'Papas y ensalada'],
            ['Gaseosa 400ml', 'Mercancía', 'Bebidas', '$ 4.500', '$ 2.500', '48', '12', 'BEB-100', 'Botella no retornable'],
        ];

        $file = $this->createExcelFile('menu_restaurante.xlsx', $rows);

        $response = $this->actingAs($this->restaurantAdmin)
            ->withSession(['current_business_id' => $this->restaurantBusiness->id])
            ->post(route('products.import'), [
                'file' => $file,
                'update_existing' => '1',
            ]);

        $response->assertRedirect(route('products.index'));
        $result = session('import_result');
        $this->assertSame(2, $result['created_count']);

        // 1. Pollo Asado debe ser plato de cocina ('dish') con stock 0
        $dish = Product::where('business_id', $this->restaurantBusiness->id)
            ->where('sku', 'MNU-100')
            ->first();
        $this->assertNotNull($dish);
        $this->assertSame('dish', $dish->product_type);
        $this->assertEquals(25000.00, $dish->sale_price);
        $this->assertEquals(0.0, $dish->stock);

        // 2. Gaseosa debe ser 'standard' con stock 48
        $bev = Product::where('business_id', $this->restaurantBusiness->id)
            ->where('sku', 'BEB-100')
            ->first();
        $this->assertNotNull($bev);
        $this->assertSame('standard', $bev->product_type);
        $this->assertEquals(4500.00, $bev->sale_price);
        $this->assertEquals(2500.00, $bev->cost_price);
        $this->assertEquals(48.0, $bev->stock);
    }

    /**
     * Test 4: Aislamiento Estricto Multi-Tenant y Actualización de Existentes (update_existing).
     */
    public function test_multi_tenant_isolation_and_update_existing_behavior(): void
    {
        // 1. Producto preexistente en Retail
        $existing = Product::create([
            'business_id' => $this->retailBusiness->id,
            'name' => 'Barra Energética',
            'sku' => 'BAR-001',
            'product_type' => 'standard',
            'sale_price' => 5000,
            'cost_price' => 3000,
            'stock' => 10,
            'is_active' => true,
        ]);

        // 2. Producto con MISMO SKU en Restaurante (otro tenant)
        $otherTenantProd = Product::create([
            'business_id' => $this->restaurantBusiness->id,
            'name' => 'Barra Energética Restaurante',
            'sku' => 'BAR-001',
            'product_type' => 'standard',
            'sale_price' => 7000,
            'cost_price' => 4000,
            'stock' => 5,
            'is_active' => true,
        ]);

        // Importar en Retail con actualización
        $rows = [
            ['Nombre del Producto *', 'Categoría *', 'Precio de Venta *', 'Precio de Compra / Costo', 'Stock Inicial *', 'Stock Mínimo', 'Código / SKU', 'Descripción / Notas'],
            ['Barra Energética', 'Snacks', '$ 6.000', '$ 3.500', '20', '5', 'BAR-001', 'Nueva fórmula'],
        ];
        $file = $this->createExcelFile('update_retail.xlsx', $rows, 1);

        $this->actingAs($this->retailAdmin)
            ->withSession(['current_business_id' => $this->retailBusiness->id])
            ->post(route('products.import'), [
                'file' => $file,
                'update_existing' => '1',
            ]);

        // Retail debe haberse actualizado
        $existing->refresh();
        $this->assertEquals(6000.00, $existing->sale_price);
        $this->assertEquals(3500.00, $existing->cost_price);
        $this->assertEquals(20.0, $existing->stock);

        // Restaurante NO debe haberse alterado en absoluto
        $otherTenantProd->refresh();
        $this->assertEquals(7000.00, $otherTenantProd->sale_price);
        $this->assertEquals(5.0, $otherTenantProd->stock);

        // Probar update_existing = 0
        $rowsSkip = [
            ['Nombre del Producto *', 'Categoría *', 'Precio de Venta *', 'Precio de Compra / Costo', 'Stock Inicial *', 'Stock Mínimo', 'Código / SKU', 'Descripción / Notas'],
            ['Barra Energética', 'Snacks', '$ 9.999', '$ 5.000', '50', '5', 'BAR-001', 'Omitir'],
        ];
        $fileSkip = $this->createExcelFile('skip_retail.xlsx', $rowsSkip, 1);

        $this->actingAs($this->retailAdmin)
            ->withSession(['current_business_id' => $this->retailBusiness->id])
            ->post(route('products.import'), [
                'file' => $fileSkip,
                'update_existing' => '0',
            ]);

        $existing->refresh();
        $this->assertEquals(6000.00, $existing->sale_price); // No cambió
    }

    /**
     * Test 5: Tolerancia a Errores de Fila (Importa válidas y reporta fila inválida sin error 500).
     */
    public function test_row_error_tolerance_imports_valid_rows_and_reports_invalid(): void
    {
        $rows = [
            ['Nombre del Producto *', 'Categoría *', 'Precio de Venta *', 'Precio de Compra / Costo', 'Stock Inicial *', 'Stock Mínimo', 'Código / SKU', 'Descripción / Notas'],
            ['Producto Válido 1', 'General', '10000', '5000', '10', '2', 'VAL-01', 'OK'],
            ['', 'General', '10000', '5000', '10', '2', 'INV-01', 'Fila sin nombre'],
            ['Producto Válido 2', 'General', '20000', '10000', '5', '1', 'VAL-02', 'OK'],
        ];

        $file = $this->createCsvFile('tolerancia.csv', $rows);

        $response = $this->actingAs($this->retailAdmin)
            ->withSession(['current_business_id' => $this->retailBusiness->id])
            ->post(route('products.import'), [
                'file' => $file,
                'update_existing' => '1',
            ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('products.index'));

        $result = session('import_result');
        $this->assertSame(2, $result['created_count']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('Fila 3: El nombre del producto es obligatorio.', $result['errors'][0]);

        $this->assertDatabaseHas('products', [
            'business_id' => $this->retailBusiness->id,
            'name' => 'Producto Válido 1',
        ]);
        $this->assertDatabaseHas('products', [
            'business_id' => $this->retailBusiness->id,
            'name' => 'Producto Válido 2',
        ]);
    }

    /**
     * Test 6: Empleado no puede importar productos (403 Forbidden).
     */
    public function test_employee_cannot_import_products_receiving_403(): void
    {
        $file = $this->createCsvFile('sample.csv', [
            ['Nombre del Producto *', 'Categoría *', 'Precio de Venta *'],
            ['Item', 'General', '1000'],
        ]);

        $response = $this->actingAs($this->retailEmployee)
            ->withSession(['current_business_id' => $this->retailBusiness->id])
            ->post(route('products.import'), [
                'file' => $file,
            ]);

        $response->assertStatus(403);
    }

    /**
     * Test 7: La vista de productos renderiza el botón animado "Importar Excel" con clase semántica y modal target.
     */
    public function test_product_index_renders_animated_import_excel_button(): void
    {
        // 1. Retail Business
        $retailResponse = $this->actingAs($this->retailAdmin)
            ->withSession(['current_business_id' => $this->retailBusiness->id])
            ->get(route('products.index'));

        $retailResponse->assertStatus(200);
        $retailResponse->assertSee('id="btnOpenImportModal"', false);
        $retailResponse->assertSee('btn-import-excel');
        $retailResponse->assertSee('data-bs-target="#importProductsModal"', false);
        $retailResponse->assertSee('Importar Excel');

        // 2. Restaurant Business
        $restaurantResponse = $this->actingAs($this->restaurantAdmin)
            ->withSession(['current_business_id' => $this->restaurantBusiness->id])
            ->get(route('products.index'));

        $restaurantResponse->assertStatus(200);
        $restaurantResponse->assertSee('id="btnOpenImportModal"', false);
        $restaurantResponse->assertSee('btn-import-excel');
        $restaurantResponse->assertSee('data-bs-target="#importProductsModal"', false);
        $restaurantResponse->assertSee('Importar Excel');
    }

    /**
     * Test 8: El resultado de importación renderiza un único banner detallado con estilos oficiales y sin duplicados.
     */
    public function test_import_result_renders_single_detailed_banner_above_page_title(): void
    {
        $response = $this->actingAs($this->restaurantAdmin)
            ->withSession([
                'current_business_id' => $this->restaurantBusiness->id,
                'status' => 'Importación finalizada: 12 creados, 0 actualizados.',
                'import_result' => [
                    'created_count' => 12,
                    'updated_count' => 0,
                    'skipped_count' => 1,
                    'errors' => ['Fila 5: El precio es requerido.'],
                    'total_rows_processed' => 13,
                ],
            ])
            ->get(route('products.index'));

        $response->assertStatus(200);

        // Verifica que la alerta detallada estilizada se renderiza
        $response->assertSee('style="background-color: #E6F4EA; border-left: 5px solid #137333 !important;"', false);
        $response->assertSee('Importación de Catálogo Finalizada');
        $response->assertSee('Se registraron con éxito <strong>12</strong> producto(s) nuevo(s)', false);
        $response->assertSee('y se actualizaron <strong>0</strong> existente(s)', false);
        $response->assertSee('Ver detalles y advertencias (1)');
        $response->assertSee('Fila 5: El precio es requerido.');

        // Verifica que la alerta genérica de status queda suprimida por layouts/app
        $response->assertDontSee('<i class="bi bi-check-circle-fill me-2"></i> Importación finalizada: 12 creados, 0 actualizados.', false);
    }

    /**
     * Test 9: La ruta products.import está registrada, resuelve el método POST y la vista renderiza sin RouteNotFoundException.
     */
    public function test_products_import_route_is_registered_and_catalog_renders_without_route_not_found(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Route::has('products.import'));
        $this->assertEquals(url('/products/import'), route('products.import'));

        // Admin puede acceder a productos y la vista renderiza la acción del modal
        $adminResponse = $this->actingAs($this->retailAdmin)
            ->withSession(['current_business_id' => $this->retailBusiness->id])
            ->get(route('products.index'));

        $adminResponse->assertStatus(200);
        $adminResponse->assertSee(route('products.import'), false);

        // Empleado también accede al catálogo sin RouteNotFoundException
        $employeeResponse = $this->actingAs($this->retailEmployee)
            ->withSession(['current_business_id' => $this->retailBusiness->id])
            ->get(route('products.index'));

        $employeeResponse->assertStatus(200);

        // POST a products.import resuelve hacia ProductController@import y valida archivo requerido
        $postResponse = $this->actingAs($this->retailAdmin)
            ->withSession(['current_business_id' => $this->retailBusiness->id])
            ->post(route('products.import'), []);

        $postResponse->assertStatus(302);
        $postResponse->assertSessionHasErrors('file');
    }
}

