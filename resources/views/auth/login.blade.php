@extends('layouts.auth')

@section('title', 'تسجيل الدخول - منصة الإصدار الآلي')

@section('content')
<div class="auth-wrapper">
  <div class="auth-card">

    <div class="auth-logo">
      <div class="branding-logo">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="32" height="32">
          <path d="M12 2L3 7l9 5 9-5-9-5z"/>
          <path d="M3 12l9 5 9-5"/>
          <path d="M3 17l9 5 9-5"/>
        </svg>
      </div>
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
        <label class="form-label" for="identifier">الرقم الوطني / اسم المستخدم</label>
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
