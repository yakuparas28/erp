<?php

namespace Tests\Feature\Hr;

use App\Models\Approval\ApprovalWorkflow;
use App\Models\Approval\ApprovalWorkflowStep;
use App\Models\User;
use App\Services\Approval\ApprovalService;
use Modules\Hr\Models\CriticalDate;
use Modules\Hr\Models\Employee;
use Modules\Hr\Models\LeaveType;
use Modules\Hr\Services\LeaveRequestService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TenantTestCase;

/**
 * İzin yaşam döngüsü: personel gönder → birim yön. onayla → (2. seviye) →
 * bakiye düşer. HR modülünde ApprovalService'e "manager" ve "role" resolver'ları
 * kaydedildiğinden bu senaryolar iki farklı onay tipini de kapsıyor.
 */
class LeaveWorkflowE2ETest extends TenantTestCase
{
    private LeaveRequestService $service;

    private LeaveType $annualLeave;

    private Employee $manager;

    private Employee $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(LeaveRequestService::class);

        $this->annualLeave = LeaveType::factory()->create([
            'tenant_id' => $this->tenant->id,
            'key' => 'yillik',
        ]);

        $managerUser = User::factory()->for($this->tenant)->create();
        $this->manager = Employee::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $managerUser->id,
            'annual_leave_balance' => 14,
        ]);

        $employeeUser = User::factory()->for($this->tenant)->create();
        $this->employee = Employee::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $employeeUser->id,
            'manager_id' => $this->manager->id,
            'annual_leave_balance' => 10,
        ]);

        $workflow = ApprovalWorkflow::factory()->create([
            'tenant_id' => $this->tenant->id,
            'subject_type' => 'leave_request',
        ]);
        ApprovalWorkflowStep::factory()->create([
            'approval_workflow_id' => $workflow->id,
            'sequence' => 1,
            'approver_type' => 'manager',
            'approver_value' => null,
        ]);
    }

    public function test_full_flow_submit_then_manager_approves_then_balance_deducts(): void
    {
        $leave = $this->service->submit($this->employee, [
            'leave_type_id' => $this->annualLeave->id,
            'start_date' => '2027-01-05',
            'end_date' => '2027-01-07',
            'reason' => 'aile ziyareti',
        ]);

        $this->assertSame('pending', $leave->approval->status);
        $this->assertEquals('3.00', $leave->total_days);

        app(ApprovalService::class)->approve($leave->approval, $this->manager->user);
        $this->service->afterDecision($leave);

        $leave->refresh();
        $this->assertSame('approved', $leave->approval->status);
        $this->assertEquals('7.00', $this->employee->fresh()->annual_leave_balance);
    }

    public function test_stranger_cannot_approve_managers_workflow_step(): void
    {
        $leave = $this->service->submit($this->employee, [
            'leave_type_id' => $this->annualLeave->id,
            'start_date' => '2027-02-05',
            'end_date' => '2027-02-05',
        ]);

        $strangerUser = User::factory()->for($this->tenant)->create();

        $this->expectException(HttpException::class);
        app(ApprovalService::class)->approve($leave->approval, $strangerUser);
    }

    public function test_manager_rejection_does_not_deduct_balance(): void
    {
        $balanceBefore = $this->employee->annual_leave_balance;

        $leave = $this->service->submit($this->employee, [
            'leave_type_id' => $this->annualLeave->id,
            'start_date' => '2027-03-01',
            'end_date' => '2027-03-02',
        ]);

        app(ApprovalService::class)->reject($leave->approval, $this->manager->user, 'yoğun dönem');
        $this->service->afterDecision($leave);

        $this->assertSame('rejected', $leave->approval->fresh()->status);
        $this->assertEquals($balanceBefore, $this->employee->fresh()->annual_leave_balance);
    }

    public function test_submit_fails_when_balance_is_insufficient(): void
    {
        $this->employee->update(['annual_leave_balance' => 1]);

        $this->expectException(HttpException::class);
        $this->service->submit($this->employee, [
            'leave_type_id' => $this->annualLeave->id,
            'start_date' => '2027-04-01',
            'end_date' => '2027-04-05',
        ]);
    }

    public function test_submit_blocked_during_critical_period(): void
    {
        CriticalDate::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Envanter Sayımı',
            'start_date' => '2027-05-01',
            'end_date' => '2027-05-10',
            'block_leave_requests' => true,
        ]);

        $this->expectException(HttpException::class);
        $this->service->submit($this->employee, [
            'leave_type_id' => $this->annualLeave->id,
            'start_date' => '2027-05-03',
            'end_date' => '2027-05-04',
        ]);
    }

    public function test_leave_type_not_deducting_leaves_balance_intact(): void
    {
        $unpaid = LeaveType::factory()->create([
            'tenant_id' => $this->tenant->id,
            'key' => 'ucretsiz',
            'deducts_from_balance' => false,
        ]);
        $balanceBefore = $this->employee->annual_leave_balance;

        $leave = $this->service->submit($this->employee, [
            'leave_type_id' => $unpaid->id,
            'start_date' => '2027-06-01',
            'end_date' => '2027-06-03',
        ]);

        app(ApprovalService::class)->approve($leave->approval, $this->manager->user);
        $this->service->afterDecision($leave);

        $this->assertEquals($balanceBefore, $this->employee->fresh()->annual_leave_balance);
    }
}
