@extends('layouts.public')
@section('title','إدارة المستخدمين')
@section('content')
<section class="ops-section"><div class="container">
<x-workspace-heading title="إدارة المستخدمين" role-label="إدارة الحسابات" description="ابحث عن الحساب ثم افتح تعديل صلاحياته الأساسية وحالة الوصول. صلاحيات الخدمات تضبط من قسم صلاحيات الخدمات." />
@include('operations.messages')
<article class="card ops-card"><h2>البحث والتصفية</h2><form method="GET" class="ops-toolbar" action="{{ route('admin.users.index') }}">
<label for="user-search">الاسم أو البريد أو اسم المستخدم<input id="user-search" name="search" value="{{ $search }}" maxlength="100" class="form-control" placeholder="اكتب بيانات الحساب"></label>
<label for="user-role">الدور<select id="user-role" name="role" class="form-control"><option value="">جميع الأدوار</option>@foreach(['citizen'=>'مواطن','employee'=>'موظف','admin'=>'مدير'] as $value=>$label)<option value="{{ $value }}" @selected(($filters['role']??'')===$value)>{{ $label }}</option>@endforeach</select></label>
<label for="user-state">حالة الوصول<select id="user-state" name="state" class="form-control"><option value="">جميع الحالات</option><option value="active" @selected(($filters['state']??'')==='active')>مفعّل</option><option value="disabled" @selected(($filters['state']??'')==='disabled')>معطّل</option></select></label>
<button class="btn btn-primary">عرض النتائج</button>@if(request()->hasAny(['search','role','state']))<a class="btn btn-outline" href="{{ route('admin.users.index') }}">مسح التصفية</a>@endif</form></article>
<article class="card ops-card"><h2>الحسابات <span class="ops-status">{{ $users->total() }} نتيجة</span></h2><p class="ops-help">فتح التعديل لا يغير البيانات. راجع الدور والحالة ثم اضغط «حفظ التغييرات» للحساب المطلوب فقط.</p>
<div class="ops-table-wrap" tabindex="0"><table aria-label="حسابات المستخدمين"><thead><tr><th>المستخدم</th><th>البريد الإلكتروني</th><th>الدور</th><th>الحالة</th><th>الإجراء</th></tr></thead><tbody>
@forelse($users as $user)<tr><td><strong>{{ $user->name }}</strong><br><small>{{ $user->username }}</small>@if($user->is(auth()->user()))<br><span class="ops-status">حسابك الحالي</span>@endif</td><td><bdi>{{ $user->email }}</bdi></td><td>{{ ['citizen'=>'مواطن','employee'=>'موظف','admin'=>'مدير'][$user->role]??$user->role }}</td><td>{{ $user->is_active?'مفعّل':'معطّل' }}</td><td>
<details class="user-edit"><summary>تعديل حساب {{ explode(' ',trim($user->name))[0] }}</summary><form method="POST" action="{{ route('admin.users.update',$user) }}">@csrf @method('PATCH')
<label for="role-{{ $user->id }}">الدور<select id="role-{{ $user->id }}" name="role" class="form-control">@foreach(['citizen'=>'مواطن','employee'=>'موظف','admin'=>'مدير'] as $value=>$label)<option value="{{ $value }}" @selected($user->role===$value)>{{ $label }}</option>@endforeach</select></label>
<label for="active-{{ $user->id }}">حالة الوصول<select id="active-{{ $user->id }}" name="is_active" class="form-control"><option value="1" @selected($user->is_active)>مفعّل</option><option value="0" @selected(!$user->is_active)>معطّل</option></select></label><button class="btn btn-primary">حفظ التغييرات</button></form></details>
</td></tr>@empty<tr><td colspan="5" class="ops-empty">لا توجد حسابات مطابقة. جرّب تغيير البحث أو التصفية.</td></tr>@endforelse
</tbody></table></div>{{ $users->links() }}</article>
</div></section>
@endsection
