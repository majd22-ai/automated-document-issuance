@extends('layouts.public')
@section('title', 'تفاصيل الطلب - منصة الإصدار الآلي')
@section('content')
<section class="section"><div class="container"><div class="card" style="padding:1.5rem;">
  <h1>تفاصيل الطلب</h1>
  @include('components.request-details')
  <a href="{{ route('citizen.applications.index') }}">العودة إلى طلباتي</a>
</div></div></section>
@endsection
