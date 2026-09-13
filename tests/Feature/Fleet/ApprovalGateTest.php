<?php

namespace Tests\Feature\Fleet;

use App\Models\User;
use Tests\TenantTestCase;

class ApprovalGateTest extends TenantTestCase
{
    public function test_fleet_manager_passes_gate(): void
    {
        $fm = User::factory()->for($this->tenant)->create();
        setPermissionsTeamId($this->tenant->id);
        $fm->assignRole('Fleet Manager');

        $this->assertTrue($fm->can('approve-vehicle-request'));
    }

    public function test_employee_does_not_pass_gate(): void
    {
        $emp = User::factory()->for($this->tenant)->create();
        setPermissionsTeamId($this->tenant->id);
        $emp->assignRole('Employee');

        $this->assertFalse($emp->can('approve-vehicle-request'));
    }

    public function test_approvals_route_is_forbidden_for_non_fleet_manager(): void
    {
        $emp = User::factory()->for($this->tenant)->create();
        setPermissionsTeamId($this->tenant->id);
        $emp->assignRole('Employee');
        $this->actingAs($emp);

        $this->get(route('app.fleet.approvals.index'))->assertForbidden();
    }
}
