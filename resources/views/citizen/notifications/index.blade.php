@extends('layouts.public')
@section('title', 'الإشعارات - منصة الإصدار الآلي')
@section('content')
<section class="section"><div class="container"><h1>إشعاراتي</h1>@include('operations.messages')<p>غير المقروءة: {{ $unread }}</p>
<form method="GET"><label for="notification-read-filter">حالة القراءة</label><select class="form-control" id="notification-read-filter" name="read"><option value="">كل الإشعارات</option><option value="unread" @selected(request('read')==='unread')>غير مقروءة</option><option value="read" @selected(request('read')==='read')>مقروءة</option></select><button class="btn btn-outline">تصفية</button></form>
<form method="POST" action="{{ route('citizen.notifications.read-all') }}">@csrf<button class="btn btn-outline">تعليم الكل كمقروء</button></form>
@forelse($notifications as $notification)<article class="card" style="padding:1.5rem;margin-block:1rem"><h2>{{ is_string($notification->data['title']??null)?$notification->data['title']:'إشعار في حسابك' }}</h2><p>{{ $notification->read_at?'مقروء':'جديد' }} — {{ $notification->created_at->format('Y-m-d H:i') }}</p><p>{{ is_string($notification->data['body']??null)?$notification->data['body']:'' }}</p>
@if($notification->request_id || $notification->document_id || in_array($notification->data['kind']??'', ['account_status','employee_account','employee_permission','attachment_update'], true))<form method="POST" action="{{ route('citizen.notifications.open',$notification->id) }}">@csrf<button class="btn btn-primary">فتح التفاصيل</button></form>@endif
@if(!$notification->read_at)<form method="POST" action="{{ route('citizen.notifications.read',$notification->id) }}">@csrf<button class="btn btn-outline">تعليم كمقروء</button></form>@endif</article>@empty<p>لا توجد إشعارات مطابقة في حسابك.</p>@endforelse{{ $notifications->links() }}</div></section>
@endsection
