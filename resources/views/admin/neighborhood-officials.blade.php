@extends('layouts.public')
@section('title', 'دليل عُقّال الحارات ومسؤولي الأحياء')
@push('styles')<link rel="stylesheet" href="{{ asset('css/identity-attestation.css') }}">@endpush
@push('scripts')<script src="{{ asset('js/neighborhood-official-search.js') }}?v={{ filemtime(public_path('js/neighborhood-official-search.js')) }}" defer></script>@endpush
@section('content')
<section class="ops-section"><div class="container">
<h1>دليل عُقّال الحارات ومسؤولي الأحياء</h1>
<p>يضيف المدير البيانات المعتمدة لديه. لا ينشئ هذا الإجراء حساب مستخدم أو اعتمادًا حكوميًا.</p>
@if(session('status'))<p class="attestation-warning" role="status">{{ session('status') }}</p>@endif
@if(app()->environment(['local', 'testing']))
    <form method="POST" action="{{ route('admin.neighborhood-officials.generate') }}" class="attestation-panel">
        @csrf
        <button type="submit" class="btn btn-primary">توليد الأحياء والعُقّال التجريبيين</button>
    </form>
@endif
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
<h2 id="official-search" style="scroll-margin-top: 8rem">ربط العاقل بحساب موظف ومنحه صلاحية شهادة السكن فقط</h2>
<p>اختر حسابًا موجودًا؛ لا ينشئ الربط حسابًا ولا يمنح صلاحيات مراجعة الخدمات أو إصدار الوثائق.</p>
<form method="GET" action="{{ route('admin.neighborhood-officials.index') }}#official-search" role="search" class="attestation-panel" data-official-suggestions="{{ route('admin.neighborhood-officials.suggestions') }}">
    <div class="attestation-field">
        <label for="official-name-search">ابحث باسم العاقل</label>
        <input id="official-name-search" type="search" name="q" value="{{ $search }}" maxlength="150" placeholder="الاسم الأول أو جزء من الاسم" autocomplete="off" aria-controls="official-name-suggestions">
        <input type="hidden" name="official_id" value="{{ $selectedOfficial }}">
        <ul id="official-name-suggestions" class="official-name-suggestions" aria-label="اقتراحات أسماء العُقّال" hidden></ul>
        <small data-suggestion-status role="status"></small>
        @error('q')<p class="attestation-error">{{ $message }}</p>@enderror
    </div>
    <button type="submit" class="btn btn-primary">بحث</button>
    @if($search !== '')<a class="btn btn-outline" href="{{ route('admin.neighborhood-officials.index') }}#official-search">مسح البحث</a>@endif
</form>
@if($credentials = session('demoNeighborhoodCredentials'))
    <p id="demo-official-credentials" class="attestation-warning" role="status" style="scroll-margin-top: 8rem">بيانات الدخول التجريبية لهذا العرض فقط<br>
        اسم المستخدم <bdi>{{ $credentials['username'] }}</bdi><br>
        كلمة المرور <bdi>{{ $credentials['password'] }}</bdi>
    </p>
@endif
@forelse($officials as $official)
@if(app()->environment(['local', 'testing']) && $official->user && \App\Services\DemoNeighborhoods::isDemoAccount($official->user))
<form method="POST" action="{{ route('admin.neighborhood-officials.demo-password', $official) }}">
    @csrf
    <span>{{ $official->full_name }} — {{ $official->neighborhood }}، {{ $official->district->name }} — <bdi>{{ $official->user->username }}</bdi></span>
    <button type="submit" class="btn btn-outline">إصدار كلمة مرور تجريبية</button>
</form>
@endif
<form class="card ops-card" method="POST" action="{{ route('admin.neighborhood-officials.update', $official) }}">@csrf @method('PATCH')
<strong>{{ $official->full_name }} — {{ $official->neighborhood }}</strong><input type="hidden" name="is_active" value="{{ (int) $official->is_active }}">
<label>حساب الموظف<select name="user_id" class="form-control"><option value="">دون ربط</option>@foreach($employees as $employee)<option value="{{ $employee->id }}" @selected($official->user_id === $employee->id)>{{ $employee->name }}</option>@endforeach</select></label>
@error('user_id')<p class="attestation-error">{{ $message }}</p>@enderror
<input type="hidden" name="can_attest" value="0"><label><input type="checkbox" name="can_attest" value="1" @checked($official->can_attest)> السماح باعتماد شهادة السكن في هذا الحي</label><button class="btn btn-primary">حفظ التفويض</button>
</form>
@empty
    <p>{{ $search !== '' ? 'لم يُعثر على عاقل يطابق الاسم المدخل.' : 'لم تُسجل بيانات عُقّال بعد.' }}</p>
@endforelse
{{ $officials->links() }}
</div></section>
@endsection
