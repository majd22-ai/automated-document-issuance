@php
    $locations = \App\Models\ResidenceGovernorate::with(['districts' => fn($query) => $query->orderBy('name')])->orderBy('name')->get(['id','name']);
    $byName = $byName ?? false;
    $governorateName = $governorateName ?? 'governorate_id';
    $districtName = $districtName ?? 'district_id';
    $selectedGovernorate = $selectedGovernorate ?? old('governorate_id');
    $selectedDistrict = $selectedDistrict ?? old('district_id');
    $prefix = $prefix ?? 'residence';
    $locationData = $locations->map(fn($location) => ['id'=>$location->id,'name'=>$location->name,'districts'=>$location->districts->map(fn($district) => ['id'=>$district->id,'name'=>$district->name])]);
@endphp
<div class="attestation-field"><label for="{{ $prefix }}-governorate">محافظة الإقامة *</label><select id="{{ $prefix }}-governorate" name="{{ $governorateName }}" data-location-governorate required><option value="">اختر المحافظة</option>@foreach($locations as $location)<option value="{{ $byName ? $location->name : $location->id }}" data-location-id="{{ $location->id }}" @selected((string)$selectedGovernorate === (string)($byName ? $location->name : $location->id))>{{ $location->name }}</option>@endforeach</select>@error($byName ? 'data.residence_governorate' : 'governorate_id')<p class="attestation-error">{{ $message }}</p>@enderror</div>
<div class="attestation-field"><label for="{{ $prefix }}-district">مديرية الإقامة *</label><select id="{{ $prefix }}-district" name="{{ $districtName }}" data-location-district data-selected="{{ $selectedDistrict }}" required><option value="">اختر المحافظة أولًا</option></select>@error($byName ? 'data.residence_district' : 'district_id')<p class="attestation-error">{{ $message }}</p>@enderror</div>
<script type="application/json" data-location-data>@json($locationData)</script>
@push('scripts')
@once
<script src="{{ asset('js/residence-selects.js') }}?v={{ filemtime(public_path('js/residence-selects.js')) }}" defer></script>
@endonce
@endpush
