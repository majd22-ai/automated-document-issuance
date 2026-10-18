@extends('layouts.public')
@section('title', 'تفاصيل الطلب - منصة الإصدار الآلي')
@section('content')
    @include('components.portal-demo', ['screen' => 'details', 'demoRequestId' => $applicationId ?? ''])
@endsection
