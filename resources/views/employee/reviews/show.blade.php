@extends('layouts.public')
@section('title', 'مراجعة الطلب - منصة الإصدار الآلي')
@section('content')
    @include('components.portal-demo', ['screen' => 'review', 'demoRequestId' => $applicationId ?? ''])
@endsection
