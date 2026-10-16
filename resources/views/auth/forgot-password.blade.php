@extends('layouts.auth')

@section('title', 'استعادة كلمة المرور - منصة الإصدار الآلي')

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
      <h2>استعادة كلمة المرور</h2>
      <p>أدخل بريدك الإلكتروني وسنرسل لك رابط إعادة التعيين</p>
    </div>

    @if (session('status'))
      <div class="alert alert-success">
        {{ session('status') }}
      </div>
    @endif

    @if ($errors->any())
      <div class="alert alert-danger">
        {{ $errors->first() }}
      </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="auth-form">
      @csrf

      <div class="form-group">
        <label class="form-label" for="email">البريد الإلكتروني</label>
        <input
          type="email"
          id="email"
          name="email"
          class="form-control"
          value="{{ old('email') }}"
          autocomplete="email"
          required
          autofocus
          placeholder="أدخل بريدك الإلكتروني المسجّل"
        >
      </div>

      <button type="submit" class="btn btn-primary btn-block btn-lg">إرسال رابط الاستعادة</button>
    </form>

    <div class="auth-links">
      <a href="{{ url('/login') }}">العودة إلى تسجيل الدخول</a>
      <a href="{{ url('/register') }}">إنشاء حساب جديد</a>
    </div>

  </div>
</div>
@endsection
