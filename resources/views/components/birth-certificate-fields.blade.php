@php($values = old('data', $draft->payload['data'] ?? []))
@php($values = is_array($values) ? $values : [])
<p>الحقول ذات العلامة (*) مطلوبة. لا يُعد الحفظ مسودة تقديمًا للطلب.</p>
<div class="form-group">
    <label class="form-label" for="request-child_name">اسم المولود كاملًا *</label>
    <input class="form-control" id="request-child_name" name="data[child_name]" maxlength="200" value="{{ $values['child_name'] ?? '' }}" required>
</div>
<div class="form-group">
    <label class="form-label" for="request-child_gender">جنس المولود *</label>
    <select class="form-control" id="request-child_gender" name="data[child_gender]" required>
        <option value="">اختر</option>
        <option value="ذكر" @selected(($values['child_gender'] ?? '') === 'ذكر')>ذكر</option>
        <option value="أنثى" @selected(($values['child_gender'] ?? '') === 'أنثى')>أنثى</option>
    </select>
</div>
@foreach([
    'birth_date' => ['تاريخ الميلاد', 'date', true],
    'birth_place' => ['مكان الميلاد', 'text', true],
    'father_name' => ['اسم الأب كاملًا', 'text', true],
    'father_national_id' => ['رقم هوية الأب', 'text', true],
    'mother_name' => ['اسم الأم كاملًا', 'text', true],
    'mother_national_id' => ['رقم هوية الأم', 'text', true],
    'birth_record_number' => ['رقم واقعة الميلاد', 'text', true],
    'hospital_name' => ['المستشفى أو المركز الصحي (اختياري)', 'text', false],
    'birth_record_date' => ['تاريخ تسجيل واقعة الميلاد (اختياري)', 'date', false],
] as $key => [$label, $type, $required])
    <div class="form-group">
        <label class="form-label" for="request-{{ $key }}">{{ $label }}{{ $required ? ' *' : '' }}</label>
        <input class="form-control" id="request-{{ $key }}" name="data[{{ $key }}]" type="{{ $type }}" value="{{ $values[$key] ?? '' }}" @if($type === 'text') maxlength="{{ $key === 'birth_record_number' ? 100 : (str_contains($key, 'national_id') ? 50 : 200) }}" @endif @required($required)>
    </div>
@endforeach
<p><a class="btn btn-outline" href="{{ route('citizen.services.attachments.index', $service) }}">رفع إشعار الميلاد وإثبات هوية الوالدين</a></p>
