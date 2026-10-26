@extends('layouts.public')
@section('title', 'حالة الحساب والتفعيل')
@section('content')
<section class="section" dir="rtl"><div class="container"><div class="card" style="padding:clamp(20px,4vw,40px);max-width:850px;margin:auto">
<h1>حالة الحساب والتفعيل</h1><p role="status">{{ $citizen->accountStatusLabel() }}</p>
<dl><dt>معرّف حسابك الداخلي</dt><dd>{{ $citizen->id }}</dd><dt>اسم المستخدم</dt><dd>{{ $citizen->username }}</dd><dt>الرقم الوطني</dt><dd>{{ $citizen->nationalIdentity() ?? 'لم يُربط رقم وطني بعد' }}</dd><dt>اعتماد تفعيل الحساب</dt><dd>{{ $citizen->account_activated_at ? 'مسجل بواسطة إجراء إداري' : 'لم يُسجل اعتماد جديد' }}</dd><dt>توثيق الهوية المدنية للحساب</dt><dd>{{ $citizen->identity_verified_at ? 'مؤكد بإجراء إداري موثق' : 'لم يكتمل توثيق الهوية العامة' }}</dd></dl>
@if($citizen->registration_status === 'initial_account')
<p>يمكنك تقديم طلب إصدار البطاقة الشخصية لأول مرة ومتابعته فقط، وفق الشروط المعتمدة. بعد اكتمال الإصدار الرسمي واعتماد الموظف يُرقّى حسابك نفسه، وتبقى كلمة المرور واسم المستخدم والطلبات محفوظة.</p><a class="btn btn-primary" href="{{ route('citizen.applications.create', ['service'=>'identity-first-issue']) }}">إصدار البطاقة لأول مرة</a>
@elseif($citizen->needsCitizenActivation())
<div class="alert alert-info"><h2>الحضور مطلوب لاستكمال التفعيل</h2><p>يرجى الحضور إلى مكتب الأحوال المدنية أو مركز الإصدار الآلي المعتمد، ومعك مرجع حسابك والوثائق الرسمية المطلوبة لدى الجهة. يتحقق الموظف المخوّل من البيانات ويعتمد التفعيل وتوثيق الهوية حسب نتيجة الإجراءات.</p><p>نجاح تسجيل الدخول أو التحقق من الجوال لا يفعّل الحساب ولا يثبت الهوية. الخدمات الحكومية المقيدة محجوبة إلى حين استيفاء التفعيل والتوثيق وشروط كل خدمة.</p></div>
@else
<p>يمكن متابعة الخدمات وفق الصلاحيات وشروط كل خدمة. الحسابات السابقة تخضع لفحوص الأهلية القائمة؛ لا تُعد هذه الصفحة إثباتًا حكوميًا مستقلًا.</p>
@endif
<p><a href="{{ route('citizen.applications.index') }}">متابعة طلباتي</a> · <a href="{{ route('citizen.notifications.index') }}">إشعاراتي</a> · <a href="{{ route('profile.edit') }}">الملف الشخصي</a></p>
</div></div></section>
@endsection
