<?php

namespace Modules\Hr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\TemplatedMail;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Mail\NotificationTemplateService;
use App\Services\Mail\TenantMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Modules\Hr\Models\Department;
use Modules\Hr\Models\Employee;
use Modules\Hr\Models\LeaveRequest;

/**
 * hr.employee ekranı: her tenant kullanıcısına 1-1 HR profili (departman,
 * yönetici, izin bakiyesi vb.) bağlar. Yeni employee için önce sistem
 * kullanıcısı (User) davet edilir (Yönetim → Kullanıcılar), sonra bu ekrandan
 * HR bilgileri girilir.
 */
class EmployeeController extends Controller
{
    public function __construct(
        private readonly NotificationTemplateService $templates,
        private readonly TenantMailer $mailer,
    ) {}

    public function index(): View
    {
        return view('hr::employees.index', [
            'employees' => Employee::with(['user', 'department', 'manager'])
                ->orderBy('first_name')->get(),
            'departments' => Department::orderBy('name')->get(),
            'managers' => Employee::orderBy('first_name')->get(),
        ]);
    }

    public function show(Employee $employee): View
    {
        $employee->load(['user', 'department', 'manager', 'subordinates.user']);

        $employee->load(['department', 'manager', 'user', 'subordinates']);

        return view('hr::employees.show', [
            'employee' => $employee,
            'recentLeaves' => LeaveRequest::where('employee_id', $employee->id)
                ->with(['leaveType', 'approval'])
                ->latest()
                ->limit(10)
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedForCreate($request);
        $tenantId = $request->user()->tenant_id;
        $tempPassword = $validated['temp_password'];

        [$employee, $user] = DB::transaction(function () use ($validated, $tenantId, $tempPassword): array {
            $user = new User([
                'name' => "{$validated['first_name']} {$validated['last_name']}",
                'email' => $validated['email'],
                'password' => $tempPassword,
            ]);
            $user->tenant_id = $tenantId;
            $user->save();

            // Personele yalnızca en az yetkili "Employee" rolü verilir; Tenant Admin
            // gibi yükseltmeler Yönetim > Kullanıcılar ekranından manuel yapılır.
            setPermissionsTeamId($tenantId);
            $user->assignRole('Employee');

            unset($validated['email'], $validated['temp_password']);
            $employee = Employee::create($validated + ['tenant_id' => $tenantId, 'user_id' => $user->id]);

            return [$employee, $user];
        });

        // Geçici şifre yalnızca yeni personele e-posta ile iletilir; oluşturan
        // kullanıcının session flash'ına veya loglara asla düşmez.
        $this->sendInvitation($request->user()->tenant, $user, $tempPassword);

        return redirect()->route('app.hr.employees.index')
            ->with('status', __(':name added; invitation email has been sent.', ['name' => $employee->full_name]));
    }

    private function sendInvitation(Tenant $tenant, User $user, string $tempPassword): void
    {
        try {
            $rendered = $this->templates->render('user_invitation', $tenant->id, [
                'kullanici_adi' => $user->name,
                'kullanici_email' => $user->email,
                'firma_adi' => $tenant->name,
                'gecici_sifre' => $tempPassword,
                'uygulama_adi' => config('app.name'),
            ]);
            $this->mailer->send($tenant->id, $user->email, new TemplatedMail($rendered['subject'], $rendered['body']));
        } catch (\Throwable $e) {
            // E-posta ayarı yoksa personel yine de oluşur; sysadmin ayarı sonradan yapıp Kullanıcılar ekranından şifre sıfırlayabilir.
            report($e);
        }
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $validated = $this->validatedForUpdate($request, $employee);

        $employee->update($validated);

        return redirect()->route('app.hr.employees.index')
            ->with('status', __(':name updated.', ['name' => $employee->full_name]));
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        $name = $employee->full_name;
        $employee->delete();

        return redirect()->route('app.hr.employees.index')
            ->with('status', __(':name deleted.', ['name' => $name]));
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedForCreate(Request $request): array
    {
        if (! $request->filled('temp_password')) {
            $request->merge(['temp_password' => Str::password(12)]);
        }

        $tenantId = $request->user()->tenant_id;

        return $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'title' => ['nullable', 'string', 'max:100'],
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'manager_id' => ['nullable', Rule::exists('employees', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'hire_date' => ['nullable', 'date'],
            'birth_date' => ['nullable', 'date'],
            'national_id' => ['nullable', 'string', 'max:20'],
            'mobile' => ['nullable', 'string', 'max:30'],
            'annual_leave_balance' => ['nullable', 'numeric', 'min:0'],
            'second_level_approval_required' => ['sometimes', 'boolean'],
            'temp_password' => ['required', 'string', 'min:8', 'max:64'],
            'gross_salary' => ['nullable', 'numeric', 'min:0'],
            'iban' => ['nullable', 'string', 'max:34'],
            'salary_expense_type' => ['nullable', Rule::in(['direct_labor', 'admin'])],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedForUpdate(Request $request, Employee $employee): array
    {
        $tenantId = $request->user()->tenant_id;

        return $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'title' => ['nullable', 'string', 'max:100'],
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'manager_id' => [
                'nullable',
                Rule::exists('employees', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)),
                Rule::notIn([$employee->id]),
            ],
            'hire_date' => ['nullable', 'date'],
            'birth_date' => ['nullable', 'date'],
            'termination_date' => ['nullable', 'date'],
            'national_id' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:30'],
            'mobile' => ['nullable', 'string', 'max:30'],
            'annual_leave_balance' => ['nullable', 'numeric', 'min:0'],
            'second_level_approval_required' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'gross_salary' => ['nullable', 'numeric', 'min:0'],
            'iban' => ['nullable', 'string', 'max:34'],
            'salary_expense_type' => ['nullable', Rule::in(['direct_labor', 'admin'])],
        ]);
    }
}
