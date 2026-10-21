@extends('layouts.auth')

@section('title', 'تسجيل الدخول - منصة الإصدار الآلي')

@section('content')
<div class="auth-wrapper">
  <div class="auth-card">

    <div class="auth-logo">
      <img class="auth-platform-logo" src="{{ asset('assets/images/logos/unified-issuance-logo.png') }}" alt="شعار مركز الإصدار الآلي الموحد">
      <h2>تسجيل الدخول</h2>
      <p>أدخل بيانات حسابك للوصول إلى خدمات المنصة</p>
    </div>

    @if ($errors->any())
      <div class="alert alert-danger">
        {{ $errors->first() }}
      </div>
    @endif

    <form method="POST" action="{{ url('/login') }}" class="auth-form" data-validate>
      @csrf

      {{-- Preserve redirect target --}}
      @if(request('redirect'))
        <input type="hidden" name="redirect" value="{{ request('redirect') }}">
      @endif

      <div class="form-group">
        <label class="form-label" for="identifier">البريد الإلكتروني / الرقم الوطني / اسم المستخدم</label>
        <input
          type="text"
          id="identifier"
          name="identifier"
          class="form-control"
          value="{{ old('identifier') }}"
          autocomplete="username"
          required
          autofocus
        >
      </div>

      <div class="form-group">
        <label class="form-label" for="password">كلمة المرور</label>
        <input
          type="password"
          id="password"
          name="password"
          class="form-control"
          autocomplete="current-password"
          required
        >
      </div>

      <div class="form-group" style="display:flex; align-items:center; gap:8px;">
        <input type="checkbox" id="remember" name="remember" value="1" style="width:auto;">
        <label for="remember" style="font-size:14px; margin:0;">تذكرني</label>
      </div>

      <button type="submit" class="btn btn-primary btn-block btn-lg">دخول</button>
    </form>

    <div class="auth-links">
      <a href="{{ url('/register') }}">إنشاء حساب جديد</a>
      <a href="{{ url('/forgot-password') }}">نسيت كلمة المرور؟</a>
    </div>

  </div>
</div>
@endsection
