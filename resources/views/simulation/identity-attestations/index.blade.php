@extends('layouts.public')
@section('title', 'طلبات المصادقة التجريبية')
@section('content')
<section class="section"><div class="container"><h1>طلبات المصادقة التجريبية</h1><p class="attestation-warning">بيئة تجريبية: هذه المصادقة محاكاة داخل النظام وليست اعتمادًا رسميًا</p>
<div class="ops-table-wrap"><table><thead><tr><th>مرجع الطلب</th><th>المستلم</th><th>المسار</th><th>الحالة</th><th>الإجراء</th></tr></thead><tbody>@forelse($attestations as $row)<tr><td>{{ $row->form->application->reference }}</td><td>{{ $row->recipient_name }}</td><td>{{ $row->authorityLabel() }}</td><td>{{ $row->statusLabel() }}</td><td><a href="{{ route('simulation.identity-attestations.show', $row) }}">عرض الطلب</a></td></tr>@empty<tr><td colspan="5">لا توجد طلبات مصادقة ضمن صلاحياتك.</td></tr>@endforelse</tbody></table></div>{{ $attestations->links() }}</div></section>
@endsection
