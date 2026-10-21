@extends('layouts.public')
@section('title', 'لوحة تحكم المواطن - منصة الإصدار الآلي')
@section('content')
    <section class="section"><div class="container"><a class="btn btn-primary" href="{{ route('citizen.data') }}">بياناتي المسجلة</a> <a class="btn btn-outline" href="{{ route('citizen.documents.index') }}">وثائقي المسجلة</a> <a class="btn btn-outline" href="{{ route('citizen.applications.index') }}">طلباتي المسجلة</a><p>السجلات الفعلية متاحة عبر الروابط أعلاه. الواجهة التالية معاينة تجريبية مستقلة وليست بيانات حكومية.</p></div></section>
    @include('components.portal-demo', ['screen' => 'dashboard'])
@endsection
