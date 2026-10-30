@extends('layouts.public')
@section('title', 'دليل عُقّال الحارات ومسؤولي الأحياء')
@push('styles')<link rel="stylesheet" href="{{ asset('css/identity-attestation.css') }}">@endpush
@section('content')
<section class="ops-section"><div class="container">
<h1>دليل عُقّال الحارات ومسؤولي الأحياء</h1>
<p>يضيف المدير البيانات المعتمدة لديه. لا ينشئ هذا الإجراء حساب مستخدم أو اعتمادًا حكوميًا.</p>
@if(session('status'))<p class="attestation-warning" role="status">{{ session('status') }}</p>@endif
<form method="POST" action="{{ route('admin.neighborhood-officials.store') }}" class="attestation-panel" data-residence-selects>@csrf
<h2>إضافة عاقل الحارة</h2>
@foreach(['full_name'=>'الاسم الرباعي','national_id'=>'الرقم الوطني','phone'=>'رقم الهاتف المحمول','neighborhood'=>'الحي المسؤول عنه'] as $field=>$label)
<div class="attestation-field"><label for="official-{{ $field }}">{{ $label }} *</label><input id="official-{{ $field }}" name="{{ $field }}" value="{{ old($field) }}" required maxlength="{{ $field === 'national_id' ? 20 : 150 }}" @if(in_array($field,['phone','national_id'])) inputmode="numeric" dir="ltr" @endif>@error($field)<p class="attestation-error">{{ $message }}</p>@enderror</div>
@endforeach
@include('components.residence-selects', ['prefix'=>'admin-official'])
<button type="submit" class="btn btn-primary">حفظ العاقل</button></form>
<div class="ops-table-wrap"><table class="ops-table"><thead><tr><th>العاقل</th><th>المحافظة والمديرية</th><th>الحي</th><th>الهاتف</th><th>الحالة</th></tr></thead><tbody>
@forelse($officials as $official)<tr><td>{{ $official->full_name }}</td><td>{{ $official->district->governorate->name }} — {{ $official->district->name }}</td><td>{{ $official->neighborhood }}</td><td dir="ltr">{{ $official->phone }}</td><td><form method="POST" action="{{ route('admin.neighborhood-officials.update',$official) }}">@csrf @method('PATCH')<input type="hidden" name="is_active" value="{{ $official->is_active ? 0 : 1 }}"><button class="btn btn-outline">{{ $official->is_active ? 'تعطيل' : 'إعادة تفعيل' }}</button></form></td></tr>@empty<tr><td colspan="5">لم تُسجل بيانات عُقّال بعد.</td></tr>@endforelse
</tbody></table></div>{{ $officials->links() }}
</div></section>
@endsection
