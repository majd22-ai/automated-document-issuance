
@extends('layouts.public')
@section('title', 'بيانات المواطنين - لوحة الموظف')
@section('content')
<section class="page-hero"><div class="container"><div class="ops-page-head"><h1>بيانات المواطنين</h1><p class="ops-help">ابحث عن المواطن ثم افتح ملفه للاطلاع على البيانات والوثائق والطلبات المرتبطة.</p></div><p>البحث والتحقق من بيانات المواطنين</p></div></section>
<section class="section"><div class="container">
  <div style="margin-bottom:1.5rem;"><a href="{{ route('employee.dashboard') }}" style="color:var(--primary);text-decoration:none;"><x-icon name="arrow-left" /> العودة للوحة التحكم</a></div>
  <div class="card ops-card" style="padding:2rem;">
    @if($errors->any())<div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    <form method="GET" action="{{ route('employee.citizens.index') }}" class="ops-toolbar card ops-card" style="padding:1.25rem">
      <div class="form-group" style="max-width:500px;">
        <label class="form-label" for="citizen-search">أدخل الاسم أو الرقم الوطني</label>
        <input id="citizen-search" name="search" value="{{ $search }}" type="search" class="form-control" minlength="2" maxlength="100" placeholder="الرقم الوطني كاملًا أو جزء من الاسم" required>
      </div>
      <div style="margin-block-start:1rem"><button class="btn btn-primary" type="submit">بحث</button></div>
     <a class="btn btn-outline" href="{{ url()->current() }}">مسح التصفية</a></form>
  </div>
  <div class="card ops-card" style="text-align:center;padding:3rem;margin-top:1rem;">
    @if($citizens === null)
      <p>أدخل الاسم أو الرقم الوطني لعرض النتائج المصرح لك بالاطلاع عليها.</p>
    @else
      <table class="table" style="width:100%;text-align:start;">
        <thead><tr><th scope="col">الاسم</th><th scope="col">الرقم الوطني</th><th scope="col">البيانات</th></tr></thead>
        <tbody>
          @forelse($citizens as $citizen)
            <tr><td>{{ $citizen->name }}</td><td>{{ $citizen->nationalIdentity() ?? 'غير مسجل' }}</td><td><a href="{{ route('employee.citizens.show', $citizen) }}">عرض بيانات المواطن</a></td></tr>
          @empty
            <tr><td colspan="3">لم يتم العثور على مواطن مطابق للبحث.</td></tr>
          @endforelse
        </tbody>
      </table>
      {{ $citizens->links() }}
    @endif
  </div>
</div></section>
@endsection
