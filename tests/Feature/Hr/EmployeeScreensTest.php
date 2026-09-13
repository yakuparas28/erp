<?php

namespace Tests\Feature\Hr;

use App\Models\User;
use Modules\Hr\Models\Employee;
use Tests\TenantTestCase;

class EmployeeScreensTest extends TenantTestCase
{
    public function test_index_lists_employees(): void
    {
        Employee::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => User::factory()->for($this->tenant)->create()->id,
            'first_name' => 'Ayşe',
            'last_name' => 'Yılmaz',
        ]);

        $this->actingAs($this->tenantAdmin)
            ->get(route('app.hr.employees.index'))
            ->assertOk()
            ->assertSee('Ayşe Yılmaz');
    }

    public function test_create_employee_also_creates_the_system_user(): void
    {
        $this->actingAs($this->tenantAdmin)
            ->post(route('app.hr.employees.store'), [
                'email' => 'mehmet.demir@example.test',
                'first_name' => 'Mehmet',
                'last_name' => 'Demir',
                'title' => 'Depo Sorumlusu',
                'annual_leave_balance' => 14,
                'temp_password' => 'gizli-parola-1234',
            ])
            ->assertRedirect(route('app.hr.employees.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'mehmet.demir@example.test',
            'name' => 'Mehmet Demir',
            'tenant_id' => $this->tenant->id,
        ]);
        $this->assertDatabaseHas('employees', [
            'first_name' => 'Mehmet',
            'last_name' => 'Demir',
            'title' => 'Depo Sorumlusu',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_email_must_be_unique_across_users(): void
    {
        User::factory()->for($this->tenant)->create(['email' => 'in-use@example.test']);

        $this->actingAs($this->tenantAdmin)
            ->post(route('app.hr.employees.store'), [
                'email' => 'in-use@example.test',
                'first_name' => 'X',
                'last_name' => 'Y',
                'temp_password' => 'gizli-parola-1234',
            ])
            ->assertSessionHasErrors(['email']);
    }

    public function test_temp_password_is_auto_generated_when_missing(): void
    {
        $this->actingAs($this->tenantAdmin)
            ->post(route('app.hr.employees.store'), [
                'email' => 'auto.pass@example.test',
                'first_name' => 'Auto',
                'last_name' => 'Pass',
            ])
            ->assertRedirect(route('app.hr.employees.index'));

        $this->assertDatabaseHas('users', ['email' => 'auto.pass@example.test']);
    }

    public function test_manager_cannot_be_self(): void
    {
        $employee = Employee::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => User::factory()->for($this->tenant)->create()->id,
        ]);

        $this->actingAs($this->tenantAdmin)
            ->patch(route('app.hr.employees.update', $employee), [
                'first_name' => $employee->first_name,
                'last_name' => $employee->last_name,
                'manager_id' => $employee->id,
            ])
            ->assertSessionHasErrors(['manager_id']);
    }

    public function test_can_update_and_delete_employee(): void
    {
        $employee = Employee::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => User::factory()->for($this->tenant)->create()->id,
            'first_name' => 'Eski',
            'last_name' => 'Ad',
        ]);

        $this->actingAs($this->tenantAdmin)
            ->patch(route('app.hr.employees.update', $employee), [
                'first_name' => 'Yeni',
                'last_name' => 'Ad',
            ]);

        $this->assertSame('Yeni', $employee->fresh()->first_name);

        $this->actingAs($this->tenantAdmin)
            ->delete(route('app.hr.employees.destroy', $employee))
            ->assertRedirect(route('app.hr.employees.index'));

        $this->assertDatabaseMissing('employees', ['id' => $employee->id]);
    }
}
