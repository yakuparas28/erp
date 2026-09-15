{{-- Yeni çek/senet ekle modalı. --}}
<div id="cn-modal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 p-4" role="dialog">
    <div class="bg-white rounded-md shadow-xl w-full max-h-[92vh] overflow-hidden" style="max-width: min(640px, calc(100vw - 32px));">
        <form method="POST" action="{{ route('app.accounting.checks-and-notes.store') }}">
            @csrf
            <input type="hidden" name="direction" value="{{ $direction }}">

            <div class="flex items-center justify-between border-b border-border-color px-4 py-3">
                <h2 class="text-base font-bold text-title inline-flex items-center gap-2">
                    <i class="ph ph-note"></i>
                    {{ $direction === 'incoming' ? __('New Incoming Instrument') : __('New Outgoing Instrument') }}
                </h2>
                <button type="button" data-cn-close class="text-default hover:text-gray-900 cursor-pointer">
                    <i class="ph ph-x text-lg"></i>
                </button>
            </div>

            <div class="p-4 overflow-y-auto space-y-3">
                @error('note')
                    <div class="bg-danger-transparent text-danger border border-danger rounded-md px-3 py-2 text-sm">{{ $message }}</div>
                @enderror

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-[12px] text-default block mb-1">{{ __('Type') }} *</label>
                        <select name="instrument_type" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                            <option value="check">{{ __('Check') }}</option>
                            <option value="promissory_note">{{ __('Promissory Note') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-[12px] text-default block mb-1">{{ __('Instrument No') }} *</label>
                        <input type="text" name="instrument_no" required maxlength="64"
                               class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0 font-mono">
                    </div>
                </div>

                <div>
                    <label class="text-[12px] text-default block mb-1">{{ $direction === 'incoming' ? __('From (Customer)') : __('To (Supplier)') }} *</label>
                    <select name="partner_id" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                        @foreach ($partners as $partner)
                            <option value="{{ $partner->id }}">{{ $partner->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div class="col-span-2">
                        <label class="text-[12px] text-default block mb-1">{{ __('Drawer / Debtor') }}</label>
                        <input type="text" name="drawer_name" maxlength="128"
                               class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                    </div>
                    <div>
                        <label class="text-[12px] text-default block mb-1">{{ __('Currency') }}</label>
                        <select name="currency_id" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                            <option value="">TRY</option>
                            @foreach ($currencies as $currency)
                                <option value="{{ $currency->id }}">{{ $currency->code }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-[12px] text-default block mb-1">{{ __('Drawee Bank') }}</label>
                        <input type="text" name="drawee_bank_name" maxlength="128"
                               class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                    </div>
                    <div>
                        <label class="text-[12px] text-default block mb-1">{{ __('Branch') }}</label>
                        <input type="text" name="drawee_branch" maxlength="128"
                               class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="text-[12px] text-default block mb-1">{{ __('Issue Date') }} *</label>
                        <input type="date" name="issue_date" required value="{{ now()->toDateString() }}"
                               class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                    </div>
                    <div>
                        <label class="text-[12px] text-default block mb-1">{{ __('Maturity Date') }} *</label>
                        <input type="date" name="maturity_date" required value="{{ now()->addMonth()->toDateString() }}"
                               class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                    </div>
                    <div>
                        <label class="text-[12px] text-default block mb-1">{{ __('Amount') }} *</label>
                        <input type="number" step="0.01" min="0.01" name="amount" required
                               class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0 text-right">
                    </div>
                </div>

                <div>
                    <label class="text-[12px] text-default block mb-1">{{ __('Notes') }}</label>
                    <textarea name="notes" rows="2" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0"></textarea>
                </div>
            </div>

            <div class="border-t border-border-color px-4 py-3 flex items-center justify-end gap-2">
                <button type="button" data-cn-close class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer">
                    {{ __('Cancel') }}
                </button>
                <button type="submit" class="btn-sm bg-primary text-white border border-primary hover:bg-primary/90 cursor-pointer inline-flex items-center gap-2">
                    <i class="ph ph-check"></i> {{ __('Save') }}
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const modal = document.getElementById('cn-modal');
    if (!modal) return;
    const open = () => { modal.classList.remove('hidden'); modal.classList.add('flex'); };
    const close = () => { modal.classList.remove('flex'); modal.classList.add('hidden'); };
    document.querySelectorAll('[data-cn-open-add]').forEach((b) => b.addEventListener('click', open));
    document.querySelectorAll('[data-cn-close]').forEach((b) => b.addEventListener('click', close));
    modal.addEventListener('click', (e) => { if (e.target === modal) close(); });
})();
</script>
