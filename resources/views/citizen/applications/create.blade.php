@extends('layouts.public')
@section('title', 'بدء الخدمة - منصة الإصدار الآلي')
@section('content')
<section class="section"><div class="container"><div class="card" style="padding:1.5rem;">
  <h1>{{ $service->name }}</h1>
  @if($service->is_active && $service->rules_approved)<p><a class="btn btn-outline" href="{{ route('citizen.services.attachments.index', $service) }}">رفع الصور والمستندات ومعاينتها</a></p>@endif
  @if(isset($report))@include('components.requirements-result', ['requirementsResult' => $report->toArray()])@endif
  @if($reasons)
    <div class="alert alert-danger" role="alert"><h2>لا يمكن بدء الخدمة</h2><ul>@foreach($reasons as $reason)<li>{{ $reason }}</li>@endforeach</ul></div>
    <p>يمكن استكمال بيانات الاتصال من الملف الشخصي، أما التحقق الرسمي والشروط والموانع فتراجع لدى الجهة المختصة.</p>
  @else
    <h2>استوفيت فحوص الأهلية المسجلة حاليًا</h2>
    <p>الخدمة المحددة: {{ $service->name }} — الرمز: {{ $service->code }}</p>
    <p role="status">لم يتم إنشاء طلب بعد. أدخل البيانات ثم راجع الملخص وأقر بصحتها قبل الإرسال.</p>
    @if($errors->any())<div class="alert alert-danger" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <p>مقدم الطلب: {{ auth()->user()->name }} — الرقم الوطني: {{ auth()->user()->nationalIdentity() }}</p>
    <form method="POST" action="{{ route('citizen.services.preview', $service) }}">
      @csrf
      @foreach($service->application_fields ?? [] as $field)
        @php($fieldValue = old('data.'.$field['key']))
        @php($fieldValue = is_scalar($fieldValue) ? $fieldValue : '')
        <div class="form-group"><label class="form-label" for="request-{{ $field['key'] }}">{{ $field['label'] }} {{ $field['required'] ? '(مطلوب)' : '(اختياري)' }}</label>
          @if($field['type'] === 'textarea')
            <textarea class="form-control" id="request-{{ $field['key'] }}" name="data[{{ $field['key'] }}]" maxlength="{{ $field['max_length'] ?? 1000 }}" @required($field['required'])>{{ $fieldValue }}</textarea>
          @else
            <input class="form-control" id="request-{{ $field['key'] }}" name="data[{{ $field['key'] }}]" type="{{ ['text' => 'text', 'integer' => 'number', 'date' => 'date'][$field['type']] }}" value="{{ $fieldValue }}" @if($field['type'] === 'text') maxlength="{{ $field['max_length'] ?? 1000 }}" @endif @required($field['required'])>
          @endif
        </div>
      @endforeach
      <div class="form-group"><label class="form-label" for="request-notes">ملاحظات الطلب (اختياري)</label><textarea class="form-control" id="request-notes" name="notes" maxlength="2000">{{ is_string(old('notes')) ? old('notes') : '' }}</textarea></div>
      <button type="submit" class="btn btn-primary">مراجعة ملخص الطلب</button>
    </form>
  @endif
  <p><a href="{{ $service->publicUrl() }}">شروط الخدمة</a> / <a href="{{ route('citizen.data') }}">بياناتي</a> / <a href="{{ route('citizen.applications.index') }}">طلباتي</a></p>
</div></div></section>
@endsection
