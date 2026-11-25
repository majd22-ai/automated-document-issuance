@extends('layouts.public')
@section('title', 'متابعة بلاغات فقدان البطاقة')
@section('content')
<section class="ops-section" dir="rtl"><div class="container">
    <h1>متابعة بلاغات فقدان البطاقة</h1>
    @include('operations.messages')
    @if(auth()->user()->role === 'admin')
        <p><a href="{{ route('admin.review-permissions.index') }}">إدارة صلاحيات موظفي بدل فاقد</a></p>
        @if($lostCardCase)
            <p><a href="{{ route('admin.services.assessments.create', app(\App\Services\LostCardWorkflow::class)->service()) }}">تقييم أهلية المواطن لخدمة بدل فاقد</a></p>
        @endif
    @endif
    @if($lostCardCase)
        <p>مرجع المتابعة <bdi>{{ $lostCardCase->reference }}</bdi></p>
        <dl>
            <dt>مقدم الطلب</dt><dd>{{ $lostCardCase->holder_name }}</dd>
            <dt>الرقم الوطني</dt><dd><bdi>{{ $lostCardCase->national_id }}</bdi></dd>
            <dt>البطاقة المفقودة</dt><dd><bdi>{{ $lostCardCase->card_number }}</bdi></dd>
            <dt>تاريخ ومكان الفقد</dt><dd><bdi>{{ $lostCardCase->lost_on }}</bdi> · {{ $lostCardCase->loss_place }}</dd>
            <dt>بلاغ الشرطة</dt><dd>{{ $lostCardCase->police?->reference ?? 'بانتظار تسجيل البلاغ' }}</dd>
            <dt>إعلان الصحيفة</dt><dd>{{ $lostCardCase->notice?->status === 'published' ? 'منشور' : ($lostCardCase->notice ? 'قيد التجهيز' : 'لم يُطلب النشر') }}</dd>
        </dl>
        <p><button type="button" data-ops-open="lost-cards" class="btn btn-primary">فتح محاكاة الشرطة والصحيفة</button></p>
        @if($lostCardCase->application)
            <p>طلب بدل فاقد <bdi>{{ $lostCardCase->application->reference }}</bdi> · {{ $lostCardCase->application->statusLabel() }}</p>
            @can('viewReview', $lostCardCase->application)
                <p><a href="{{ route('employee.applications.review', $lostCardCase->application_id) }}">مراجعة الطلب</a></p>
            @endcan
        @endif
    @else
        <div class="ops-table-wrap"><table class="table">
            <thead><tr><th>المرجع</th><th>المواطن</th><th>بلاغ الشرطة</th><th>النشر</th><th>الطلب</th></tr></thead>
            <tbody>
            @forelse($queue as $item)
                <tr>
                    <td><a href="{{ route(auth()->user()->role.'.lost-cards.show', $item) }}"><bdi>{{ $item->reference }}</bdi></a></td>
                    <td>{{ $item->holder_name }}</td>
                    <td>{{ $item->police ? 'مسجّل' : 'بانتظار التسجيل' }}</td>
                    <td>{{ $item->notice?->status === 'published' ? 'منشور' : ($item->notice ? 'قيد التجهيز' : 'لم يُطلب النشر') }}</td>
                    <td>{{ $item->application?->statusLabel() ?? 'لم يُرسل' }}</td>
                </tr>
            @empty
                <tr><td colspan="5">لا توجد طلبات إبلاغ.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        {{ $queue->links() }}
    @endif
</div></section>
@endsection
