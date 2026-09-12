<?php

namespace Tests\Feature\Hr;

use App\Models\Tenant;
use App\Models\User;
use Modules\Hr\Models\Department;
use Modules\Hr\Models\Employee;
use Tests\TenantTestCase;

class DepartmentScreensTest extends TenantTestCase
{
    public function test_index_lists_departments(): void
    {
        Department::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'İK']);

        $this->actingAs($this->tenantAdmin)
            ->get(route('app.hr.departments.index'))
            ->assertOk()
            ->assertSee('İK');
    }

    public function test_can_create_department_with_manager(): void
    {
        $manager = Employee::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => User::factory()->for($this->tenant)->create()->id,
        ]);

        $this->actingAs($this->tenantAdmin)
            ->post(route('app.hr.departments.store'), [
                'name' => 'Muhasebe',
                'manager_employee_id' => $manager->id,
            ])
            ->assertRedirect(route('app.hr.departments.index'));

        $this->assertDatabaseHas('departments', [
            'name' => 'Muhasebe',
            'manager_employee_id' => $manager->id,
        ]);
    }

    public function test_parent_department_cannot_be_self(): void
    {
        $dept = Department::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->tenantAdmin)
            ->patch(route('app.hr.departments.update', $dept), [
                'name' => $dept->name,
                'parent_id' => $dept->id,
            ])
            ->assertSessionHasErrors(['parent_id']);
    }

    public function test_cross_tenant_manager_reference_is_rejected(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherEmployee = Employee::factory()->create([
            'tenant_id' => $otherTenant->id,
            'user_id' => User::factory()->for($otherTenant)->create()->id,
        ]);

        $this->actingAs($this->tenantAdmin)
            ->post(route('app.hr.departments.store'), [
                'name' => 'X',
                'manager_employee_id' => $otherEmployee->id,
            ])
            ->assertSessionHasErrors(['manager_employee_id']);
    }
}
