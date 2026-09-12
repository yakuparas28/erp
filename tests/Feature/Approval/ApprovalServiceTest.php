<?php

namespace Tests\Feature\Approval;

use App\Models\Approval\ApprovalWorkflow;
use App\Models\Approval\ApprovalWorkflowStep;
use App\Models\User;
use App\Services\Approval\ApprovalService;
use Modules\Hr\Models\Employee;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TenantTestCase;

/**
 * Generic onay motoru testleri. Subject olarak Employee kullanılıyor çünkü
 * hem morph alias'ı hem tenant-scope'u var; motorun subject-agnostik olduğunu
 * kanıtlamak için bu yeterli.
 */
class ApprovalServiceTest extends TenantTestCase
{
    private ApprovalService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ApprovalService::class);
    }

    private function newWorkflow(array $stepDefinitions = [['role', 'Tenant Admin']]): ApprovalWorkflow
    {
        $workflow = ApprovalWorkflow::factory()->create([
            'tenant_id' => $this->tenant->id,
            'subject_type' => 'employee',
        ]);
        foreach ($stepDefinitions as $i => [$type, $value]) {
            ApprovalWorkflowStep::factory()->create([
                'approval_workflow_id' => $workflow->id,
                'sequence' => $i + 1,
                'approver_type' => $type,
                'approver_value' => $value,
            ]);
        }

        return $workflow;
    }

    private function newSubject(): Employee
    {
        return Employee::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => User::factory()->for($this->tenant)->create()->id,
        ]);
    }

    public function test_submit_creates_pending_approval_pointing_to_first_step(): void
    {
        $this->newWorkflow();
        $subject = $this->newSubject();

        $approval = $this->service->submit($subject, $this->tenantAdmin);

        $this->assertSame('pending', $approval->status);
        $this->assertSame(1, $approval->current_step);
        $this->assertSame('employee', $approval->approvable_type);
        $this->assertSame($subject->id, $approval->approvable_id);
    }

    public function test_submit_fails_when_no_workflow_configured(): void
    {
        $subject = $this->newSubject();

        $this->expectException(HttpException::class);
        $this->service->submit($subject, $this->tenantAdmin);
    }

    public function test_approve_single_step_workflow_marks_approved(): void
    {
        $this->newWorkflow();
        $subject = $this->newSubject();
        $approval = $this->service->submit($subject, $this->tenantAdmin);

        $approver = User::factory()->for($this->tenant)->create();
        setPermissionsTeamId($this->tenant->id);
        $approver->assignRole('Tenant Admin');

        $result = $this->service->approve($approval, $approver);

        $this->assertSame('approved', $result->status);
        $this->assertNull($result->current_step);
        $this->assertNotNull($result->decided_at);
        $this->assertDatabaseHas('approval_actions', [
            'approval_id' => $approval->id,
            'action' => 'approve',
            'actor_user_id' => $approver->id,
        ]);
    }

    public function test_approve_two_step_workflow_advances_then_completes(): void
    {
        $workflow = $this->newWorkflow([
            ['role', 'Tenant Admin'],
            ['role', 'Warehouse Operator'],
        ]);
        $subject = $this->newSubject();
        $approval = $this->service->submit($subject, $this->tenantAdmin);

        $firstApprover = User::factory()->for($this->tenant)->create();
        $secondApprover = User::factory()->for($this->tenant)->create();
        setPermissionsTeamId($this->tenant->id);
        $firstApprover->assignRole('Tenant Admin');
        $secondApprover->assignRole('Warehouse Operator');

        $after1 = $this->service->approve($approval, $firstApprover);
        $this->assertSame('pending', $after1->status);
        $this->assertSame(2, $after1->current_step);

        $after2 = $this->service->approve($after1, $secondApprover);
        $this->assertSame('approved', $after2->status);
    }

    public function test_approve_forbidden_when_actor_role_does_not_match(): void
    {
        $this->newWorkflow([['role', 'Tenant Admin']]);
        $subject = $this->newSubject();
        $approval = $this->service->submit($subject, $this->tenantAdmin);

        $stranger = User::factory()->for($this->tenant)->create();
        setPermissionsTeamId($this->tenant->id);
        $stranger->assignRole('Warehouse Operator');

        $this->expectException(HttpException::class);
        $this->service->approve($approval, $stranger);
    }

    public function test_reject_marks_rejected_and_records_action(): void
    {
        $this->newWorkflow();
        $subject = $this->newSubject();
        $approval = $this->service->submit($subject, $this->tenantAdmin);

        $approver = User::factory()->for($this->tenant)->create();
        setPermissionsTeamId($this->tenant->id);
        $approver->assignRole('Tenant Admin');

        $result = $this->service->reject($approval, $approver, 'sebep');

        $this->assertSame('rejected', $result->status);
        $this->assertDatabaseHas('approval_actions', [
            'approval_id' => $approval->id,
            'action' => 'reject',
            'comment' => 'sebep',
        ]);
    }

    public function test_cancel_allowed_only_for_submitter(): void
    {
        $this->newWorkflow();
        $subject = $this->newSubject();
        $approval = $this->service->submit($subject, $this->tenantAdmin);

        $other = User::factory()->for($this->tenant)->create();

        $this->expectException(HttpException::class);
        $this->service->cancel($approval, $other);
    }

    public function test_cancel_by_submitter_marks_cancelled(): void
    {
        $this->newWorkflow();
        $subject = $this->newSubject();
        $approval = $this->service->submit($subject, $this->tenantAdmin);

        $result = $this->service->cancel($approval, $this->tenantAdmin, 'vazgeçtim');

        $this->assertSame('cancelled', $result->status);
    }

    public function test_double_decide_is_rejected(): void
    {
        $this->newWorkflow();
        $subject = $this->newSubject();
        $approval = $this->service->submit($subject, $this->tenantAdmin);

        $approver = User::factory()->for($this->tenant)->create();
        setPermissionsTeamId($this->tenant->id);
        $approver->assignRole('Tenant Admin');
        $this->service->approve($approval, $approver);

        $this->expectException(HttpException::class);
        $this->service->approve($approval->fresh(), $approver);
    }

    public function test_user_type_resolver_matches_by_id(): void
    {
        $specificApprover = User::factory()->for($this->tenant)->create();

        $this->newWorkflow([['user', (string) $specificApprover->id]]);
        $subject = $this->newSubject();
        $approval = $this->service->submit($subject, $this->tenantAdmin);

        $result = $this->service->approve($approval, $specificApprover);
        $this->assertSame('approved', $result->status);
    }
}
