@extends('layouts.public')
@section('title','إصدار سجل الوثيقة')
@section('content')
<section class="section"><div class="container"><div class="ops-page-head"><h1>مراجعة الإصدار</h1><p class="ops-help">راجع المتطلبات والوثيقة الحالية أولًا، ثم نفذ إجراء الإصدار المتاح وفق صلاحياتك.</p></div>@include('operations.messages')@include('components.request-details')
@include('components.requirements-result',['requirementsResult'=>$report->toArray()])
<p>الإصدار هنا سجل داخلي أكاديمي، ولا يمنح اعتمادًا حكوميًا. يلزم إعداد مدة الصلاحية وطرق الاستلام من المسؤول قبل الإصدار.</p>
@if($application->issuedDocument)<a class="btn btn-primary" href="{{ route('employee.documents.show',$application->issuedDocument->id) }}">عرض الوثيقة الصادرة وتسليمها</a>
@elseif($application->status==='approved')
<form class="card ops-card" style="padding:1.5rem" method="POST" action="{{ route('employee.issuance.store',$application->id) }}">@csrf
<input type="hidden" name="operation_token" value="{{ \Illuminate\Support\Str::uuid() }}"><input type="hidden" name="expected_version" value="{{ $application->review_version }}"><input type="hidden" name="expected_rules_version" value="{{ $application->service->rules_version }}"><input type="hidden" name="expected_fingerprint" value="{{ $fingerprint }}">
@if($application->service->operation==='renewal')<label for="previous-document" style="display:block;margin-block:1rem .4rem">الوثيقة السابقة المطلوب تجديدها</label><select class="form-control" id="previous-document" name="previous_document_id" required>@foreach($previous as $document)<option value="{{ $document->id }}">{{ $document->document_number }} — {{ $document->validityLabel() }} — {{ $document->expires_at?->format('Y-m-d') }}</option>@endforeach</select>@endif
<div style="margin-block-start:1rem"><button class="btn btn-primary">إصدار السجل والنسخة الداخلية</button></div></form>
@else<p>الطلب لم يصل إلى الاعتماد النهائي.</p>@endif<a href="{{ route('employee.issuance.index') }}">العودة إلى قائمة الإصدار</a></div></section>
@endsection
