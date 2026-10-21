@extends('layouts.public')
@section('title', 'مراجعة الطلبات - منصة الإصدار الآلي')
@section('content')
<section class="section"><div class="container"><h1>طلبات المواطنين — عرض فقط</h1>
  @include('components.request-filters')
  <div class="card" style="padding:1.5rem;overflow-x:auto;">
    <table class="table" style="width:100%;text-align:start;"><thead><tr><th>الرقم المرجعي</th><th>الخدمة</th><th>الحالة</th><th>تاريخ التقديم</th></tr></thead><tbody>
      @forelse($applications as $application)
        <tr><td><a href="{{ route('employee.applications.review', $application->id) }}">{{ $application->reference }}</a></td><td>{{ $application->service_name ?? $application->service_code }}</td><td>{{ $application->statusLabel() }}</td><td>{{ $application->submitted_at?->format('Y-m-d') ?? $application->created_at?->format('Y-m-d') }}</td></tr>
      @empty
        <tr><td colspan="4">لا توجد طلبات مطابقة ضمن صلاحياتك.</td></tr>
      @endforelse
    </tbody></table>{{ $applications->links() }}
  </div>
</div></section>
@endsection
