@extends('layouts.public')
@section('title', 'مراجعة الطلب - منصة الإصدار الآلي')
@section('content')
<section class="section"><div class="container"><div class="card" style="padding:1.5rem;">
  <h1>تفاصيل طلب المواطن — عرض فقط</h1>
  <p>مراجعة الطلب واعتماده وتغيير حالته غير متاحة ضمن هذا المتطلب.</p>
  @include('components.request-details')
  <a href="{{ route('employee.applications.index') }}">العودة إلى قائمة الطلبات</a>
</div></div></section>
@endsection
