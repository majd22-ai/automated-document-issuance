@extends('layouts.public')
@section('title','المواعيد')
@section('content')
<section class="section"><div class="container"><div class="ops-page-head"><h1>{{ $employee ? 'إدارة حجوزات المواطنين' : 'مواعيدي القادمة والسابقة' }}</h1><p class="ops-help">افتح تفاصيل الموعد لإدارة الحجز والاطلاع على طلب الخدمة المرتبط.</p></div>@include('operations.messages')
@if($employee)<a class="btn btn-primary" href="{{ route('employee.appointment-slots.index') }}">تعريف فترات المواعيد وسعتها</a>@else<p>لحجز موعد جديد، افتح طلبك ثم «المواعيد والفحوصات والرسوم».</p><a href="{{ route('citizen.applications.index') }}">طلباتي</a>@endif
@forelse($appointments as $appointment)<article class="card ops-card mb-3" style="padding:1.5rem"><h2>{{ $appointment->slot->center }}</h2><p>{{ $appointment->slot->starts_at->setTimezone(config('operations.timezone'))->format('Y-m-d H:i') }} — {{ $appointment->slot->starts_at->isFuture() ? 'قادم' : 'سابق' }} — {{ $appointment->statusLabel() }}</p>@if($employee)<p>{{ $appointment->citizen->name }}</p>@endif<p>الطلب: {{ $appointment->application->reference }}</p><a class="btn btn-outline" href="{{ route(($employee?'employee':'citizen').'.applications.operations',$appointment->citizen_service_request_id) }}">تفاصيل الموعد وإدارته</a></article>@empty<p>لا توجد مواعيد مسجلة.</p>@endforelse {{ $appointments->links() }}
</div></section>
@endsection
