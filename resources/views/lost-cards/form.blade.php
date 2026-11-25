@extends('layouts.public')
@section('title', 'استمارة طلب بطاقة شخصية بدل فاقد')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/lost-cards.css') }}?v={{ filemtime(public_path('css/lost-cards.css')) }}">
@endpush
@section('content')
<section class="section" dir="rtl"><div class="container lost-card-page">
    <h1>استمارة طلب بطاقة شخصية بدل فاقد</h1>
    <p>محاكاة أكاديمية · مرجع المتابعة <bdi>{{ $case->reference }}</bdi></p>
    @include('operations.messages')
    <form method="post" action="{{ route('citizen.lost-cards.submit', $case) }}" class="card ops-card lost-card-form">
        @csrf
        <fieldset>
            <legend>بيانات مقدم الطلب</legend>
            <p>الاسم في الحساب {{ $case->holder_name }} · الرقم الوطني <bdi>{{ $case->national_id }}</bdi></p>
            <div class="lost-card-fields">
                @foreach(['given_name'=>'اسم الفرد','father_name'=>'اسم الأب','grandfather_name'=>'اسم الجد','family_name'=>'اللقب'] as $key => $label)
                    <label>{{ $label }}<input name="{{ $key }}" class="form-control" value="{{ old($key, $parts[$key] ?? '') }}" maxlength="100" required></label>
                @endforeach
            </div>
        </fieldset>
        <fieldset>
            <legend>بيانات البطاقة السابقة</legend>
            <p>رقم البطاقة <bdi>{{ $case->card_number }}</bdi></p>
            <div class="lost-card-fields">
                <label>المحافظة <input name="issuing_governorate" class="form-control" maxlength="150" value="{{ old('issuing_governorate') }}" required></label>
                <label>مركز الإصدار <input name="issuing_center" class="form-control" maxlength="150" value="{{ old('issuing_center') }}" required></label>
                <label>تاريخ الإصدار السابق <input type="date" name="previous_issued_on" class="form-control" max="{{ $case->lost_on }}" value="{{ old('previous_issued_on', $case->previous_issued_on) }}" required></label>
            </div>
        </fieldset>
        <fieldset>
            <legend>أدلة البلاغ والنشر</legend>
            <p>بلاغ الشرطة <bdi>{{ $case->police->reference }}</bdi></p>
            <p>إعلان صحيفة الثورة <bdi>{{ $case->notice->reference }}</bdi> · عدد {{ $case->notice->issue_number }} · صفحة {{ $case->notice->page_number }} · <bdi>{{ $case->notice->published_on }}</bdi></p>
        </fieldset>
        @if($service->required_documents)
            <p><a href="{{ route('citizen.services.attachments.index', $service) }}">رفع المرفقات المطلوبة للخدمة</a></p>
        @endif
        <fieldset>
            <legend>الإقرار والتوقيع</legend>
            <p>تاريخ التقديم <bdi>{{ today()->format('Y-m-d') }}</bdi></p>
            <p><label><input type="checkbox" name="declaration" value="1" @checked(old('declaration')) required> أقر بفقدان بطاقتي الموضحة أعلاه وبصحة البيانات، وأتحمل المسؤولية عنها.</label></p>
            <p><label>توقيع مقدم الطلب بكتابة اسمه الكامل <input name="signature" class="form-control" maxlength="200" value="{{ old('signature') }}" required></label></p>
        </fieldset>
        <button class="btn btn-primary" type="submit">إرسال طلب بدل فاقد</button>
    </form>
</div></section>
@endsection
