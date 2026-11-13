<!doctype html>
<html lang="ar" dir="rtl">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title', 'بوابة البريد التجريبية')</title>
  <link rel="stylesheet" href="{{ asset('css/postal-network.css') }}">
  <link rel="stylesheet" href="{{ asset('css/service-banner.css') }}?v={{ filemtime(public_path('css/service-banner.css')) }}">
</head>
<body class="postal-page">
@php
  $serviceBanner = [
    'title' => trim(strip_tags($__env->yieldContent('title', 'بوابة البريد التجريبية'))),
    'subtitle' => 'بوابة موظفي البريد — خدمات شبكة البريد التجريبية',
    'agency' => 'assets/images/banner/moi-logo.webp', 'agencyAlt' => 'شعار وزارة الداخلية',
    'document' => 'assets/images/banner/unified-issuance-building.webp', 'documentAlt' => 'مبنى مجمع الإصدار الآلي الموحد',
  ];
@endphp
@include('components.service-banner')
<main class="postal-shell">
  <header><h1>بوابة موظفي البريد — صنعاء</h1><p class="postal-demo">بيانات تجريبية — محاكاة تعليمية مستقلة؛ ليست خدمة رسمية للبريد اليمني ولا تأكيداً لمواقع فروعه.</p></header>
  @if($errors->any())<div role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
  @yield('content')
</main></body></html>
