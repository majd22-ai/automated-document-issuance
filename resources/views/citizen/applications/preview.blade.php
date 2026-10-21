@extends('layouts.public')
@section('title', 'مراجعة الطلب قبل الإرسال')
@section('content')
<section class="section"><div class="container"><div class="card" style="padding:1.5rem;">
  <h1>مراجعة ملخص الطلب</h1>
  @if($errors->any())<div class="alert alert-danger" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
  <dl>
    <dt>الخدمة</dt><dd>{{ $service->name }}</dd>
    <dt>نوع الطلب</dt><dd>{{ \App\Models\GovernmentService::OPERATIONS[$service->operation] }}</dd>
    <dt>المواطن</dt><dd>{{ auth()->user()->name }}</dd>
    <dt>الرقم الوطني</dt><dd>{{ auth()->user()->username }}</dd>
    <dt>الرسوم المسجلة</dt><dd>{{ $service->fee !== null ? $service->fee.' '.$service->currency : 'لم تحدّد' }} — لم يتم تحصيل رسوم ضمن هذه الخطوة.</dd>
    @foreach($service->application_fields ?? [] as $field)
      <dt>{{ $field['label'] }}</dt><dd>{{ $preview['payload']['data'][$field['key']] ?? 'غير مسجل' }}</dd>
    @endforeach
    <dt>الملاحظات</dt><dd>{{ $preview['payload']['notes'] ?? 'لا توجد ملاحظات' }}</dd>
  </dl>
  <p>سيعاد التحقق من بياناتك وأهلية الخدمة عند التأكيد. صلاحية هذا الملخص 30 دقيقة.</p>
  <h2>مرفقات الطلب</h2>
  @include('components.attachment-list', ['attachments' => auth()->user()->attachments()->where('government_service_id', $service->id)->whereNull('citizen_service_request_id')->get()])
  <form method="POST" action="{{ route('citizen.services.submit', $service) }}">
    @csrf
    <input type="hidden" name="preview_token" value="{{ $token }}">
    <div class="form-group"><label for="request-declaration"><input type="checkbox" id="request-declaration" name="declaration" value="1" required> أقر بأن البيانات المعروضة صحيحة وتخصني، وأؤكد إرسال الطلب.</label></div>
    <button class="btn btn-primary" type="submit">تأكيد إرسال الطلب</button>
    <a class="btn btn-outline" href="{{ route('citizen.applications.create', ['service' => $service->code]) }}">العودة لتعديل البيانات</a>
  </form>
</div></div></section>
@endsection
