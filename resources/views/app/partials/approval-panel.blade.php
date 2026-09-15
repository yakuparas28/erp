{{-- Tekrar kullanılabilir onay paneli.
     Beklenen değişkenler:
       $required        bool   — akış aktif mi (threshold aşıldı mı)
       $approval        ?Approval — mevcut onay kaydı (null = henüz gönderilmedi)
       $canApprove      bool   — bu kullanıcı onaylayabilir mi (Tenant Admin vb.)
       $canSubmit       bool   — bu kullanıcı onaya gönderebilir mi
       $title           string — panel başlığı
       $submitRoute     string — POST → onaya gönder
       $approveRoute    ?string — POST → onayla
       $rejectRoute     ?string — POST → reddet
--}}
@if ($required)
    <div class="bg-white border border-border-color rounded-md p-4 mt-4">
        <h2 class="text-base font-bold text-title mb-2 inline-flex items-center gap-2">
            <i class="ph ph-shield-check"></i> {{ $title }}
        </h2>
        <p class="text-[12px] text-default mb-3">
            {{ __('This document exceeds the approval threshold and must be approved before it can proceed.') }}
        </p>

        @if ($approval === null)
            @if ($canSubmit)
                <form method="POST" action="{{ $submitRoute }}">
                    @csrf
                    <button type="submit" class="btn-sm bg-warning text-white border border-warning hover:bg-warning/90 cursor-pointer inline-flex items-center gap-2">
                        <i class="ph ph-paper-plane-tilt"></i> {{ __('Submit for Approval') }}
                    </button>
                </form>
            @else
                <span class="text-[12px] text-default">{{ __('Waiting for someone to submit this for approval.') }}</span>
            @endif
        @elseif ($approval->status === 'pending')
            <div class="flex items-center gap-2 mb-3">
                <span class="text-[11px] bg-warning-transparent text-warning border border-warning px-2 py-0.5 rounded inline-flex items-center gap-1">
                    <i class="ph ph-clock"></i> {{ __('Awaiting approval') }}
                </span>
                <span class="text-[12px] text-default">{{ __('Submitted') }} {{ $approval->submitted_at?->diffForHumans() }}</span>
            </div>
            @if ($canApprove && $approveRoute && $rejectRoute)
                <div class="flex items-center gap-2">
                    <form method="POST" action="{{ $approveRoute }}">
                        @csrf
                        <button type="submit" class="btn-sm bg-success text-white border border-success hover:bg-success/90 cursor-pointer inline-flex items-center gap-1">
                            <i class="ph ph-check"></i> {{ __('Approve') }}
                        </button>
                    </form>
                    <form method="POST" action="{{ $rejectRoute }}">
                        @csrf
                        <button type="submit" class="btn-sm bg-white border border-danger text-danger hover:bg-danger hover:text-white cursor-pointer inline-flex items-center gap-1">
                            <i class="ph ph-x"></i> {{ __('Reject') }}
                        </button>
                    </form>
                </div>
            @endif
        @elseif ($approval->status === 'approved')
            <span class="text-[11px] bg-success-transparent text-success border border-success px-2 py-0.5 rounded inline-flex items-center gap-1">
                <i class="ph ph-check-circle"></i> {{ __('Approved') }} · {{ $approval->decided_at?->diffForHumans() }}
            </span>
        @elseif ($approval->status === 'rejected')
            <span class="text-[11px] bg-danger-transparent text-danger border border-danger px-2 py-0.5 rounded inline-flex items-center gap-1">
                <i class="ph ph-x-circle"></i> {{ __('Rejected') }} · {{ $approval->decided_at?->diffForHumans() }}
            </span>
        @endif
    </div>
@endif
