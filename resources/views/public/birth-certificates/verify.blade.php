@extends('layouts.public')
@section('title', 'التحقق من نموذج شهادة الميلاد')
@section('content')
<section class="section"><div class="container"><div class="card" style="padding:1.5rem">
    <h1>التحقق من نموذج شهادة الميلاد</h1>
    <div class="alert alert-info" role="status">هذا تحقق داخلي من نموذج تجريبي غير رسمي، وليس تحققًا حكوميًا.</div>
    <p>حالة السجل: موجود في المنصة.</p>
    <p>رقم النموذج: <span dir="ltr">{{ $certificate->certificate_number }}</span></p>
    <p>تاريخ إنشاء النموذج: {{ $certificate->issued_at->format('Y-m-d') }}</p>
    <p>لا تعرض هذه الصفحة اسم المولود أو أسماء الوالدين أو أرقام الهويات.</p>
</div></div></section>
@endsection
