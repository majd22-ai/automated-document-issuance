
@extends('layouts.public')
@section('title', 'إدارة المستخدمين - لوحة المدير')
@section('content')
<section class="ops-page-head"><div class="container"><h1>إدارة المستخدمين</h1><p>عرض وإدارة جميع حسابات المستخدمين</p></div></section>
<section class="ops-section"><div class="container">
  <p><a class="btn btn-outline" href="{{ route('admin.review-permissions.index') }}">تحديد صلاحيات مراجعة الخدمات واعتمادها</a></p>
  <div style="margin-bottom:1.5rem;"><a href="{{ route('admin.dashboard') }}" style="color:var(--primary);text-decoration:none;"><x-icon name="arrow-left" /> العودة للوحة التحكم</a></div>
  @if(session('status'))
    <div class="alert alert-success" role="status">{{ session('status') }}</div>
  @endif
  @if($errors->any())
    <div class="alert alert-danger" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
  @endif
  <div class="card ops-card" style="padding:2rem;">
    <h2>البحث عن حساب</h2><form method="GET" class="ops-toolbar" action="{{ route('admin.users.index') }}">
      <div class="form-group">
        <label class="form-label" for="user-search">البحث بالاسم أو البريد أو اسم المستخدم</label>
        <input id="user-search" name="search" value="{{ $search }}" maxlength="100" class="form-control">
      </div>
      <button type="submit" class="btn btn-primary">بحث</button><a class="btn btn-outline" href="{{ route('admin.users.index') }}">مسح البحث</a>
    </form>
  </div>
  <div class="card ops-card" style="padding:1.5rem;margin-top:1rem;overflow-x:auto;">
    <div class="ops-table-wrap"><table aria-label="حسابات المستخدمين" class="table" style="width:100%;text-align:start;">
      <thead><tr><th scope="col">المستخدم</th><th scope="col">البريد الإلكتروني</th><th scope="col">الدور وحالة الحساب</th></tr></thead>
      <tbody>
      @forelse($users as $user)
        <tr>
          <td>{{ $user->name }}<br><small>{{ $user->username }}</small></td>
          <td>{{ $user->email }}</td>
          <td>
            <form method="POST" action="{{ route('admin.users.update', $user) }}" style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
              @csrf
              @method('PATCH')
              <label for="role-{{ $user->id }}">الدور</label>
              <select id="role-{{ $user->id }}" name="role" class="form-control" style="width:auto;">
                @foreach(['citizen' => 'مواطن', 'employee' => 'موظف', 'admin' => 'مدير'] as $role => $label)
                  <option value="{{ $role }}" @selected($user->role === $role)>{{ $label }}</option>
                @endforeach
              </select>
              <label for="active-{{ $user->id }}">الحالة</label>
              <select id="active-{{ $user->id }}" name="is_active" class="form-control" style="width:auto;">
                <option value="1" @selected($user->is_active)>مفعّل</option>
                <option value="0" @selected(! $user->is_active)>معطّل</option>
              </select>
              <button class="btn btn-primary btn-sm" type="submit">حفظ</button>
            </form>
          </td>
        </tr>
      @empty
        <tr><td class="ops-empty" colspan="3">لا توجد حسابات مطابقة للبحث.</td></tr>
      @endforelse
      </tbody>
    </table></div>
    {{ $users->links() }}
  </div>
</div></section>
@endsection
