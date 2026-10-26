@extends('layouts.public')
@section('title','إدارة الأقسام')
@section('content')
<section class="section"><div class="container"><h1>إدارة الأقسام</h1>@include('admin.oversight-feedback')
<p>تعطيل القسم يحجب تقاريره عن الموظفين المفوضين؛ لا يحذف الحسابات أو السجلات التاريخية.</p>
<form method="POST" action="{{ route('admin.departments.store') }}" class="card" style="padding:1rem">@csrf<label>اسم القسم<input class="form-control" name="name" maxlength="150" required></label><label>الوصف<input class="form-control" name="description" maxlength="500"></label><input type="hidden" name="is_active" value="1"><button class="btn btn-primary">إضافة قسم</button></form>
@foreach($departments as $department)<form class="card" style="padding:1rem;margin-block:1rem" method="POST" action="{{ route('admin.departments.update',$department) }}">@csrf @method('PATCH')<label>اسم القسم<input class="form-control" name="name" value="{{ $department->name }}" maxlength="150" required></label><label>الوصف<input class="form-control" name="description" value="{{ $department->description }}" maxlength="500"></label><label>الحالة<select class="form-control" name="is_active"><option value="1" @selected($department->is_active)>نشط</option><option value="0" @selected(!$department->is_active)>معطل</option></select></label><p>الحسابات المرتبطة: {{ $department->employees_count }}</p><button class="btn btn-outline">حفظ</button></form>@endforeach{{ $departments->links() }}
</div></section>
@endsection
