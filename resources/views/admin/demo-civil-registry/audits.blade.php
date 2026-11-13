@extends('layouts.public')
@section('title', 'سجل تدقيق السجل المدني التجريبي')
@section('content')
<section class="ops-section"><div class="container"><div class="ops-page-head"><h1>سجل تدقيق السجل المدني التجريبي</h1><p>الأحداث للعرض فقط. لا تُخزن عبارات البحث.</p></div><p><a href="{{ route('admin.demo-civil-registry.index') }}">العودة إلى إدارة السجل</a></p>
<div class="ops-table-wrap"><table class="table"><thead><tr><th>التاريخ</th><th>المستخدم</th><th>الدور</th><th>العملية</th><th>المواطن</th><th>الواقعة</th><th>الوثيقة</th><th>الطلب</th><th>النتيجة</th></tr></thead><tbody>
@forelse($audits as $audit)<tr><td>{{ $audit->created_at?->format('Y-m-d H:i:s') }}</td><td>{{ $audit->actor?->name ?? 'حساب محذوف' }}</td><td>{{ $audit->actor_role }}</td><td>{{ $audit->action }}</td><td>{{ $audit->person_id ?? '—' }}</td><td>{{ $audit->record_id ?? '—' }}</td><td>{{ $audit->document_id ?? '—' }}</td><td>{{ $audit->citizen_service_request_id ?? '—' }}</td><td>{{ $audit->outcome }}</td></tr>@empty<tr><td colspan="9">لا توجد أحداث مسجلة.</td></tr>@endforelse
</tbody></table></div>{{ $audits->links() }}</div></section>
@push('styles')<link rel="stylesheet" href="{{ asset('css/demo-civil-registry.css') }}?v={{ filemtime(public_path('css/demo-civil-registry.css')) }}">@endpush
@endsection