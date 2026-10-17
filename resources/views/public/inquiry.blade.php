@extends('layouts.public')

@php
  $reference = $mode === 'reference';

  $title = $reference
    ? 'الاستعلام عن حالة طلب'
    : 'الاستعلام عن جميع طلباتي';
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
    <div class="card gov-front-form">

      <p class="gov-demo-note">
        واجهة عرض تجريبية؛ لا تعرض بيانات حقيقية ولا ترسل البيانات المدخلة.
        الاستعلام الفعلي يتطلب ربط النظام والتحقق من هوية المستخدم.
      </p>

      <form data-demo-form>
        <div class="form-group">

          <label class="form-label" for="inquiry-value">
            {{ $reference ? 'رقم الطلب المرجعي' : 'الرقم الوطني' }}
          </label>

          <input
            class="form-control"
            id="inquiry-value"
            type="text"
            maxlength="40"
            required
            autocomplete="off"
            aria-describedby="inquiry-help"
            @if(!$reference)
              inputmode="numeric"
              pattern="[0-9٠-٩]+"
            @endif
          >

          <p id="inquiry-help">
            @if($reference)
              متابعة معاملة محددة باستخدام رقم الطلب المرجعي.
            @else
              عرض المعاملات المرتبطة بالرقم الوطني بعد تفعيل الخدمة
              والتحقق من الهوية.
            @endif
          </p>

        </div>

        <button class="btn btn-primary" type="submit">
          استعلام تجريبي
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