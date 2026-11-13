@extends('documents.copies.layout')
@section('document_title','سجل داخلي لخدمة البطاقة الشخصية')
@section('type_note')
@if($identityPhoto ?? null)<img src="{{ $identityPhoto }}" alt="صورة صاحب البطاقة المحفوظة وقت الإصدار" width="150" height="180" style="object-fit:contain">@endif
<p>هذا سجل لخدمة البطاقة الشخصية داخل المنصة الأكاديمية، ولا يحل محل بطاقة الهوية.</p>@endsection
