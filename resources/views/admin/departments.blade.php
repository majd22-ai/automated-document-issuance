@extends('layouts.public')
@section('title','إدارة الأقسام')
@section('content')
<section class="ops-section"><div class="container"><div class="ops-page-head"><h1>إدارة الأقسام</h1></div>@include('admin.oversight-feedback')
<p>تعطيل القسم يحجب تقاريره عن الموظفين المفوضين؛ لا يحذف الحسابات أو السجلات التاريخية.</p>
<h2>إضافة قسم</h2><form method="POST" action="{{ route('admin.departments.store') }}" class="card ops-card ops-form-grid">@csrf<label>اسم القسم<input class="form-control" name="name" maxlength="150" required></label><label>الوصف<input class="form-control" name="description" maxlength="500"></label><input type="hidden" name="is_active" value="1"><button class="btn btn-primary">إضافة قسم</button></form>
<h2>الأقسام المسجلة</h2><div class="ops-grid">@forelse($departments as $department)<form class="card ops-card" method="POST" action="{{ route('admin.departments.update',$department) }}">@csrf @method('PATCH')<label>اسم القسم<input class="form-control" name="name" value="{{ $department->name }}" maxlength="150" required></label><label>الوصف<input class="form-control" name="description" value="{{ $department->description }}" maxlength="500"></label><label>الحالة<select class="form-control" name="is_active"><option value="1" @selected($department->is_active)>نشط</option><option value="0" @selected(!$department->is_active)>معطل</option></select></label><p>الحسابات المرتبطة: {{ $department->employees_count }}</p><button class="btn btn-outline">حفظ</button></form>@empty<div class="card ops-card ops-empty">لا توجد أقسام مسجلة. أضف قسمًا للبدء بتنظيم فريق العمل.</div>@endforelse</div>{{ $departments->links() }}
</div></section>
@endsection
