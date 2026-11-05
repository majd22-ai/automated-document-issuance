@extends('layouts.public')
@section('title', 'تقييم أهلية مواطن للخدمة')
@section('content')
<section class="ops-section"><div class="container">
<div class="ops-page-head"><h1>تقييم أهلية مواطن</h1><p>{{ $service->name }}</p><a href="{{ route('admin.services.edit',$service) }}">العودة إلى إعداد الخدمة</a></div>
@include('operations.messages')
<div class="card ops-card"><p>هذا التقييم يخص مواطنًا واحدًا، ولا يغيّر إعدادات الخدمة. راجع المرجع المعتمد قبل تسجيل النتيجة.</p><p>إصدار الشروط الحالي: {{ $service->rules_version }}</p>
@include('admin.services.assessment-form')
</div></div></section>
@endsection
