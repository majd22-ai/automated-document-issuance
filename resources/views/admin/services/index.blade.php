@extends('layouts.public')
@section('title', 'إدارة الخدمات - لوحة المدير')
@section('content')
<section class="page-hero"><div class="container"><h1>إدارة الخدمات الحكومية</h1><p>ضبط البيانات المعتمدة وتفعيل الخدمات</p></div></section>
<section class="section"><div class="container">
  @if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
  <p><a class="btn btn-primary" href="{{ route('admin.services.create') }}">إضافة خدمة</a> <a class="btn btn-outline" href="{{ route('admin.dashboard') }}">لوحة المدير</a></p>
  <div class="card" style="padding:1.5rem;overflow-x:auto;"><table class="table" style="width:100%;text-align:start;">
    <thead><tr><th>الخدمة</th><th>نوع الوثيقة والعملية</th><th>الحالة</th><th>الرسوم</th><th>الإدارة</th></tr></thead>
    <tbody>@forelse($services as $service)<tr>
      <td>{{ $service->name }}<br><small>{{ $service->code }}</small></td>
      <td>{{ \App\Models\GovernmentService::DOCUMENT_TYPES[$service->document_type] }} — {{ \App\Models\GovernmentService::OPERATIONS[$service->operation] }}</td>
      <td>{{ $service->is_active ? 'مفعّلة' : 'غير مفعّلة' }} / {{ $service->rules_approved ? 'معتمدة' : 'بانتظار الاعتماد' }}</td>
      <td>{{ $service->fee !== null ? $service->fee.' '.$service->currency : 'لم تُحدّد' }}</td>
      <td><a href="{{ route('admin.services.edit', $service) }}">إعداد الخدمة والتقييمات</a> / <a href="{{ $service->publicUrl() }}">التفاصيل</a></td>
    </tr>@empty<tr><td colspan="5">لا توجد خدمات مسجلة.</td></tr>@endforelse</tbody>
  </table>{{ $services->links() }}</div>
</div></section>
@endsection