@extends('layouts.public')
@section('title', $guide['title'].' - منصة الإصدار الآلي')
@section('content')
<div class="container"><nav class="breadcrumb"><a href="{{ route('home') }}">الرئيسية</a><span class="sep">/</span><a href="{{ route('services.identity') }}">خدمات السجل المدني</a><span class="sep">/</span><span>{{ $guide['title'] }}</span></nav></div>
@include('components.civil-registry-guide')
@endsection
