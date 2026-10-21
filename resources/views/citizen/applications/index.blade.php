@extends('layouts.public')
@section('title', 'طلباتي - منصة الإصدار الآلي')
@section('content')
<section class="section"><div class="container">
  <h1>طلباتي</h1>
  @include('components.request-filters')
  <a href="{{ route('citizen.data') }}">العودة إلى بياناتي</a>
  @include('components.citizen-requests')
</div></section>
@endsection
