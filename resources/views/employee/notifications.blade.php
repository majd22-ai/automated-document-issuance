@extends('layouts.public')
@section('title', 'إشعارات الموظف')
@section('content')
<section class="section"><div class="container"><div class="ops-page-head"><h1>صندوق الإشعارات</h1><p class="ops-help">تحديثات حسابك والمعاملات المسندة إليك. يفتح كل إشعار الصفحة المسموحة لك حسب صلاحياتك الحالية.</p></div>@include('operations.messages')
<p>غير المقروءة: {{ $unread }} — تعرض الإشعارات من الأحدث إلى الأقدم.</p>
<form method="GET" style="max-width:360px;margin-block:1rem"><label for="staff-notification-filter">حالة الإشعارات</label><select class="form-control" name="read" id="staff-notification-filter"><option value="">جميع الإشعارات</option><option value="unread" @selected(request('read')==='unread')>الإشعارات الجديدة</option><option value="read" @selected(request('read')==='read')>الإشعارات المقروءة</option></select><button class="btn btn-outline" type="submit">عرض</button></form>
<div class="ops-toolbar"><form method="POST" action="{{ route('employee.notifications.read-all') }}">@csrf<button class="btn btn-outline" type="submit" @disabled($unread === 0)>تعليم الإشعارات الحالية كمقروءة</button></form><a class="btn btn-outline" href="{{ route('employee.notifications.index') }}">جميع الإشعارات</a></div>
@forelse($notifications as $notification)
<article class="card ops-card ops-inbox-item {{ !$notification->read_at ? 'is-unread' : '' }}" style="padding:1.5rem;margin-block:1rem"><h2>{{ is_string($notification->data['title']??null) ? $notification->data['title'] : 'إشعار' }}</h2><p>{{ is_string($notification->data['body']??null) ? $notification->data['body'] : '' }}</p><div class="ops-inbox-meta"><span class="ops-status">{{ $notification->read_at ? 'مقروء' : 'جديد' }}</span><time datetime="{{ $notification->created_at->toAtomString() }}" dir="ltr">{{ $notification->created_at->format('Y-m-d H:i') }}</time>@if(is_string($notification->data['request_reference']??null))<span>مرجع الطلب: <bdi class="ops-inbox-reference">{{ $notification->data['request_reference'] }}</bdi></span>@endif</div>
<div class="ops-toolbar">
<form method="POST" action="{{ route('employee.notifications.open', $notification->id) }}">@csrf<button class="btn btn-primary" type="submit">فتح ومراجعة</button></form>
@if(!$notification->read_at)<form method="POST" action="{{ route('employee.notifications.read', $notification->id) }}">@csrf<button class="btn btn-outline" type="submit">تعليم كمقروء</button></form>@endif</div></article>
@empty<div class="card ops-card ops-empty"><h2>صندوقك خالٍ من الإشعارات المطابقة</h2><p>ستظهر هنا العمليات الموجهة إلى حسابك أو طلباتك المسندة عند تسجيلها فعليًا.</p></div>@endforelse
{{ $notifications->links() }}
</div></section>
@endsection
