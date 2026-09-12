<?php

namespace Tests\Feature\Hr;

use App\Models\Tenant;
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

    public function test_can_create_employee_for_existing_user(): void
    {
        $user = User::factory()->for($this->tenant)->create();

        $this->actingAs($this->tenantAdmin)
            ->post(route('app.hr.employees.store'), [
                'user_id' => $user->id,
                'first_name' => 'Mehmet',
                'last_name' => 'Demir',
                'title' => 'Depo Sorumlusu',
                'annual_leave_balance' => 14,
            ])
            ->assertRedirect(route('app.hr.employees.index'));

        $this->assertDatabaseHas('employees', [
            'user_id' => $user->id,
            'first_name' => 'Mehmet',
            'title' => 'Depo Sorumlusu',
        ]);
    }

    public function test_cannot_create_two_employees_for_same_user(): void
    {
        $user = User::factory()->for($this->tenant)->create();
        Employee::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $user->id]);

        $this->actingAs($this->tenantAdmin)
            ->post(route('app.hr.employees.store'), [
                'user_id' => $user->id,
                'first_name' => 'X',
                'last_name' => 'Y',
            ])
            ->assertSessionHasErrors(['user_id']);
    }

    public function test_cannot_reference_user_from_another_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->for($otherTenant)->create();

        $this->actingAs($this->tenantAdmin)
            ->post(route('app.hr.employees.store'), [
                'user_id' => $otherUser->id,
                'first_name' => 'X',
                'last_name' => 'Y',
            ])
            ->assertSessionHasErrors(['user_id']);
    }

    public function test_manager_cannot_be_self(): void
    {
        $employee = Employee::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => User::factory()->for($this->tenant)->create()->id,
        ]);

        $this->actingAs($this->tenantAdmin)
            ->patch(route('app.hr.employees.update', $employee), [
                'user_id' => $employee->user_id,
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
                'user_id' => $employee->user_id,
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
