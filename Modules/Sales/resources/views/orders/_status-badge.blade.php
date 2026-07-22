<span class="text-[11px] {{ match ($status) {
    'draft' => 'bg-light text-default',
    'quotation_sent' => 'bg-warning-transparent text-warning',
    'confirmed' => 'bg-info-transparent text-info',
    'done' => 'bg-success-transparent text-success',
    'cancelled' => 'bg-danger-transparent text-danger',
    default => 'bg-light text-default',
} }} px-2 py-0.5 rounded">
    {{ __('so-status.'.$status) }}
</span>
