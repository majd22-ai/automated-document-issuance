@extends('layouts.public')
@section('title', 'تجديد البطاقة الشخصية')
@push('styles')<link rel="stylesheet" href="{{ asset('css/identity-card-wizard.css') }}">@endpush
@push('scripts')<script src="{{ asset('js/identity-phones.js') }}?v={{ filemtime(public_path('js/identity-phones.js')) }}" defer></script>@endpush
@section('content')
<section class="section identity-wizard" data-identity-phones data-phone-codes='@json(config("residence_phones"))' data-phone-country="{{ app(\App\Services\ResidenceLocations::class)->countryCode(data_get($form->data, 'residence_country', '')) ?? 'YE' }}"><div class="container">
<h1>تجديد البطاقة الشخصية</h1><p>مرجع الاستمارة: <b dir="ltr">{{ $form->reference }}</b></p>
<p class="identity-notice">البطاقة السابقة ← تحديث العنوان والصورة ← مراجعة الموظف ← السداد ← الموعد والحضور والبصمة ← الاعتماد والإصدار ← التسليم</p>
@if(session('status'))<p class="identity-notice" role="status">{{ session('status') }}</p>@endif
@if($errors->any())<div class="identity-error" role="alert">@foreach($errors->all() as $message)<p>{{ $message }}</p>@endforeach</div>@endif
<div class="identity-panel"><h2>بيانات الهوية المسجلة</h2><p>الاسم: {{ $form->citizen->name }}</p><p>الرقم الوطني: {{ $form->citizen->national_id }}</p><p>تعديل بيانات الهوية الأساسية يحتاج إجراء تصحيح مستقل. التجديد العادي لا يتطلب شاهدين أو مصادقة العاقل.</p></div>
@if(!$form->submitted_at || $form->correctionIsOpen())
@if($form->correctionIsOpen())<p class="identity-error">سبب الاستكمال: {{ $form->correction_reason }}</p>@endif
<form action="{{ route('citizen.identity-renewal.save', $form) }}" method="post" enctype="multipart/form-data">@csrf<input type="hidden" name="version" value="{{ $form->version }}">
<div class="identity-panel identity-field"><label for="previous-document">البطاقة السابقة *</label><select id="previous-document" name="previous_document_id" required><option value="">اختر البطاقة المراد تجديدها</option>@foreach($documents as $document)<option value="{{ $document->id }}" @selected(old('previous_document_id', data_get($form->data,'previous_document_id')) == $document->id)>{{ $document->document_number }} — انتهاء الصلاحية: {{ $document->expires_at?->format('Y-m-d') }} — {{ $document->status }}</option>@endforeach</select>
@if($documents->isEmpty())<p>لا توجد بطاقة مرتبطة بحسابك. يلزم التحقق من سجل البطاقة وربطها قبل التجديد.</p>@endif</div>
<h2>العنوان والتواصل</h2><div class="identity-field-grid identity-panel" data-residence-cascade data-residence-url="{{ route('locations.residence', ['country'=>'ZZ']) }}">
@include('components.residence-country-selects')
@foreach(config('identity_card.fields.3') as $key=>$field)
@continue(in_array($key, ['residence_country','residence_governorate','residence_district']))
<div class="identity-field"><label for="renewal-{{ $key }}">{{ $field['label'] }} {{ $field['required'] ? '*' : '(اختياري)' }}</label>
@if(isset($field['options']))<select id="renewal-{{ $key }}" name="data[{{ $key }}]" @required($field['required'])><option value="">اختر</option>@foreach($field['options'] as $value=>$label)<option value="{{ $value }}" @selected(old('data.'.$key,data_get($form->data,$key)) == $value)>{{ $label }}</option>@endforeach</select>
@else<input id="renewal-{{ $key }}" name="data[{{ $key }}]" value="{{ old('data.'.$key,data_get($form->data,$key)) }}" @required($field['required']) maxlength="200">@endif</div>
@endforeach</div>
<div class="identity-panel"><h2>مرفقات التجديد</h2><label for="renewal-photo">صورة شخصية حديثة (JPG أو PNG، 600 × 600 على الأقل، بحد أقصى {{ config('identity_card.photo.max_kb') }} كيلوبايت)</label><input id="renewal-photo" type="file" name="photo" accept="image/jpeg,image/png">
@if($form->photo)<img class="identity-photo" src="{{ route('citizen.identity-card.photo',$form) }}" alt="الصورة الشخصية المحفوظة">@endif
<label for="previous-card">صورة البطاقة السابقة (JPG أو PNG أو PDF)</label><input id="previous-card" type="file" name="previous_card" accept="image/jpeg,image/png,application/pdf">
@if($copy)<p>صورة البطاقة السابقة محفوظة.</p>@endif
</div><button class="btn btn-primary" type="submit">حفظ البيانات والمرفقات</button></form>
@if($form->current_step === 7 && $form->photo && $copy)
<form class="identity-panel" action="{{ route('citizen.identity-card.submit',$form) }}" method="post">@csrf<input type="hidden" name="version" value="{{ $form->version }}"><input type="hidden" name="review_token" value="{{ $reviewToken }}"><h2>تأكيد التقديم</h2><p>تُرسل البيانات والمرفقات المحفوظة أعلاه. احفظ أي تعديلات قبل التأكيد.</p><label><input type="checkbox" name="declaration" value="1" required>أقر بصحة بيانات التجديد والمرفقات.</label><button class="btn btn-primary">تقديم طلب التجديد</button></form>
@endif
@else
<div class="identity-panel"><h2>تم تقديم طلب التجديد</h2><p>الحالة: {{ $form->application?->statusLabel() ?? $form->statusLabel() }}</p><a class="btn btn-primary" href="{{ route('citizen.applications.show',$form->citizen_service_request_id) }}">متابعة الطلب والسداد والموعد والتسليم</a></div>
@endif
</div></section>
@endsection
