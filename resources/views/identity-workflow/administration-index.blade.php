@extends('layouts.public')
@section('title', 'المراجعة الإدارية لطلبات البطاقة')
@push('styles')<link rel="stylesheet" href="{{ asset('css/identity-workflow.css') }}">@endpush
@section('content')
<section class="section"><div class="container identity-workflow"><h1>المراجعة الإدارية لطلبات البطاقة</h1><p class="workflow-warning">مسار تجريبي داخل المنصة؛ الموافقة الإدارية ليست إصدار بطاقة حكومية.</p><div class="workflow-panel workflow-table-wrap"><table class="workflow-table"><thead><tr><th>مرجع الطلب</th><th>مقدم الطلب</th><th>الحالة</th><th>الإجراء</th></tr></thead><tbody>@forelse($forms as $form)<tr><td>{{ $form->application?->reference ?? $form->reference }}</td><td>{{ data_get($form->data,'applicant_name') }}</td><td>{{ $form->statusLabel() }}</td><td><a href="{{ route('identity-workflow.administration.show', $form) }}">مراجعة الطلب</a></td></tr>@empty<tr><td colspan="4">لا توجد طلبات جاهزة للمراجعة.</td></tr>@endforelse</tbody></table>{{ $forms->links() }}</div></div></section>
@endsection
