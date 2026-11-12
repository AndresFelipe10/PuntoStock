<?php

namespace Tests\Feature\Directory;

use App\Models\Business;
use App\Models\Customer;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerTest extends TestCase
{
    use RefreshDatabase;

    protected Role $adminRole;
    protected Role $employeeRole;
    protected Business $businessA;
    protected Business $businessB;
    protected User $adminUser;
    protected User $employeeUser;

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

        $this->businessA = Business::create(['name' => 'Negocio Cali A']);
        $this->businessB = Business::create(['name' => 'Negocio Cali B']);

        $this->adminUser = User::factory()->create();
        $this->businessA->users()->attach($this->adminUser->id, ['role_id' => $this->adminRole->id, 'is_active' => true]);

        $this->employeeUser = User::factory()->create();
        $this->businessA->users()->attach($this->employeeUser->id, ['role_id' => $this->employeeRole->id, 'is_active' => true]);
    }

    public function test_admin_and_employee_can_view_customers_list_and_details(): void
    {
        $customer = Customer::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'María Rodríguez',
            'identification_number' => '1144001122',
            'phone' => '3151234567',
            'email' => 'maria@correo.com',
            'address' => 'Calle 5 # 10-20',
            'is_active' => true,
        ]);

        $adminResponse = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get(route('customers.index'));

        $adminResponse->assertStatus(200);
        $adminResponse->assertSee('María Rodríguez');

        $empResponse = $this->actingAs($this->employeeUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get(route('customers.show', $customer));

        $empResponse->assertStatus(200);
        $empResponse->assertSee('María Rodríguez');
    }

    public function test_admin_can_create_customer(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('customers.store'), [
                'name' => 'Carlos López',
                'identification_number' => '94500123',
                'phone' => '3209876543',
                'email' => 'carlos@cliente.com',
                'address' => 'Av. 6N # 22-10',
                'is_active' => '1',
            ]);

        $response->assertRedirect(route('customers.index'));
        $this->assertDatabaseHas('customers', [
            'business_id' => $this->businessA->id,
            'name' => 'Carlos López',
            'identification_number' => '94500123',
        ]);
    }

    public function test_employee_can_create_customer(): void
    {
        $response = $this->actingAs($this->employeeUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('customers.store'), [
                'name' => 'Cliente Mostrador',
                'identification_number' => '22334455',
            ]);

        $response->assertRedirect(route('customers.index'));
        $this->assertDatabaseHas('customers', [
            'business_id' => $this->businessA->id,
            'name' => 'Cliente Mostrador',
        ]);
    }

    public function test_admin_can_update_customer(): void
    {
        $customer = Customer::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Nombre Antiguo',
            'document' => '1144001122',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->put(route('customers.update', $customer), [
                'name' => 'Nombre Nuevo',
                'document' => '1144001122',
                'phone' => '3001112233',
                'is_active' => '1',
            ]);

        $response->assertRedirect(route('customers.index'));
        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Nombre Nuevo',
            'document' => '1144001122',
            'phone' => '3001112233',
        ]);
    }

    public function test_employee_cannot_update_customer(): void
    {
        $customer = Customer::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Cliente Original',
        ]);

        $response = $this->actingAs($this->employeeUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->put(route('customers.update', $customer), [
                'name' => 'Hack Attempt',
            ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_delete_customer_without_sales(): void
    {
        $customer = Customer::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Cliente Sin Compras',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->delete(route('customers.destroy', $customer));

        $response->assertRedirect(route('customers.index'));
        $this->assertDatabaseMissing('customers', [
            'id' => $customer->id,
        ]);
    }

    public function test_employee_cannot_delete_customer(): void
    {
        $customer = Customer::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Cliente Protegido',
        ]);

        $response = $this->actingAs($this->employeeUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->delete(route('customers.destroy', $customer));

        $response->assertStatus(403);
    }

    public function test_customer_with_sales_is_deactivated_instead_of_deleted(): void
    {
        $customer = Customer::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Cliente Con Historial',
            'is_active' => true,
        ]);

        Sale::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->adminUser->id,
            'customer_id' => $customer->id,
            'sale_date' => now(),
            'total' => 50000,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->delete(route('customers.destroy', $customer));

        $response->assertRedirect(route('customers.index'));

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'is_active' => 0, // Desactivado
        ]);
    }

    public function test_customers_are_isolated_between_businesses(): void
    {
        Customer::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Cliente Exclusivo Empresa A',
        ]);

        Customer::withoutGlobalScopes()->create([
            'business_id' => $this->businessB->id,
            'name' => 'Cliente Exclusivo Empresa B',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get(route('customers.index'));

        $response->assertSee('Cliente Exclusivo Empresa A');
        $response->assertDontSee('Cliente Exclusivo Empresa B');
    }

    public function test_customer_can_be_created_with_null_or_empty_email(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->post(route('customers.store'), [
                'name' => 'Cliente Sin Email',
                'document' => '1144998877',
                'phone' => '3159988776',
                'email' => '',
                'address' => 'Barrio Granada, Cali',
                'is_active' => '1',
            ]);

        $response->assertRedirect(route('customers.index'));
        $this->assertDatabaseHas('customers', [
            'business_id' => $this->businessA->id,
            'name' => 'Cliente Sin Email',
            'document' => '1144998877',
            'email' => null,
        ]);
    }

    public function test_customer_show_displays_payment_methods_in_spanish_not_raw_english_code(): void
    {
        $customer = Customer::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'name' => 'Carlos Mendoza',
            'document' => '1144005566',
            'phone' => '3167788990',
            'is_active' => true,
        ]);

        Sale::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->adminUser->id,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_document' => $customer->document,
            'invoice_number' => 'VTA-TEST-001',
            'sale_date' => now('America/Bogota'),
            'subtotal' => 50000,
            'total' => 50000,
            'payment_method' => 'cash',
            'status' => 'completed',
        ]);

        Sale::withoutGlobalScopes()->create([
            'business_id' => $this->businessA->id,
            'user_id' => $this->adminUser->id,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_document' => $customer->document,
            'invoice_number' => 'VTA-TEST-002',
            'sale_date' => now('America/Bogota'),
            'subtotal' => 75000,
            'total' => 75000,
            'payment_method' => 'transfer',
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->adminUser)
            ->withSession(['current_business_id' => $this->businessA->id])
            ->get(route('customers.show', $customer));

        $response->assertStatus(200);
        $response->assertSee('Efectivo');
        $response->assertSee('Transferencia');
        $response->assertDontSee('<span class="badge bg-light text-dark border">cash</span>', false);
        $response->assertDontSee('>cash<', false);
    }
}
