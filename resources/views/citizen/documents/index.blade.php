@extends('layouts.public')
@section('title', 'وثائقي - منصة الإصدار الآلي')
@section('content')
<section class="section"><div class="container">
  <h1>وثائقي</h1>
  <a href="{{ route('citizen.data') }}">العودة إلى بياناتي</a>
  @include('components.citizen-documents')
</div></section>
@endsection
