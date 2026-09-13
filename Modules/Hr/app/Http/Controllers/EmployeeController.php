<?php

namespace Modules\Hr\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        $employee = \DB::transaction(function () use ($validated, $request): Employee {
            $tenantId = $request->user()->tenant_id;

            $user = new User([
                'name' => "{$validated['first_name']} {$validated['last_name']}",
                'email' => $validated['email'],
                'password' => $validated['temp_password'],
            ]);
            $user->tenant_id = $tenantId;
            $user->save();
            setPermissionsTeamId($tenantId);
            $user->assignRole('Tenant Admin'); // Personel varsayılan olarak platform kullanıcısı sayılır; rol Yönetim > Kullanıcılar'dan sonra düzenlenebilir.

            unset($validated['email'], $validated['temp_password']);

            return Employee::create($validated + ['tenant_id' => $tenantId, 'user_id' => $user->id]);
        });

        return redirect()->route('app.hr.employees.index')
            ->with('status', __(':name added. Temporary password: :password', [
                'name' => $employee->full_name,
                'password' => $request->input('temp_password'),
            ]));
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
        ]);
    }
}
