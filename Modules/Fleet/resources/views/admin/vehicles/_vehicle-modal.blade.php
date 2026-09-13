@php use Modules\Fleet\Models\Vehicle; @endphp
<div id="vehicle-modal" class="hs-overlay hidden fixed top-0 start-0 w-full h-full z-[70] overflow-x-hidden overflow-y-auto pointer-events-none">
    <div class="hs-overlay-open:opacity-100 hs-overlay-open:duration-500 opacity-0 transition-all ease-out mt-7 lg:mt-10 opacity-0 ease-out transition-all sm:max-w-2xl sm:w-full m-3 sm:mx-auto min-h-[calc(100%-56px)] flex items-center">
        <div class="w-full bg-white border shadow-sm rounded-xl pointer-events-auto">
            <div class="flex justify-between items-center py-3 px-4 border-b">
                <h3 class="font-bold text-gray-800">{{ __('Vehicle') }}</h3>
                <button type="button" class="size-8 inline-flex items-center justify-center gap-x-2 rounded-full border border-transparent bg-gray-100" data-hs-overlay="#vehicle-modal"><i class="ph ph-x"></i></button>
            </div>
            <form id="vehicle-form" method="POST" action="{{ route('app.fleet.vehicles.store') }}">
                @csrf
                <input type="hidden" name="_method" value="POST">
                <div class="p-4 grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div><label class="text-xs text-default">{{ __('Plate') }}</label><input name="plaka" required maxlength="16" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white"></div>
                    <div><label class="text-xs text-default">{{ __('Brand & Model') }}</label><input name="marka_model" required maxlength="128" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white"></div>
                    <div><label class="text-xs text-default">{{ __('Year') }}</label><input name="yil" type="number" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white"></div>
                    <div><label class="text-xs text-default">{{ __('Current KM') }}</label><input name="guncel_km" type="number" required min="0" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white"></div>
                    <div><label class="text-xs text-default">Şasi No</label><input name="sasi_no" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white"></div>
                    <div><label class="text-xs text-default">Motor No</label><input name="motor_no" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white"></div>
                    <div><label class="text-xs text-default">{{ __('Maintenance') }}</label><input name="bakim_tarihi" type="date" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white"></div>
                    <div><label class="text-xs text-default">{{ __('Inspection') }}</label><input name="muayene_tarihi" type="date" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white"></div>
                    <div><label class="text-xs text-default">{{ __('Status') }}</label>
                        <select name="durum" required class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white">
                            <option value="{{ Vehicle::DURUM_GARAJDA }}">{{ __('vehicle-status.Garajda') }}</option>
                            <option value="{{ Vehicle::DURUM_AKTIF }}">{{ __('vehicle-status.Aktif_Kullanimda') }}</option>
                            <option value="{{ Vehicle::DURUM_BLOKELI }}">{{ __('vehicle-status.Blokeli_Bakimda') }}</option>
                        </select>
                    </div>
                    <div class="md:col-span-2 border-t border-border-color pt-3 mt-1"><div class="font-semibold text-sm mb-2">MTV</div></div>
                    <div><label class="text-xs text-default">MTV Ödeme Tarihi</label><input name="mtv_odeme_tarihi" type="date" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white"></div>
                    <div><label class="text-xs text-default">MTV Durumu</label>
                        <select name="mtv_odeme_durumu" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white">
                            <option value="{{ Vehicle::MTV_ODENMEDI }}">{{ __('mtv.Odenmedi') }}</option>
                            <option value="{{ Vehicle::MTV_KISMI }}">{{ __('mtv.Kismi_Odendi') }}</option>
                            <option value="{{ Vehicle::MTV_ODENDI }}">{{ __('mtv.Odendi') }}</option>
                        </select>
                    </div>
                    <div class="md:col-span-2"><label class="text-xs text-default">MTV Notu</label><textarea name="mtv_odeme_notu" rows="2" maxlength="2000" class="w-full px-3 py-2 text-sm border border-border-color rounded-md bg-white"></textarea></div>
                </div>
                <div class="flex justify-end items-center gap-2 py-3 px-4 border-t">
                    <button type="button" class="btn-sm border border-border-color" data-hs-overlay="#vehicle-modal">{{ __('Cancel') }}</button>
                    <button class="btn-sm bg-primary text-white">{{ __('Save') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
