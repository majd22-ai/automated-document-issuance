@extends('layouts.public')
@section('title', 'فقدت بطاقتي')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/lost-cards.css') }}?v={{ filemtime(public_path('css/lost-cards.css')) }}">
@endpush
@section('content')
<section class="section" dir="rtl"><div class="container lost-card-page">
    <h1>فقدت بطاقتي</h1>
    <p>محاكاة أكاديمية لإجراءات بلاغ الفقد والنشر وطلب بدل فاقد.</p>
    @include('operations.messages')
    @if($case)
        <p>مرجع المتابعة <bdi>{{ $case->reference }}</bdi></p>
        <div class="card ops-card lost-card-steps">
            <ol>
                <li>إرسال طلب الإبلاغ · تم الاستلام</li>
                <li>بلاغ الشرطة · {{ $case->police ? 'مسجّل' : 'بانتظار الموظف' }} @if($case->police)<a href="{{ route('citizen.lost-cards.verify', [$case, 'police']) }}"><bdi>{{ $case->police->reference }}</bdi></a>@endif</li>
                <li>صحيفة الثورة · {{ $case->notice?->status === 'published' ? 'منشور' : ($case->notice ? 'قيد التجهيز' : 'بانتظار تقديم طلب النشر') }}
                    @if($case->notice?->status === 'published')
                        <a href="{{ route('citizen.lost-cards.verify', [$case, 'newspaper']) }}"><bdi>{{ $case->notice->reference }}</bdi></a> · عدد {{ $case->notice->issue_number }} · صفحة {{ $case->notice->page_number }} · <bdi>{{ $case->notice->published_on }}</bdi>
                    @endif
                </li>
                <li>إشعار الاستكمال · {{ $case->ready() ? 'أُرسل إلى صندوق الإشعارات' : 'بانتظار اكتمال البلاغ والنشر' }}</li>
                <li>طلب بدل فاقد · {{ $case->application?->statusLabel() ?? 'لم يُرسل بعد' }}</li>
            </ol>
            @if($case->application)
                <a class="btn btn-outline" href="{{ route('citizen.applications.show', $case->application_id) }}">متابعة طلب بدل فاقد</a>
            @elseif($case->ready())
                <a class="btn btn-primary" href="{{ route('citizen.lost-cards.form', $case) }}">تقديم طلب بدل فاقد</a>
            @endif
        </div>
        <p><a href="{{ route('citizen.lost-cards.index') }}">بلاغاتي</a></p>
    @else
        <h2>الإبلاغ عن فقدان البطاقة</h2>
        @if($cards)
            <form method="post" action="{{ route('citizen.lost-cards.report') }}" class="card ops-card lost-card-form">
                @csrf
                <p>مقدم الطلب <strong>{{ auth()->user()->name }}</strong></p>
                <p><label>البطاقة المفقودة
                    <select name="card" class="form-control" required>
                        <option value="">اختر البطاقة</option>
                        @foreach($cards as $key => $number)
                            <option value="{{ $key }}" @selected(old('card') === $key)>{{ $number }}</option>
                        @endforeach
                    </select>
                </label></p>
                <p><label>تاريخ الفقد <input type="date" name="lost_on" class="form-control" max="{{ today()->format('Y-m-d') }}" value="{{ old('lost_on') }}" required></label></p>
                <p><label>مكان الفقد <input name="loss_place" class="form-control" maxlength="150" value="{{ old('loss_place') }}" required></label></p>
                <button class="btn btn-primary" type="submit">إرسال طلب الإبلاغ</button>
            </form>
        @else
            <p>لا توجد بطاقة شخصية سارية مرتبطة بحسابك.</p>
        @endif
        <h2>بلاغاتي</h2>
        <ul>
            @forelse($cases as $item)
                <li><a href="{{ route('citizen.lost-cards.show', $item) }}"><bdi>{{ $item->reference }}</bdi></a> · <bdi>{{ $item->lost_on }}</bdi></li>
            @empty
                <li>لا توجد بلاغات مسجلة.</li>
            @endforelse
        </ul>
        {{ $cases->links() }}
    @endif
</div></section>
@endsection
