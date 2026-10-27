@extends('layouts.public')
@section('title', 'بيانات المواطن - لوحة الموظف')
@section('content')
<section class="section"><div class="container">
  <div class="ops-page-head"><h1>بيانات المواطن</h1><p class="ops-help">راجع بيانات المواطن، ثم انتقل إلى الوثيقة أو الطلب المرتبط لاستكمال المتابعة.</p></div>
  <p><a href="{{ route('employee.citizens.index') }}">العودة إلى البحث</a></p>
  @include('components.citizen-details')
  @include('components.citizen-documents')
  @include('components.citizen-requests')
</div></section>
@endsection
