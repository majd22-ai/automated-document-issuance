@extends('layouts.public')
@section('title', 'تم تقديم الطلب')
@section('content')
<section class="section"><div class="container"><div class="card" style="padding:1.5rem;">
  <h1>تم تقديم طلبك بنجاح</h1>
  <p>الرقم المرجعي: <strong dir="ltr">{{ $application->reference }}</strong></p>
  <p>الخدمة: {{ $application->service_name ?? $application->service_code }}</p>
  <p>نوع الطلب: {{ \App\Models\GovernmentService::OPERATIONS[$application->request_type] ?? 'غير متوفر' }}</p>
  <p>تاريخ التقديم: {{ $application->submitted_at->format('Y-m-d H:i') }}</p>
  <p>الحالة: {{ $application->statusLabel() }}</p>
  <a class="btn btn-primary" href="{{ route('citizen.applications.show', $application->id) }}">عرض تفاصيل الطلب</a>
  <a class="btn btn-outline" href="{{ route('citizen.applications.index') }}">طلباتي</a>
</div></div></section>
@endsection
