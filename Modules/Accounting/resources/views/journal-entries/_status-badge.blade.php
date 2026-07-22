<span class="text-[11px] {{ match ($status) {
    'draft' => 'bg-light text-default',
    'posted' => 'bg-success-transparent text-success',
    'cancelled' => 'bg-danger-transparent text-danger',
    default => 'bg-light text-default',
} }} px-2 py-0.5 rounded">
    {{ __('journal-entry-status.'.$status) }}
</span>
