<?php

namespace Tests\Feature\Hr;

use App\Models\Approval\ApprovalWorkflow;
use App\Models\Approval\ApprovalWorkflowStep;
use App\Models\User;
use Modules\Hr\Models\Employee;
use Modules\Hr\Models\LeaveHourConfig;
use Modules\Hr\Models\LeaveType;
use Modules\Hr\Services\LeaveRequestService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TenantTestCase;

/**
 * LeaveHourConfig.negative_balance_policy'nin izin talep başvurusundaki
 * bakiye kontrolünü nasıl etkilediğini kanıtlar: 'lenient' → eksiye izin ver,
 * 'strict' (varsayılan) → 422.
 */
class NegativeBalancePolicyTest extends TenantTestCase
{
    private LeaveRequestService $service;

    private LeaveType $type;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(LeaveRequestService::class);

        $this->type = LeaveType::factory()->create([
            'tenant_id' => $this->tenant->id,
            'key' => 'yillik',
        ]);

        $this->employee = Employee::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => User::factory()->for($this->tenant)->create()->id,
            'annual_leave_balance' => 1,
        ]);

        $workflow = ApprovalWorkflow::factory()->create([
            'tenant_id' => $this->tenant->id,
            'subject_type' => 'leave_request',
        ]);
        ApprovalWorkflowStep::factory()->create([
            'approval_workflow_id' => $workflow->id,
            'sequence' => 1,
            'approver_type' => 'role',
            'approver_value' => 'Tenant Admin',
        ]);
    }

    public function test_strict_policy_rejects_insufficient_balance_by_default(): void
    {
        // No LeaveHourConfig row → default strict.
        $this->expectException(HttpException::class);
        $this->service->submit($this->employee, [
            'leave_type_id' => $this->type->id,
            'start_date' => '2027-01-01',
            'end_date' => '2027-01-05',
        ]);
    }

    public function test_lenient_policy_allows_negative_balance(): void
    {
        LeaveHourConfig::create([
            'tenant_id' => $this->tenant->id,
            'department_id' => null,
            'daily_work_hours' => 8,
            'monthly_leave_hours' => 24,
            'min_hours' => 1,
            'negative_balance_policy' => 'lenient',
            'is_active' => true,
        ]);

        $leave = $this->service->submit($this->employee, [
            'leave_type_id' => $this->type->id,
            'start_date' => '2027-02-01',
            'end_date' => '2027-02-05',
        ]);

        $this->assertSame('pending', $leave->approval->status);
        $this->assertEquals('5.00', $leave->total_days);
    }
}
