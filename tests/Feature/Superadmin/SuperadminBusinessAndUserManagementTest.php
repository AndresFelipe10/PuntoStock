<?php

namespace Tests\Feature\Superadmin;

use App\Mail\WelcomeTenantMail;
use App\Models\Business;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\RestaurantOrder;
use App\Models\RestaurantOrderItem;
use App\Models\RestaurantTable;
use App\Models\Role;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\SalePayment;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SuperadminBusinessAndUserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $superadmin;
    protected User $normalUser;
    protected Business $business;
    protected Role $adminRole;
    protected Role $employeeRole;

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

        $this->superadmin = User::factory()->create([
            'is_superadmin' => true,
            'must_change_password' => false,
        ]);

        $this->normalUser = User::factory()->create([
            'is_superadmin' => false,
            'must_change_password' => false,
        ]);

        $this->business = Business::create([
            'name' => 'Empanadas Gourmet Cali',
            'business_type' => 'restaurant',
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    public function test_superadmin_can_update_collaborator_details_and_role(): void
    {
        $collaborator = User::factory()->create([
            'name' => 'Pedro Pérez',
            'email' => 'pedro@empanadas.com',
            'is_superadmin' => false,
        ]);

        $this->business->users()->attach($collaborator->id, [
            'role_id' => $this->employeeRole->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->superadmin)
            ->put(route('superadmin.businesses.users.update', [$this->business, $collaborator]), [
                'name' => 'Pedro Pablo Pérez',
                'email' => 'pedro.nuevo@empanadas.com',
                'role' => 'admin',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $collaborator->refresh();
        $this->assertEquals('Pedro Pablo Pérez', $collaborator->name);
        $this->assertEquals('pedro.nuevo@empanadas.com', $collaborator->email);

        $membership = $this->business->users()->where('users.id', $collaborator->id)->first();
        $this->assertEquals($this->adminRole->id, $membership->pivot->role_id);
    }

    public function test_superadmin_can_update_collaborator_password_and_dispatch_welcome_mail(): void
    {
        Mail::fake();

        $collaborator = User::factory()->create([
            'name' => 'Ana Gómez',
            'email' => 'ana@empanadas.com',
            'is_superadmin' => false,
            'must_change_password' => false,
        ]);

        $this->business->users()->attach($collaborator->id, [
            'role_id' => $this->employeeRole->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->superadmin)
            ->put(route('superadmin.businesses.users.update', [$this->business, $collaborator]), [
                'name' => 'Ana Gómez',
                'email' => 'ana@empanadas.com',
                'role' => 'employee',
                'password' => 'TemporalClave123!',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');
        $response->assertSessionHas('temp_password', 'TemporalClave123!');

        $collaborator->refresh();
        $this->assertTrue(Hash::check('TemporalClave123!', $collaborator->password));
        $this->assertTrue((bool) $collaborator->must_change_password);

        Mail::assertSent(WelcomeTenantMail::class, function ($mail) use ($collaborator) {
            return $mail->hasTo('ana@empanadas.com');
        });
    }

    public function test_superadmin_cannot_demote_sole_active_admin_to_employee(): void
    {
        $admin = User::factory()->create([
            'name' => 'Único Admin',
            'email' => 'admin.unico@empanadas.com',
            'is_superadmin' => false,
        ]);

        $this->business->users()->attach($admin->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->superadmin)
            ->put(route('superadmin.businesses.users.update', [$this->business, $admin]), [
                'name' => 'Único Admin Modificado',
                'email' => 'admin.unico@empanadas.com',
                'role' => 'employee',
            ]);

        $response->assertSessionHasErrors(['role']);

        $membership = $this->business->users()->where('users.id', $admin->id)->first();
        $this->assertEquals($this->adminRole->id, $membership->pivot->role_id);
    }

    public function test_superadmin_can_wipe_collaborator_operations(): void
    {
        $collaboratorA = User::factory()->create(['name' => 'Colaborador A']);
        $collaboratorB = User::factory()->create(['name' => 'Colaborador B']);

        $this->business->users()->attach([
            $collaboratorA->id => ['role_id' => $this->employeeRole->id, 'is_active' => true],
            $collaboratorB->id => ['role_id' => $this->employeeRole->id, 'is_active' => true],
        ]);

        // Operaciones de A
        $saleA = Sale::create([
            'business_id' => $this->business->id,
            'user_id' => $collaboratorA->id,
            'sale_date' => now(),
            'total' => 50000,
            'status' => 'completed',
            'payment_method' => 'cash',
        ]);
        SalePayment::create([
            'business_id' => $this->business->id,
            'sale_id' => $saleA->id,
            'amount' => 50000,
            'method' => 'cash',
        ]);
        $orderA = RestaurantOrder::create([
            'business_id' => $this->business->id,
            'user_id' => $collaboratorA->id,
            'order_number' => 'ORD-TEST-001',
            'status' => 'delivered',
            'total' => 25000,
            'service_type' => 'dine_in',
        ]);
        $prod = Product::create([
            'business_id' => $this->business->id,
            'name' => 'Empanada Carne',
            'sku' => 'EMP-CARNE',
            'price' => 5000,
            'cost' => 2000,
            'stock' => 20,
        ]);

        RestaurantOrderItem::create([
            'business_id' => $this->business->id,
            'order_id' => $orderA->id,
            'product_id' => $prod->id,
            'quantity' => 5,
            'unit_price' => 5000,
            'subtotal' => 25000,
        ]);
        Expense::create([
            'business_id' => $this->business->id,
            'created_by' => $collaboratorA->id,
            'amount' => 15000,
            'category' => 'supplies',
            'description' => 'Servilletas',
            'issue_date' => now()->toDateString(),
        ]);

        // Operaciones de B
        $saleB = Sale::create([
            'business_id' => $this->business->id,
            'user_id' => $collaboratorB->id,
            'sale_date' => now(),
            'total' => 80000,
            'status' => 'completed',
            'payment_method' => 'cash',
        ]);

        $response = $this->actingAs($this->superadmin)
            ->post(route('superadmin.businesses.users.wipe', [$this->business, $collaboratorA]));

        $response->assertRedirect();
        $response->assertSessionHas('status');

        // Operaciones de A eliminadas
        $this->assertDatabaseMissing('sales', ['id' => $saleA->id]);
        $this->assertDatabaseMissing('restaurant_orders', ['id' => $orderA->id]);
        $this->assertDatabaseMissing('expenses', ['business_id' => $this->business->id, 'created_by' => $collaboratorA->id]);

        // Operaciones de B conservadas
        $this->assertDatabaseHas('sales', ['id' => $saleB->id]);

        // Colaborador A sigue existiendo y vinculado
        $this->assertDatabaseHas('users', ['id' => $collaboratorA->id]);
        $this->assertDatabaseHas('business_user', [
            'business_id' => $this->business->id,
            'user_id' => $collaboratorA->id,
        ]);
    }

    public function test_superadmin_cannot_delete_sole_active_admin(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin Inamovible',
            'email' => 'admin.inamovible@empanadas.com',
        ]);

        $this->business->users()->attach($admin->id, [
            'role_id' => $this->adminRole->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->superadmin)
            ->delete(route('superadmin.businesses.users.destroy', [$this->business, $admin]));

        $response->assertSessionHasErrors(['error']);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
        $this->assertDatabaseHas('business_user', [
            'business_id' => $this->business->id,
            'user_id' => $admin->id,
        ]);
    }

    public function test_superadmin_can_delete_collaborator_and_purge_orphan_user(): void
    {
        $collaborator = User::factory()->create([
            'name' => 'Empleado Efímero',
            'email' => 'efimero@empanadas.com',
            'is_superadmin' => false,
        ]);

        $this->business->users()->attach($collaborator->id, [
            'role_id' => $this->employeeRole->id,
            'is_active' => true,
        ]);

        // Crear una venta vinculada a él
        Sale::create([
            'business_id' => $this->business->id,
            'user_id' => $collaborator->id,
            'sale_date' => now(),
            'total' => 10000,
            'status' => 'completed',
            'payment_method' => 'cash',
        ]);

        $response = $this->actingAs($this->superadmin)
            ->delete(route('superadmin.businesses.users.destroy', [$this->business, $collaborator]));

        $response->assertRedirect();
        $response->assertSessionHas('status');

        // Desvinculado y purgado totalmente
        $this->assertDatabaseMissing('business_user', [
            'business_id' => $this->business->id,
            'user_id' => $collaborator->id,
        ]);
        $this->assertDatabaseMissing('users', ['id' => $collaborator->id]);
    }

    public function test_superadmin_can_perform_operational_reset_preserving_catalog(): void
    {
        $category = Category::create(['business_id' => $this->business->id, 'name' => 'Comidas']);
        $product = Product::create([
            'business_id' => $this->business->id,
            'category_id' => $category->id,
            'name' => 'Empanada Pollo',
            'sku' => 'EMP-POLLO',
            'price' => 4500,
            'cost' => 2000,
            'stock' => 50,
        ]);
        $table = RestaurantTable::create([
            'business_id' => $this->business->id,
            'name' => 'Mesa 1',
            'capacity' => 4,
            'status' => 'occupied',
        ]);

        $user = User::factory()->create();
        $this->business->users()->attach($user->id, ['role_id' => $this->employeeRole->id, 'is_active' => true]);

        Sale::create([
            'business_id' => $this->business->id,
            'user_id' => $user->id,
            'sale_date' => now(),
            'total' => 9000,
            'status' => 'completed',
            'payment_method' => 'cash',
        ]);

        $response = $this->actingAs($this->superadmin)
            ->post(route('superadmin.businesses.reset', $this->business), [
                'mode' => 'operational',
                'confirmation_name' => $this->business->name,
                'reset_stock' => '1',
            ]);

        $response->assertRedirect(route('superadmin.businesses.index'));
        $response->assertSessionHas('status');

        // Operaciones vaciadas
        $this->assertEquals(0, Sale::withoutGlobalScopes()->where('business_id', $this->business->id)->count());

        // Catálogo conservado
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
        $this->assertDatabaseHas('products', ['id' => $product->id]);
        $this->assertDatabaseHas('restaurant_tables', ['id' => $table->id, 'status' => 'available']);

        // Stock reseteado a 0
        $product->refresh();
        $this->assertEquals(0, $product->stock);
    }

    public function test_superadmin_can_perform_full_catalog_wipe(): void
    {
        $category = Category::create(['business_id' => $this->business->id, 'name' => 'Bebidas']);
        $product = Product::create([
            'business_id' => $this->business->id,
            'category_id' => $category->id,
            'name' => 'Limonada Natural',
            'sku' => 'LIM-NAT',
            'price' => 6000,
            'cost' => 1500,
            'stock' => 20,
        ]);

        $response = $this->actingAs($this->superadmin)
            ->post(route('superadmin.businesses.reset', $this->business), [
                'mode' => 'full_catalog',
                'confirmation_name' => $this->business->name,
            ]);

        $response->assertRedirect(route('superadmin.businesses.index'));
        $response->assertSessionHas('status');

        // Catálogo eliminado
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
        $this->assertDatabaseMissing('products', ['id' => $product->id]);

        // Negocio permanece intacto
        $this->assertDatabaseHas('businesses', ['id' => $this->business->id]);
    }

    public function test_reset_fails_if_confirmation_name_does_not_match(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->post(route('superadmin.businesses.reset', $this->business), [
                'mode' => 'operational',
                'confirmation_name' => 'Nombre Incorrecto',
            ]);

        $response->assertSessionHasErrors(['confirmation_name']);
    }

    public function test_superadmin_can_permanently_delete_business_and_orphan_users_with_strict_isolation(): void
    {
        // Negocio A (A ser eliminado)
        $bizA = $this->business;
        $adminA = User::factory()->create(['name' => 'Admin A', 'email' => 'adminA@test.co']);
        $bizA->users()->attach($adminA->id, ['role_id' => $this->adminRole->id, 'is_active' => true]);

        $catA = Category::create(['business_id' => $bizA->id, 'name' => 'Cat A']);
        $prodA = Product::create([
            'business_id' => $bizA->id,
            'category_id' => $catA->id,
            'name' => 'Prod A',
            'sku' => 'PROD-A',
            'price' => 1000,
            'cost' => 500,
            'stock' => 10,
        ]);
        $saleA = Sale::create([
            'business_id' => $bizA->id,
            'user_id' => $adminA->id,
            'sale_date' => now(),
            'total' => 5000,
            'status' => 'completed',
            'payment_method' => 'cash',
        ]);

        // Negocio B (Debe mantenerse 100% intacto)
        $bizB = Business::create([
            'name' => 'Pizzería Napolitana Cali',
            'business_type' => 'restaurant',
            'status' => 'active',
            'is_active' => true,
        ]);
        $adminB = User::factory()->create(['name' => 'Admin B', 'email' => 'adminB@test.co']);
        $bizB->users()->attach($adminB->id, ['role_id' => $this->adminRole->id, 'is_active' => true]);

        $catB = Category::create(['business_id' => $bizB->id, 'name' => 'Cat B']);
        $prodB = Product::create([
            'business_id' => $bizB->id,
            'category_id' => $catB->id,
            'name' => 'Prod B',
            'sku' => 'PROD-B',
            'price' => 2000,
            'cost' => 800,
            'stock' => 15,
        ]);
        $saleB = Sale::create([
            'business_id' => $bizB->id,
            'user_id' => $adminB->id,
            'sale_date' => now(),
            'total' => 12000,
            'status' => 'completed',
            'payment_method' => 'cash',
        ]);

        // Eliminación de Negocio A
        $response = $this->actingAs($this->superadmin)
            ->delete(route('superadmin.businesses.destroy', $bizA), [
                'confirmation_name' => $bizA->name,
            ]);

        $response->assertRedirect(route('superadmin.businesses.index'));
        $response->assertSessionHas('status');

        // Verificación de Negocio A purgado
        $this->assertDatabaseMissing('businesses', ['id' => $bizA->id]);
        $this->assertDatabaseMissing('users', ['id' => $adminA->id]);
        $this->assertDatabaseMissing('categories', ['id' => $catA->id]);
        $this->assertDatabaseMissing('products', ['id' => $prodA->id]);
        $this->assertDatabaseMissing('sales', ['id' => $saleA->id]);

        // Verificación estricta de aislamiento: Negocio B intacto
        $this->assertDatabaseHas('businesses', ['id' => $bizB->id]);
        $this->assertDatabaseHas('users', ['id' => $adminB->id]);
        $this->assertDatabaseHas('categories', ['id' => $catB->id]);
        $this->assertDatabaseHas('products', ['id' => $prodB->id]);
        $this->assertDatabaseHas('sales', ['id' => $saleB->id]);
    }

    public function test_business_deletion_fails_if_confirmation_name_does_not_match(): void
    {
        $response = $this->actingAs($this->superadmin)
            ->delete(route('superadmin.businesses.destroy', $this->business), [
                'confirmation_name' => 'Erróneo',
            ]);

        $response->assertSessionHasErrors(['confirmation_name']);
        $this->assertDatabaseHas('businesses', ['id' => $this->business->id]);
    }

    public function test_non_superadmin_cannot_execute_management_actions(): void
    {
        $this->actingAs($this->normalUser)
            ->post(route('superadmin.businesses.reset', $this->business), [
                'mode' => 'operational',
                'confirmation_name' => $this->business->name,
            ])
            ->assertNotFound();

        $this->actingAs($this->normalUser)
            ->delete(route('superadmin.businesses.destroy', $this->business), [
                'confirmation_name' => $this->business->name,
            ])
            ->assertNotFound();
    }
}

