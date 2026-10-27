@extends('layouts.public')
@section('title', 'محاكاة السجل المدني — تجربة محلية')
@section('content')
<section class="section"><div class="container">
  <div class="ops-page-head"><h1>محاكاة السجل المدني</h1><p class="ops-help">تجربة محلية للتكامل؛ راجع نتيجة السيناريو وسجل المحاولة قبل العودة إلى الطلب.</p></div>
  <div class="alert alert-warning" role="status">بيئة تجربة محلية ببيانات وهمية. لا تتصل بالسجل المدني الحقيقي، ولا تعتمد الهوية أو تغيّر بيانات الحسابات والطلبات. لا تدخل بيانات مواطنين حقيقية.</div>
  <div class="card ops-card" style="padding:1.5rem;max-width:720px;margin-block:1rem">
    <h2>بيانات الاختبار</h2>
    @foreach($records as $number => $record)
      <p><strong dir="ltr">{{ $number }}</strong> — {{ $record['name'] }} — {{ $record['available'] ? 'سجل متاح للمطابقة' : 'محاكاة تعطل الخدمة' }}</p>
    @endforeach
    <p>لاختبار اختلاف البيانات: استخدم الرقم الأول مع اسم مختلف. لاختبار عدم وجود سجل: استخدم 99000000009.</p>
    <form method="POST" action="{{ route('integrations.civil-registry-demo.run') }}">
      @csrf
      <div class="form-group"><label for="demo-national-id" class="form-label">الرقم الوطني التجريبي</label><input id="demo-national-id" class="form-control" name="national_id" value="{{ old('national_id', $input['national_id'] ?? '') }}" pattern="[0-9]{11}" maxlength="11" inputmode="numeric" dir="ltr" required>@error('national_id')<p class="text-danger">{{ $message }}</p>@enderror</div>
      <div class="form-group"><label for="demo-name" class="form-label">الاسم التجريبي</label><input id="demo-name" class="form-control" name="name" maxlength="150" value="{{ old('name', $input['name'] ?? '') }}" required>@error('name')<p class="text-danger">{{ $message }}</p>@enderror</div>
      <div style="margin-block-start:1rem"><button class="btn btn-primary" type="submit">تشغيل المحاكاة</button></div>
    </form>
  </div>
  @if($outcome)
    <div class="alert alert-info" role="status"><strong>نتيجة محاكاة تجريبية:</strong>
      {{ match($outcome) { 'match' => 'الرقم والاسم متطابقان مع سجل الاختبار.', 'mismatch' => 'الرقم موجود لكن الاسم غير مطابق.', 'not_found' => 'لا يوجد سجل تجريبي لهذا الرقم.', 'unavailable' => 'خدمة السجل المدني غير متاحة في هذا السيناريو.', default => 'تعذر التحقق.' } }}
      <p>هذه النتيجة ليست إثباتًا رسميًا ولا تمنح صلاحية تقديم خدمة.</p>
    </div>
  @endif
</div></section>
@endsection
