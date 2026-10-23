@extends('layouts.public')
@section('title', 'لوحة تحكم الموظف - منصة الإصدار الآلي')
@section('content')
    <section class="section"><div class="container"><a class="btn btn-outline" href="{{ route('employee.appointments.index') }}">إدارة المواعيد</a> <a class="btn btn-outline" href="{{ route('employee.payments.index') }}">الرسوم والمدفوعات</a></div></section>
    <section class="section"><div class="container"><a class="btn btn-primary" href="{{ route('employee.attachments.index') }}">مراجعة صور ومستندات المواطنين</a></div></section>
    <section class="section"><div class="container"><a class="btn btn-primary" href="{{ route('employee.citizens.index') }}">البحث عن مواطن</a><p>الواجهة التالية معاينة تجريبية مستقلة عن سجلات المواطنين الفعلية.</p></div></section>
    @include('components.portal-demo', ['screen' => 'review'])
@endsection
