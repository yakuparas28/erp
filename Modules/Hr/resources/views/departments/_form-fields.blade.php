<div class="p-4 border-b border-border-color">
    <h3 class="text-base font-bold text-title mb-0">{{ $department ? __('Edit Department') : __('New Department') }}</h3>
</div>
<div class="p-4 space-y-3">
    <div>
        <label class="block text-sm text-gray-900 mb-1">{{ __('Name') }} <span class="text-danger">*</span></label>
        <input type="text" name="name" value="{{ old('name', $department?->name) }}" class="form-control" required maxlength="100">
    </div>
    <div>
        <label class="block text-sm text-gray-900 mb-1">{{ __('Parent') }}</label>
        <select name="parent_id" class="form-control">
            <option value="">—</option>
            @foreach ($departments as $d)
                <option value="{{ $d->id }}" @selected($department?->parent_id === $d->id)>{{ $d->name }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-sm text-gray-900 mb-1">{{ __('Manager') }}</label>
        <select name="manager_employee_id" class="form-control">
            <option value="">—</option>
            @foreach ($employees as $e)
                <option value="{{ $e->id }}" @selected($department?->manager_employee_id === $e->id)>{{ $e->full_name }}</option>
            @endforeach
        </select>
    </div>
    @if ($department)
        <div class="flex items-center gap-2">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked($department->is_active) id="dept-act-{{ $department->id }}">
            <label for="dept-act-{{ $department->id }}" class="text-sm text-gray-900">{{ __('Active') }}</label>
        </div>
    @endif
</div>
