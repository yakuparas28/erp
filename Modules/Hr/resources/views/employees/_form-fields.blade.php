<div class="p-4 border-b border-border-color">
    <h3 class="text-base font-bold text-title mb-0">{{ $employee ? __('Edit Employee') : __('New Employee') }}</h3>
</div>
<div class="p-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
    <div class="sm:col-span-2">
        <label class="block text-sm text-gray-900 mb-1">{{ __('System User') }} <span class="text-danger">*</span></label>
        <select name="user_id" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0" required>
            <option value="">{{ __('Select user') }}</option>
            @foreach ($availableUsers as $u)
                <option value="{{ $u->id }}" @selected($employee?->user_id === $u->id)>{{ $u->name }} — {{ $u->email }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-sm text-gray-900 mb-1">{{ __('First Name') }} <span class="text-danger">*</span></label>
        <input type="text" name="first_name" value="{{ old('first_name', $employee?->first_name) }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0" required maxlength="100">
    </div>
    <div>
        <label class="block text-sm text-gray-900 mb-1">{{ __('Last Name') }} <span class="text-danger">*</span></label>
        <input type="text" name="last_name" value="{{ old('last_name', $employee?->last_name) }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0" required maxlength="100">
    </div>
    <div>
        <label class="block text-sm text-gray-900 mb-1">{{ __('Title') }}</label>
        <input type="text" name="title" value="{{ old('title', $employee?->title) }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0" maxlength="100">
    </div>
    <div>
        <label class="block text-sm text-gray-900 mb-1">{{ __('Department') }}</label>
        <select name="department_id" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
            <option value="">—</option>
            @foreach ($departments as $d)
                <option value="{{ $d->id }}" @selected($employee?->department_id === $d->id)>{{ $d->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-sm text-gray-900 mb-1">{{ __('Manager') }}</label>
        <select name="manager_id" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
            <option value="">—</option>
            @foreach ($managers as $m)
                <option value="{{ $m->id }}" @selected($employee?->manager_id === $m->id)>{{ $m->full_name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-sm text-gray-900 mb-1">{{ __('Hire Date') }}</label>
        <input type="date" name="hire_date" value="{{ old('hire_date', $employee?->hire_date?->format('Y-m-d')) }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
    </div>
    <div>
        <label class="block text-sm text-gray-900 mb-1">{{ __('Birth Date') }}</label>
        <input type="date" name="birth_date" value="{{ old('birth_date', $employee?->birth_date?->format('Y-m-d')) }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
    </div>
    <div>
        <label class="block text-sm text-gray-900 mb-1">{{ __('National ID') }}</label>
        <input type="text" name="national_id" value="{{ old('national_id', $employee?->national_id) }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0" maxlength="20">
    </div>
    <div>
        <label class="block text-sm text-gray-900 mb-1">{{ __('Mobile') }}</label>
        <input type="text" name="mobile" value="{{ old('mobile', $employee?->mobile) }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0" maxlength="30">
    </div>
    <div>
        <label class="block text-sm text-gray-900 mb-1">{{ __('Annual Leave Balance') }}</label>
        <input type="number" step="0.5" min="0" name="annual_leave_balance" value="{{ old('annual_leave_balance', $employee?->annual_leave_balance ?? 14) }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
    </div>
    <div class="flex items-center gap-2 pt-6">
        <input type="hidden" name="second_level_approval_required" value="0">
        <input type="checkbox" name="second_level_approval_required" value="1" @checked($employee?->second_level_approval_required) id="sla-{{ $employee?->id ?? 'new' }}">
        <label for="sla-{{ $employee?->id ?? 'new' }}" class="text-sm text-gray-900">{{ __('Second-level approval required') }}</label>
    </div>
    @if ($employee)
        <div class="flex items-center gap-2 pt-6">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked($employee->is_active) id="act-{{ $employee->id }}">
            <label for="act-{{ $employee->id }}" class="text-sm text-gray-900">{{ __('Active') }}</label>
        </div>
    @endif
</div>
