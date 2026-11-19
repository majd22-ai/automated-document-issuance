@extends('layouts.public')
@section('title', $form->isRenewal() ? 'استمارة تجديد البطاقة الشخصية' : 'استمارة إصدار البطاقة الشخصية')
@push('styles')<link rel="stylesheet" href="{{ asset('css/identity-card-wizard.css') }}">@endpush
@section('content')
<section class="section"><div class="container"><h1>{{ $form->isRenewal() ? 'استمارة تجديد البطاقة الشخصية' : 'استمارة إصدار البطاقة الشخصية' }}</h1><p>المرجع الداخلي: {{ $form->application->reference }} — {{ $form->statusLabel() }}</p>
@if(!$form->isRenewal())<p>بيانات الشهود وحدها لا تثبت حضورهم أو موافقتهم. المصادقات هنا تجريبية، وإجراء البصمة لم يُنفذ بعد.</p>@include('components.identity-card-attestation', ['identityForm' => $form])@else<p>التجديد العادي لا يتطلب شاهدين أو مصادقة العاقل. راجع الصورة والبطاقة السابقة في ملف مراجعة الطلب.</p>@endif
<a href="{{ route('employee.applications.review', $form->application->id) }}">العودة إلى مراجعة الطلب</a>
@include('components.identity-card-summary', ['identityForm' => $form])
<p><a href="{{ route('employee.attachments.index', ['search' => $form->citizen->name]) }}">مراجعة الصورة ضمن المرفقات</a></p>
</div></section>
@endsection
