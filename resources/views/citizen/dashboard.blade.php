@extends('layouts.public')
@section('title', 'لوحة تحكم المواطن - منصة الإصدار الآلي')
@section('content')
    <section class="section"><div class="container"><article class="card"><h2>إصدار البطاقة الشخصية</h2><p>استمارة متعددة المراحل مع حفظ المسودة والصورة وبيانات الشاهدين.</p><a class="btn btn-primary" href="{{ route('citizen.identity-card.index') }}">فتح خدمة إصدار البطاقة الشخصية</a></article></div></section>
    <section class="section"><div class="container"><p>{{ auth()->user()->accountStatusLabel() }} — <a href="{{ route('citizen.account.status') }}">حالة الحساب وتعليمات الحضور والتفعيل</a></p></div></section>
    <section class="section"><div class="container"><a class="btn btn-primary" href="{{ route('citizen.data') }}">بياناتي المسجلة</a> <a class="btn btn-outline" href="{{ route('citizen.documents.index') }}">وثائقي المسجلة</a> <a class="btn btn-outline" href="{{ route('citizen.applications.index') }}">طلباتي المسجلة</a><p>السجلات الفعلية متاحة عبر الروابط أعلاه. الواجهة التالية معاينة تجريبية مستقلة وليست بيانات حكومية.</p></div></section>
    @include('components.portal-demo', ['screen' => 'dashboard'])
@endsection
