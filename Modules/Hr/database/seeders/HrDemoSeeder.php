<?php

namespace Modules\Hr\Database\Seeders;

use App\Models\Approval\ApprovalWorkflow;
use App\Models\Approval\ApprovalWorkflowStep;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Approval\ApprovalService;
use Illuminate\Database\Seeder;
use Modules\Hr\Models\CriticalDate;
use Modules\Hr\Models\Department;
use Modules\Hr\Models\Employee;
use Modules\Hr\Models\Holiday;
use Modules\Hr\Models\LeaveBalance;
use Modules\Hr\Models\LeaveRequest;
use Modules\Hr\Models\LeaveType;
use Modules\Hr\Services\HrDefaultsService;

/**
 * Tek tenant için (varsayılan: "Test Firma") HR modülünü hızlı görsel test
 * verisiyle doldurur: 3 departman, 6 personel (1 yönetici + 5 çalışan),
 * onay iş akışı, 3 örnek izin talebi (pending/approved/rejected), yıllık
 * bakiyeler, 2 tatil, 1 kritik tarih. HrDefaultsService baz katalog için
 * çağrılır. Tamamen idempotent — birden fazla çalıştırılabilir.
 */
class HrDemoSeeder extends Seeder
{
    public function run(): void
    {
        $tenantName = env('HR_DEMO_TENANT', 'Test Firma');
        $tenant = Tenant::where('name', $tenantName)->firstOrFail();

        setPermissionsTeamId($tenant->id);

        app(HrDefaultsService::class)->provision($tenant);

        $departments = $this->seedDepartments($tenant);
        [$manager, $staff] = $this->seedEmployees($tenant, $departments);
        $this->seedWorkflow($tenant);
        $this->seedBalances($tenant, array_merge([$manager], $staff));
        $this->seedLeaveRequests($tenant, $manager, $staff);
        $this->seedHolidaysAndCriticalDates($tenant, $manager);

        $this->command?->info("HR demo data seeded for tenant: {$tenant->name}");
    }

    /** @return array<string, Department> */
    private function seedDepartments(Tenant $tenant): array
    {
        $names = ['Yönetim', 'Satış', 'Operasyon'];
        $out = [];
        foreach ($names as $name) {
            $out[$name] = Department::updateOrCreate(
                ['tenant_id' => $tenant->id, 'name' => $name],
                ['is_active' => true],
            );
        }

        return $out;
    }

    /**
     * @param  array<string, Department>  $depts
     * @return array{0: Employee, 1: list<Employee>}
     */
    private function seedEmployees(Tenant $tenant, array $depts): array
    {
        $managerUser = User::firstOrCreate(
            ['email' => 'ali.yildiz@testfirma.local'],
            ['name' => 'Ali Yıldız', 'password' => 'password', 'tenant_id' => $tenant->id],
        );
        setPermissionsTeamId($tenant->id);
        $managerUser->syncRoles(['Tenant Admin']);

        $manager = Employee::updateOrCreate(
            ['user_id' => $managerUser->id],
            [
                'tenant_id' => $tenant->id,
                'first_name' => 'Ali', 'last_name' => 'Yıldız',
                'title' => 'Genel Müdür',
                'department_id' => $depts['Yönetim']->id,
                'hire_date' => '2020-01-15',
                'annual_leave_balance' => 20,
                'is_active' => true,
            ],
        );

        $depts['Yönetim']->update(['manager_employee_id' => $manager->id]);

        $staff = [];
        foreach ($this->staffData() as [$fn, $ln, $title, $deptName, $hire, $balance]) {
            $email = mb_strtolower("{$fn}.{$ln}@testfirma.local");
            $u = User::firstOrCreate(
                ['email' => $email],
                ['name' => "$fn $ln", 'password' => 'password', 'tenant_id' => $tenant->id],
            );
            $staff[] = Employee::updateOrCreate(
                ['user_id' => $u->id],
                [
                    'tenant_id' => $tenant->id,
                    'first_name' => $fn, 'last_name' => $ln,
                    'title' => $title,
                    'department_id' => $depts[$deptName]->id,
                    'manager_id' => $manager->id,
                    'hire_date' => $hire,
                    'annual_leave_balance' => $balance,
                    'is_active' => true,
                ],
            );
        }

        return [$manager, $staff];
    }

    /** @return list<array{0:string,1:string,2:string,3:string,4:string,5:int}> */
    private function staffData(): array
    {
        return [
            ['Ayşe', 'Kaya', 'Satış Uzmanı', 'Satış', '2023-03-01', 14],
            ['Mehmet', 'Demir', 'Satış Sorumlusu', 'Satış', '2022-07-15', 15],
            ['Zeynep', 'Şahin', 'Operasyon Uzmanı', 'Operasyon', '2024-01-10', 12],
            ['Emre', 'Öz', 'Depo Sorumlusu', 'Operasyon', '2021-05-20', 20],
            ['Selin', 'Aksoy', 'İK Uzmanı', 'Yönetim', '2023-09-01', 14],
        ];
    }

    private function seedWorkflow(Tenant $tenant): void
    {
        $wf = ApprovalWorkflow::updateOrCreate(
            ['tenant_id' => $tenant->id, 'subject_type' => 'leave_request'],
            ['name' => 'İzin Onay Akışı', 'is_active' => true],
        );
        ApprovalWorkflowStep::updateOrCreate(
            ['approval_workflow_id' => $wf->id, 'sequence' => 1],
            ['approver_type' => 'manager', 'approver_value' => null],
        );
    }

    /** @param  list<Employee>  $employees */
    private function seedBalances(Tenant $tenant, array $employees): void
    {
        $year = (int) date('Y');
        foreach ($employees as $e) {
            LeaveBalance::updateOrCreate(
                ['tenant_id' => $tenant->id, 'employee_id' => $e->id, 'year' => $year],
                ['carried_from_previous' => 0, 'current_year_entitlement' => $e->annual_leave_balance, 'manual_adjustment' => 0],
            );
        }
    }

    /** @param  list<Employee>  $staff */
    private function seedLeaveRequests(Tenant $tenant, Employee $manager, array $staff): void
    {
        // Avoid double-seeding if leave requests already exist for these staff.
        if (LeaveRequest::where('tenant_id', $tenant->id)->whereIn('employee_id', array_column($staff, 'id'))->exists()) {
            return;
        }

        $yillik = LeaveType::where('tenant_id', $tenant->id)->where('key', 'yillik')->firstOrFail();
        $svc = app(ApprovalService::class);

        // 1. Pending — Ayşe
        $r1 = LeaveRequest::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $staff[0]->id,
            'leave_type_id' => $yillik->id,
            'start_date' => now()->addDays(10)->toDateString(),
            'end_date' => now()->addDays(14)->toDateString(),
            'total_days' => 5,
            'reason' => 'Aile ziyareti',
        ]);
        $svc->submit($r1, $staff[0]->user);

        // 2. Approved — Mehmet
        $r2 = LeaveRequest::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $staff[1]->id,
            'leave_type_id' => $yillik->id,
            'start_date' => now()->subDays(20)->toDateString(),
            'end_date' => now()->subDays(18)->toDateString(),
            'total_days' => 3,
            'reason' => 'Kısa tatil',
        ]);
        $svc->submit($r2, $staff[1]->user);
        $svc->approve($r2->approval, $manager->user);

        // 3. Rejected — Zeynep
        $r3 = LeaveRequest::create([
            'tenant_id' => $tenant->id,
            'employee_id' => $staff[2]->id,
            'leave_type_id' => $yillik->id,
            'start_date' => now()->addDays(30)->toDateString(),
            'end_date' => now()->addDays(35)->toDateString(),
            'total_days' => 6,
            'reason' => 'Yaz tatili',
        ]);
        $svc->submit($r3, $staff[2]->user);
        $svc->reject($r3->approval, $manager->user, 'Yoğun dönem, sonraya erteleyelim.');
    }

    private function seedHolidaysAndCriticalDates(Tenant $tenant, Employee $manager): void
    {
        $year = date('Y');
        foreach ([
            "{$year}-04-23" => 'Ulusal Egemenlik ve Çocuk Bayramı',
            "{$year}-05-19" => 'Atatürk\'ü Anma, Gençlik ve Spor Bayramı',
            "{$year}-10-29" => 'Cumhuriyet Bayramı',
        ] as $date => $name) {
            Holiday::updateOrCreate(
                ['tenant_id' => $tenant->id, 'date' => $date],
                ['name' => $name, 'is_recurring_yearly' => true],
            );
        }

        CriticalDate::updateOrCreate(
            ['tenant_id' => $tenant->id, 'name' => 'Yıl Sonu Envanter'],
            [
                'start_date' => "{$year}-12-25",
                'end_date' => "{$year}-12-31",
                'description' => 'Yıl sonu envanter sayımı — bu dönemde izin verilmez.',
                'block_leave_requests' => true,
                'created_by' => $manager->user_id,
                'scope' => 'tenant_wide',
            ],
        );
    }
}
