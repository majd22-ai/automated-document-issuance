@extends('layouts.public')
@section('title','إدارة الموظف')
@section('content')
<section class="section"><div class="container"><h1>{{ $employee->exists?'بيانات الموظف':'إنشاء حساب موظف' }}</h1>
@include('admin.oversight-feedback')
<a href="{{ route('admin.employees.index') }}">قائمة الموظفين</a> — <a href="{{ route('admin.review-permissions.index') }}">صلاحيات الخدمات</a>
<form class="card" style="padding:1.5rem;margin-block:1rem" method="POST" action="{{ $employee->exists?route('admin.employees.update',$employee):route('admin.employees.store') }}">@csrf @if($employee->exists)@method('PATCH')@endif
@foreach(['name'=>'الاسم الكامل','username'=>'اسم المستخدم','email'=>'البريد الإلكتروني','employee_number'=>'رقم الموظف','job_title'=>'المسمى الوظيفي'] as $key=>$label)<label>{{ $label }}<input class="form-control" name="{{ $key }}" value="{{ old($key,$employee->$key) }}" maxlength="{{ $key==='employee_number'?50:($key==='email'?255:100) }}" @required(in_array($key,['name','username','email']))></label>@endforeach
<label>القسم<select class="form-control" name="department_id"><option value="">غير معين</option>@foreach($departments as $d)<option value="{{ $d->id }}" @selected(old('department_id',$employee->department_id)==$d->id)>{{ $d->name }}{{ $d->is_active?'':' (معطل)' }}</option>@endforeach</select></label>
<label>الدور<select class="form-control" name="role"><option value="employee" @selected(old('role',$employee->role)==='employee')>موظف</option><option value="admin" @selected(old('role',$employee->role)==='admin')>مدير النظام — جميع الصلاحيات الإدارية</option></select></label>
<label>الحالة<select class="form-control" name="is_active"><option value="1" @selected(old('is_active',$employee->is_active??true))>نشط</option><option value="0" @selected(!old('is_active',$employee->is_active??true))>معطل</option></select></label>
<label>صلاحية التقارير<select class="form-control" name="report_scope"><option value="none">دون منحة</option><option value="department" @selected(old('report_scope',$employee->report_scope)==='department')>تقارير القسم فقط</option></select></label>
<label>تصدير تقارير القسم<select class="form-control" name="can_export_reports"><option value="0">غير مسموح</option><option value="1" @selected(old('can_export_reports',$employee->can_export_reports))>مسموح</option></select></label>
@unless($employee->exists)<label>كلمة مرور أولية (12 حرفًا على الأقل)<input class="form-control" type="password" name="password" required autocomplete="new-password" minlength="12"></label><label>تأكيد كلمة المرور<input class="form-control" type="password" name="password_confirmation" required autocomplete="new-password"></label>@endunless
<button class="btn btn-primary">حفظ</button></form>
@if($employee->exists)<p>تاريخ الإنشاء: {{ $employee->created_at }} — آخر تسجيل دخول: {{ $employee->last_login_at??'غير متوفر قبل بدء التتبع' }}</p>
<form method="POST" action="{{ route('admin.employees.reset-password',$employee) }}">@csrf<button class="btn btn-outline">إرسال رابط استعادة كلمة المرور إلى الموظف</button></form>@endif
</div></section>
@endsection
