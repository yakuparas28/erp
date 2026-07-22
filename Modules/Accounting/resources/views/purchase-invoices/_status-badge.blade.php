<span class="text-[11px] {{ match ($status) {
    'draft' => 'bg-light text-default',
    'posted' => 'bg-info-transparent text-info',
    'paid' => 'bg-success-transparent text-success',
    'cancelled' => 'bg-danger-transparent text-danger',
    default => 'bg-light text-default',
} }} px-2 py-0.5 rounded">
    {{ __('invoice-status.'.$status) }}
</span>
