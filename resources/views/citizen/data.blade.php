@extends('layouts.public')
@section('title', 'بياناتي - منصة الإصدار الآلي')
@section('content')
<section class="section"><div class="container">
  <h1>بياناتي</h1>
  @include('components.citizen-details')
  <p style="margin-top:1rem;">
    <a class="btn btn-outline" href="{{ route('profile.edit') }}">تحديث بيانات الاتصال</a>
    <a class="btn btn-outline" href="{{ route('citizen.documents.index') }}">وثائقي</a>
    <a class="btn btn-outline" href="{{ route('citizen.applications.index') }}">طلباتي</a>
    @if(!$missing)<a class="btn btn-primary" href="{{ route('citizen.applications.create') }}">بدء خدمة</a>@endif
  </p>
</div></section>
@endsection
