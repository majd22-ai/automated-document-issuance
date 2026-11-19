@php
    $residenceLocations = app(\App\Services\ResidenceLocations::class);
    $residenceCountries = array_filter($residenceLocations->countries(), fn ($country) => array_key_exists($country['code'], config('residence_phones')));
    $residenceCountry = old('data.residence_country', data_get($form->data, 'residence_country', ''));
    $residenceCountry = is_string($residenceCountry) ? $residenceCountry : '';
    $residenceCode = $residenceLocations->countryCode($residenceCountry);
    $residenceGovernorate = old('data.residence_governorate', data_get($form->data, 'residence_governorate', ''));
    $residenceDistrict = old('data.residence_district', data_get($form->data, 'residence_district', ''));
    $residenceGovernorate = is_string($residenceGovernorate) ? $residenceGovernorate : '';
    $residenceDistrict = is_string($residenceDistrict) ? $residenceDistrict : '';
@endphp
<div class="identity-field"><label for="field-residence_country">دولة الإقامة *</label>
<select id="field-residence_country" name="data[residence_country]" data-residence-country required @error('data.residence_country') aria-invalid="true" @enderror>
<option value="">اختر دولة الإقامة أولًا</option>
@if($residenceCountry && !array_key_exists($residenceCode ?? '', config('residence_phones')))<option value="{{ $residenceCountry }}" data-country-code="{{ $residenceCode }}" selected>{{ $residenceCountry }} — قيمة محفوظة</option>@endif
@foreach($residenceCountries as $country)<option value="{{ $country['name'] }}" data-country-code="{{ $country['code'] }}" @selected($residenceCode === $country['code'])>{{ $country['name'] }}</option>@endforeach
</select>@error('data.residence_country')<p class="identity-field-error">{{ $message }}</p>@enderror</div>
<div class="identity-field"><label for="field-residence_governorate-manual">محافظة الإقامة / المنطقة *</label>
<select id="field-residence_governorate" data-residence-governorate hidden disabled data-selected="{{ $residenceGovernorate }}" required><option value="">اختر الدولة أولًا</option></select>
<input id="field-residence_governorate-manual" name="data[residence_governorate]" value="{{ $residenceGovernorate }}" data-residence-governorate-manual maxlength="200" aria-label="محافظة الإقامة / المنطقة" required>
@error('data.residence_governorate')<p class="identity-field-error">{{ $message }}</p>@enderror</div>
<div class="identity-field"><label for="field-residence_district-manual">مديرية الإقامة / التقسيم الإداري *</label>
<select id="field-residence_district" data-residence-district hidden disabled data-selected="{{ $residenceDistrict }}" required><option value="">اختر المحافظة أولًا</option></select>
<input id="field-residence_district-manual" name="data[residence_district]" value="{{ $residenceDistrict }}" data-residence-district-manual maxlength="200" aria-label="مديرية الإقامة / التقسيم الإداري" required>
@error('data.residence_district')<p class="identity-field-error">{{ $message }}</p>@enderror</div>
<p class="birth-help" data-residence-status role="status">اختر الدولة ثم المحافظة ثم المديرية. قد تختلف تسمية التقسيم الإداري من دولة إلى أخرى.</p>
@push('scripts')<script src="{{ asset('js/residence-country-selects.js') }}?v={{ filemtime(public_path('js/residence-country-selects.js')) }}" defer></script>@endpush
