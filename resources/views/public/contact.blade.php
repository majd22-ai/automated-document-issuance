@extends('layouts.public')

@php
  $titles = [
    'contact' => 'تواصل معنا',
    'report' => 'تقديم بلاغ إلى الجهة المعنية',
    'complaint' => 'تقديم شكوى — خاص بالمواطنين',
    'technical' => 'الإبلاغ عن مشكلة تقنية',
  ];

  $title = $titles[$mode];
@endphp

@section('title', $title)

@section('content')
<section class="gov-page-title">
  <div class="gov-container">
    <h2>{{ $title }}</h2>
  </div>
</section>

<section class="section">
  <div class="gov-container">
    <div class="card gov-front-form" id="contact-form" style="scroll-margin-top:140px">

      <p class="gov-demo-note">
        واجهة عرض تجريبية؛ لا يتم إرسال الرسائل أو البلاغات
        أو الشكاوى إلى أي جهة.

        @if($mode === 'complaint')
          هذه الواجهة مخصصة للمواطنين؛ تقديم الشكوى فعليًا يتطلب
          تسجيل الدخول والتحقق من الهوية عند تفعيل الخدمة.
        @endif
      </p>

      <form data-demo-form>

        <div class="form-group">
          <label class="form-label" for="contact-name">
            الاسم الكامل
          </label>

          <input
            class="form-control"
            id="contact-name"
            type="text"
            maxlength="100"
            required
          >
        </div>

        <div class="form-group">
          <label class="form-label" for="contact-email">
            البريد الإلكتروني
          </label>

          <input
            class="form-control"
            id="contact-email"
            type="email"
            maxlength="254"
            required
          >
        </div>

        @if($mode === 'report')
          <div class="form-group">
            <label class="form-label" for="contact-authority">
              الجهة المعنية
            </label>

            <select
              class="form-control"
              id="contact-authority"
              required
            >
              <option value="">اختر الجهة</option>
              <option>الأحوال المدنية</option>
              <option>الجوازات</option>
              <option>المرور</option>
            </select>
          </div>
        @endif

        <div class="form-group">
          <label class="form-label" for="contact-message">
            {{ $mode === 'technical' ? 'وصف المشكلة التقنية وخطوات حدوثها (دون كلمات مرور أو رموز تحقق)' : ($mode === 'contact' ? 'الرسالة' : 'تفاصيل البلاغ أو الشكوى') }}
          </label>

          <textarea
            class="form-control"
            id="contact-message"
            maxlength="3000"
            required
          ></textarea>
        </div>

        <button class="btn btn-primary" type="submit">
          تجربة النموذج
        </button>

        <p
          class="gov-demo-result"
          data-demo-result
          role="status"
          aria-live="polite"
        ></p>

      </form>
    </div>
  </div>
</section>
@endsection
