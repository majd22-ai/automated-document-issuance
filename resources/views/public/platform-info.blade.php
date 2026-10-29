@extends('layouts.public')
@section('title', $page['title'])
@section('content')
<section class="gov-page-title"><div class="gov-container"><h1>{{ $page['title'] }}</h1><p>{{ $page['intro'] }}</p></div></section>
<div class="gov-container platform-information" dir="rtl">
  @foreach($page['sections'] as $section)
  <section class="card platform-information-section" id="{{ $section['id'] }}" aria-labelledby="info-{{ $section['id'] }}">
    <h2 id="info-{{ $section['id'] }}">{{ $section['title'] }}</h2>
    @foreach($section['paragraphs'] ?? [] as $paragraph)<p>{{ $paragraph }}</p>@endforeach
    @if(isset($section['items']))<ol>@foreach($section['items'] as $item)<li>{{ $item }}</li>@endforeach</ol>@endif
  </section>
  @endforeach
</div>
@endsection
