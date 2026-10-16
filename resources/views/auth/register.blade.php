@extends('layouts.auth')

@section('title', 'إنشاء حساب جديد - منصة الإصدار الآلي')

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
      <h2>إنشاء حساب جديد</h2>
      <p>سجّل بياناتك للوصول إلى خدمات المنصة</p>
    </div>

    @if ($errors->any())
      <div class="alert alert-danger">
        {{ $errors->first() }}
      </div>
    @endif

    @if (session('status'))
      <div class="alert alert-success">
        {{ session('status') }}
      </div>
    @endif

    <form method="POST" action="{{ route('register.post') }}" class="auth-form" data-validate>
      @csrf

      <div class="form-group">
        <label class="form-label" for="name">الاسم الكامل</label>
        <input
          type="text"
          id="name"
          name="name"
          class="form-control"
          value="{{ old('name') }}"
          autocomplete="name"
          required
          autofocus
          placeholder="أدخل اسمك الرباعي"
        >
      </div>

      <div class="form-group">
        <label class="form-label" for="national_id">الرقم الوطني</label>
        <input
          type="text"
          id="national_id"
          name="national_id"
          class="form-control"
          value="{{ old('national_id') }}"
          required
          placeholder="أدخل رقمك الوطني"
          maxlength="20"
        >
      </div>

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
          placeholder="example@email.com"
        >
      </div>

      <div class="form-group">
        <label class="form-label" for="password">كلمة المرور</label>
        <input
          type="password"
          id="password"
          name="password"
          class="form-control"
          autocomplete="new-password"
          required
          placeholder="8 أحرف على الأقل"
        >
      </div>

      <div class="form-group">
        <label class="form-label" for="password_confirmation">تأكيد كلمة المرور</label>
        <input
          type="password"
          id="password_confirmation"
          name="password_confirmation"
          class="form-control"
          autocomplete="new-password"
          required
          placeholder="أعد إدخال كلمة المرور"
        >
      </div>

      <button type="submit" class="btn btn-primary btn-block btn-lg">إنشاء الحساب</button>
    </form>

    <div class="auth-links">
      <a href="{{ url('/login') }}">لديك حساب؟ سجّل الدخول</a>
    </div>

  </div>
</div>
@endsection
