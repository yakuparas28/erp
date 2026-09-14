@php use Modules\Inventory\Models\Partner; @endphp
<div id="{{ $id }}" class="hs-overlay hidden size-full fixed top-0 start-0 z-[80] overflow-x-hidden overflow-y-auto pointer-events-none">
    <div class="hs-overlay-open:mt-7 hs-overlay-open:opacity-100 hs-overlay-open:duration-500 mt-0 opacity-0 ease-out transition-all sm:max-w-2xl sm:w-full m-3 sm:mx-auto pointer-events-auto min-h-screen flex items-center justify-center" style="max-width: min(900px, calc(100vw - 32px));">
        <form method="POST" action="{{ $action }}" class="flex flex-col bg-white border shadow-sm rounded-md border-border-color w-full">
            @csrf
            @if ($method === 'PUT') @method('PUT') @endif
            <div class="flex justify-between items-center p-4 border-b border-border-color">
                <h2 class="text-base font-bold text-title">{{ $title }}</h2>
                <button type="button" class="size-7 inline-flex justify-center items-center rounded-md border border-border-color hover:bg-light cursor-pointer" data-hs-overlay="#{{ $id }}" aria-label="{{ __('Cancel') }}"><i class="ph ph-x text-sm"></i></button>
            </div>

            <div class="p-4 border-b border-border-color">
                <nav class="flex flex-wrap gap-1" role="tablist">
                    @foreach (['basic' => ['Basic Info','ph-identification-card'], 'legal' => ['Tax & e-Invoice','ph-scales'], 'contact' => ['Contact & Address','ph-map-pin'], 'financial' => ['Financial','ph-wallet']] as $tab => [$label, $icon])
                        <button type="button" class="hs-tab-active:bg-dark hs-tab-active:text-white btn-sm border border-border-color inline-flex items-center gap-2 cursor-pointer hover:bg-light {{ $tab === 'basic' ? 'active bg-dark text-white' : 'bg-white text-gray-700' }}"
                            id="{{ $id }}-tab-{{ $tab }}"
                            data-hs-tab="#{{ $id }}-panel-{{ $tab }}"
                            aria-controls="{{ $id }}-panel-{{ $tab }}" role="tab">
                            <i class="ph {{ $icon }}"></i> {{ __($label) }}
                        </button>
                    @endforeach
                </nav>
            </div>

            <div class="p-4 max-h-[60vh] overflow-y-auto">
                {{-- 1) Temel Bilgiler --}}
                <div id="{{ $id }}-panel-basic" role="tabpanel" aria-labelledby="{{ $id }}-tab-basic">
                    <div class="grid grid-cols-12 gap-3">
                        <div class="col-span-12 sm:col-span-4">
                            <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Partner Code') }}</label>
                            <input type="text" name="partner_code" maxlength="32" value="{{ $partner?->partner_code }}" placeholder="MUS001" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white font-mono focus:outline-none focus:ring-0">
                        </div>
                        <div class="col-span-12 sm:col-span-8">
                            <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Name / Title') }} <span class="text-danger">*</span></label>
                            <input type="text" name="name" required maxlength="255" value="{{ $partner?->name }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                        </div>
                        <div class="col-span-12 sm:col-span-4">
                            <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Entity Type') }}</label>
                            <select name="entity_type" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                                <option value="{{ Partner::ENTITY_COMPANY }}" @selected(($partner?->entity_type ?? Partner::ENTITY_COMPANY) === Partner::ENTITY_COMPANY)>{{ __('Company') }}</option>
                                <option value="{{ Partner::ENTITY_INDIVIDUAL }}" @selected($partner?->entity_type === Partner::ENTITY_INDIVIDUAL)>{{ __('Individual') }}</option>
                            </select>
                        </div>
                        <div class="col-span-12 sm:col-span-4">
                            <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Group / Special Code') }}</label>
                            <input type="text" name="group_code" maxlength="64" value="{{ $partner?->group_code }}" placeholder="Bölge / Sektör" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                        </div>
                        <div class="col-span-12 sm:col-span-4">
                            <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Status') }}</label>
                            <label class="inline-flex items-center gap-2 text-sm mt-2">
                                <input type="hidden" name="is_active" value="0">
                                <input type="checkbox" id="is_active_{{ $id }}" name="is_active" value="1" class="size-4 rounded border-border-color" @checked(($partner?->is_active ?? true))>
                                <span>{{ __('Active') }}</span>
                            </label>
                        </div>
                        <div class="col-span-12 flex items-center gap-6 border-t border-border-color pt-3">
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input type="hidden" name="is_customer" value="0">
                                <input type="checkbox" id="is_customer_{{ $id }}" name="is_customer" value="1" class="size-4 rounded border-border-color" @checked($partner?->is_customer)>
                                <span class="inline-flex items-center gap-1"><i class="ph ph-user text-info"></i> {{ __('Customer') }}</span>
                            </label>
                            <label class="inline-flex items-center gap-2 text-sm">
                                <input type="hidden" name="is_supplier" value="0">
                                <input type="checkbox" id="is_supplier_{{ $id }}" name="is_supplier" value="1" class="size-4 rounded border-border-color" @checked($partner?->is_supplier)>
                                <span class="inline-flex items-center gap-1"><i class="ph ph-truck text-warning"></i> {{ __('Supplier') }}</span>
                            </label>
                        </div>
                    </div>
                </div>

                {{-- 2) Vergi & e-Belge --}}
                <div id="{{ $id }}-panel-legal" role="tabpanel" aria-labelledby="{{ $id }}-tab-legal" class="hidden">
                    <div class="grid grid-cols-12 gap-3">
                        <div class="col-span-12 sm:col-span-6">
                            <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Tax Office') }}</label>
                            <input type="text" name="tax_office" maxlength="128" value="{{ $partner?->tax_office }}" placeholder="Kadıköy" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                        </div>
                        <div class="col-span-12 sm:col-span-6">
                            <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Tax Number (VKN)') }}</label>
                            <input type="text" name="tax_number" maxlength="32" value="{{ $partner?->tax_number }}" placeholder="10 haneli" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white font-mono focus:outline-none focus:ring-0">
                        </div>
                        <div class="col-span-12 sm:col-span-6">
                            <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('National ID (TCKN)') }}</label>
                            <input type="text" name="national_id" maxlength="32" value="{{ $partner?->national_id }}" placeholder="11 haneli" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white font-mono focus:outline-none focus:ring-0">
                            <p class="text-[10px] text-default mt-1">{{ __('Fill only for individuals') }}</p>
                        </div>
                        <div class="col-span-12 border-t border-border-color pt-3">
                            <h3 class="text-sm font-bold text-title mb-2">{{ __('e-Invoice / e-Archive') }}</h3>
                        </div>
                        <div class="col-span-12 sm:col-span-6">
                            <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('e-Invoice Status') }}</label>
                            <select name="e_invoice_status" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                                <option value="{{ Partner::EINVOICE_NONE }}" @selected(($partner?->e_invoice_status ?? Partner::EINVOICE_NONE) === Partner::EINVOICE_NONE)>{{ __('Not registered') }}</option>
                                <option value="{{ Partner::EINVOICE_ARSIV }}" @selected($partner?->e_invoice_status === Partner::EINVOICE_ARSIV)>{{ __('e-Arşiv') }}</option>
                                <option value="{{ Partner::EINVOICE_FATURA }}" @selected($partner?->e_invoice_status === Partner::EINVOICE_FATURA)>{{ __('e-Fatura') }}</option>
                            </select>
                        </div>
                        <div class="col-span-12 sm:col-span-6">
                            <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('e-Invoice Alias / Mailbox') }}</label>
                            <input type="text" name="e_invoice_alias" maxlength="128" value="{{ $partner?->e_invoice_alias }}" placeholder="urn:mail:defaultpk@..." class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                        </div>
                    </div>
                </div>

                {{-- 3) İletişim & Adres --}}
                <div id="{{ $id }}-panel-contact" role="tabpanel" aria-labelledby="{{ $id }}-tab-contact" class="hidden">
                    <div class="grid grid-cols-12 gap-3">
                        <div class="col-span-12 sm:col-span-6">
                            <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Contact Person') }}</label>
                            <input type="text" name="contact_person" maxlength="128" value="{{ $partner?->contact_person }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                        </div>
                        <div class="col-span-12 sm:col-span-6">
                            <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Email') }}</label>
                            <input type="email" name="email" maxlength="255" value="{{ $partner?->email }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                        </div>
                        <div class="col-span-12 sm:col-span-6">
                            <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Phone') }}</label>
                            <input type="text" name="phone" maxlength="64" value="{{ $partner?->phone }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                        </div>
                        <div class="col-span-12 sm:col-span-6">
                            <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Fax') }}</label>
                            <input type="text" name="fax" maxlength="32" value="{{ $partner?->fax }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                        </div>
                        <div class="col-span-12 border-t border-border-color pt-3">
                            <h3 class="text-sm font-bold text-title mb-2">{{ __('Address') }}</h3>
                        </div>
                        <div class="col-span-12">
                            <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Address') }}</label>
                            <textarea name="address" rows="2" maxlength="1000" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">{{ $partner?->address }}</textarea>
                        </div>
                        <div class="col-span-12 sm:col-span-4">
                            <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Country') }}</label>
                            <input type="text" name="country" maxlength="64" value="{{ $partner?->country ?? 'Türkiye' }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                        </div>
                        <div class="col-span-12 sm:col-span-4">
                            <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('City') }}</label>
                            <input type="text" name="city" maxlength="64" value="{{ $partner?->city }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                        </div>
                        <div class="col-span-12 sm:col-span-4">
                            <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('District') }}</label>
                            <input type="text" name="district" maxlength="64" value="{{ $partner?->district }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                        </div>
                    </div>
                </div>

                {{-- 4) Finansal & Muhasebe --}}
                <div id="{{ $id }}-panel-financial" role="tabpanel" aria-labelledby="{{ $id }}-tab-financial" class="hidden">
                    <div class="grid grid-cols-12 gap-3">
                        <div class="col-span-12 sm:col-span-6">
                            <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Receivable Account Code') }}</label>
                            <input type="text" name="account_code_receivable" maxlength="32" value="{{ $partner?->account_code_receivable }}" placeholder="120.01.001" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white font-mono focus:outline-none focus:ring-0">
                            <p class="text-[10px] text-default mt-1">{{ __('120 Alıcılar hesap kodu (müşteri için)') }}</p>
                        </div>
                        <div class="col-span-12 sm:col-span-6">
                            <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Payable Account Code') }}</label>
                            <input type="text" name="account_code_payable" maxlength="32" value="{{ $partner?->account_code_payable }}" placeholder="320.01.001" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white font-mono focus:outline-none focus:ring-0">
                            <p class="text-[10px] text-default mt-1">{{ __('320 Satıcılar hesap kodu (tedarikçi için)') }}</p>
                        </div>
                        <div class="col-span-12 sm:col-span-4">
                            <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Currency') }}</label>
                            <select name="currency_code" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                                @foreach (['TRY', 'USD', 'EUR', 'GBP'] as $c)
                                    <option value="{{ $c }}" @selected(($partner?->currency_code ?? 'TRY') === $c)>{{ $c }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-span-12 sm:col-span-4">
                            <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Payment Term (days)') }}</label>
                            <input type="number" name="payment_term_days" min="0" value="{{ $partner?->payment_term_days ?? 0 }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                        </div>
                        <div class="col-span-12 sm:col-span-4">
                            <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Credit Limit') }}</label>
                            <input type="number" step="0.01" min="0" name="credit_limit" value="{{ $partner?->credit_limit ?? 0 }}" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">
                        </div>
                        <div class="col-span-12">
                            <label class="text-sm font-semibold text-gray-900 mb-1 block">{{ __('Notes') }}</label>
                            <textarea name="notes" rows="3" maxlength="2000" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white focus:outline-none focus:ring-0">{{ $partner?->notes }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-2 p-4 border-t border-border-color">
                <button type="button" class="btn-sm bg-white border border-border-color text-gray-900 hover:bg-light cursor-pointer" data-hs-overlay="#{{ $id }}">{{ __('Cancel') }}</button>
                <button type="submit" class="btn-sm bg-dark text-white border border-dark hover:bg-primary-hover cursor-pointer">{{ __('Save') }}</button>
            </div>
        </form>
    </div>
</div>
