@extends('layouts.public')
@section('title', 'اعتماد شهادات السكن')
@section('content')
<section class="ops-section"><h1>طلبات اعتماد شهادة السكن في حيي</h1><p>تجربة محلية؛ لا تمثل ختمًا حكوميًا ولا إرسال SMS فعليًا.</p>
<button class="btn btn-outline" type="button" onclick="document.getElementById('local-neighborhood-messages').showModal()">محاكاة رسائل جوال العاقل</button>
<dialog id="local-neighborhood-messages" aria-labelledby="local-messages-title" style="max-width:min(92vw,480px);padding:24px;border-radius:16px">
<form method="dialog"><button class="btn btn-outline" aria-label="إغلاق">×</button></form><h2 id="local-messages-title">رسائل تجريبية — لم تُرسل عبر SMS</h2>
@forelse($attestations->where('status','pending') as $row)<article class="card ops-card"><p>لديك طلب اعتماد شهادة سكن للمواطن {{ $row->citizen_name }} في {{ data_get($row->recipient_scope,'neighborhood') }}.</p><time>{{ $row->created_at->format('Y-m-d H:i') }}</time><p><a href="{{ route('employee.neighborhood-attestations.show', $row) }}">فتح رابط مراجعة الطلب</a></p></article>@empty<p>لا توجد رسائل لطلبات معلقة في هذه الصفحة.</p>@endforelse
</dialog>
@forelse($attestations as $row)<article class="card ops-card"><h2>{{ $row->citizen_name }}</h2><p>{{ $row->statusLabel() }} — {{ $row->created_at->format('Y-m-d H:i') }}</p><a href="{{ route('employee.neighborhood-attestations.show', $row) }}">عرض الطلب</a></article>@empty<p>لا توجد طلبات ضمن الحي المخوّل لك.</p>@endforelse
{{ $attestations->links() }}</section>
@endsection
