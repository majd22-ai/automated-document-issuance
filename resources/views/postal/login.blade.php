@extends('postal.layout')
@section('title', 'دخول محصل البريد التجريبي')
@section('content')
<section class="postal-card"><h2>دخول موظف التحصيل</h2>
  <form method="POST" action="{{ route('postal.login.store') }}">
    @csrf
    <label>اسم المستخدم البريدي<input name="username" dir="ltr" value="{{ old('username') }}" maxlength="80" autocomplete="username" required></label>
    <label>كلمة المرور<input type="password" name="password" maxlength="72" autocomplete="current-password" required></label>
    <button type="submit">تسجيل الدخول</button>
  </form>
  <p>استخدم حساب البريد التجريبي الذي أنشأه مدير النظام.</p>
</section>
@endsection
