@extends('layouts.public')
@section('title', 'فقدت بطاقتي')
@section('content')
<section class="section" dir="rtl">
    <div class="container">
        <h1>فقدت بطاقتي</h1>
        <p>لتقديم بلاغ فقد، افتح الخدمة من حساب المواطن. مراجعة البلاغات متاحة للمدير وللموظف المكلّف بخدمة بدل فاقد.</p>
        <a href="{{ route('home') }}">العودة إلى الرئيسية</a>
    </div>
</section>
@endsection
