<?php

namespace Tests\Feature\Hr;

use App\Models\User;
use Modules\Hr\Models\Employee;
use Modules\Hr\Models\LeaveRequest;
use Modules\Hr\Models\LeaveType;
use Tests\TenantTestCase;

class LeaveScreensTest extends TenantTestCase
{
    public function test_leave_types_index_is_reachable_and_can_create_a_type(): void
    {
        $this->actingAs($this->tenantAdmin)
            ->get(route('app.hr.leave-types.index'))
            ->assertOk();

        $this->actingAs($this->tenantAdmin)
            ->post(route('app.hr.leave-types.store'), [
                'key' => 'yillik',
                'name' => 'Yıllık İzin',
                'unit' => 'day',
                'deducts_from_balance' => 1,
                'is_active' => 1,
            ])
            ->assertRedirect(route('app.hr.leave-types.index'));

        $this->assertDatabaseHas('leave_types', ['key' => 'yillik', 'name' => 'Yıllık İzin']);
    }

    public function test_leave_balances_index_lists_employees(): void
    {
        Employee::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => User::factory()->for($this->tenant)->create()->id,
            'first_name' => 'Ali',
            'last_name' => 'Test',
        ]);

        $this->actingAs($this->tenantAdmin)
            ->get(route('app.hr.leave-balances.index'))
            ->assertOk()
            ->assertSee('Ali Test');
    }

    public function test_leave_config_page_is_reachable(): void
    {
        $this->actingAs($this->tenantAdmin)
            ->get(route('app.hr.leave-config.index'))
            ->assertOk();
    }

    public function test_holiday_can_be_added_from_config_page(): void
    {
        $this->actingAs($this->tenantAdmin)
            ->post(route('app.hr.leave-config.holidays.store'), [
                'date' => '2027-04-23',
                'name' => 'Ulusal Egemenlik ve Çocuk Bayramı',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('holidays', ['name' => 'Ulusal Egemenlik ve Çocuk Bayramı']);
    }

    public function test_leave_monitoring_page_shown_to_permitted_user(): void
    {
        $this->actingAs($this->tenantAdmin)
            ->get(route('app.hr.leave-monitoring.index'))
            ->assertOk();
    }

    public function test_leave_type_with_requests_cannot_be_deleted(): void
    {
        $type = LeaveType::factory()->create(['tenant_id' => $this->tenant->id]);
        $employee = Employee::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => User::factory()->for($this->tenant)->create()->id,
        ]);
        LeaveRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'employee_id' => $employee->id,
            'leave_type_id' => $type->id,
        ]);

        $this->actingAs($this->tenantAdmin)
            ->delete(route('app.hr.leave-types.destroy', $type))
            ->assertStatus(422);
    }

    public function test_mine_page_requires_hr_profile(): void
    {
        $userWithoutProfile = User::factory()->for($this->tenant)->create();

        $this->actingAs($userWithoutProfile)
            ->get(route('app.hr.leaves.mine'))
            ->assertForbidden();
    }
}
