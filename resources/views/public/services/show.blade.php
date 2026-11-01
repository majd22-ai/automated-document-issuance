@extends('layouts.public')
@section('title', $service->name.' - منصة الإصدار الآلي')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/service-detail.css') }}?v={{ filemtime(public_path('css/service-detail.css')) }}">
@endpush
@section('content')
<section class="page-hero"><div class="container"><h1>{{ $service->name }}</h1><p>{{ $service->description ?? 'الوصف قيد الاعتماد.' }}</p></div></section>
<section class="section service-detail"><div class="container"><div class="grid service-details-grid">
  <div>
    <div class="card mb-3"><h2>وصف الخدمة</h2><p>{{ $service->description ?? 'لم يعتمد وصف الخدمة بعد.' }}</p><p>نوع الوثيقة: {{ \App\Models\GovernmentService::DOCUMENT_TYPES[$service->document_type] }} — العملية: {{ \App\Models\GovernmentService::OPERATIONS[$service->operation] }}</p></div>
    @foreach(['conditions' => 'الشروط والمتطلبات', 'required_documents' => 'المستندات المطلوبة'] as $field => $label)
      @php
        $documents = $service->{$field} ?? [];
        if ($field === 'required_documents' && $service->document_type === 'identity') {
          $identitySections = array_values(config('civil_registry_guides.identity.sections'));
          $documents = $identitySections[$service->operation === 'issue' ? 0 : 1];
        }
      @endphp
      <div class="card mb-3"><h2>{{ $label }}</h2><ul class="requirements-list">@forelse($documents as $item)<li>{{ $item }}</li>@empty<li>لم تُحدّد؛ راجع الجهة المختصة.</li>@endforelse</ul></div>
    @endforeach
    <details class="card mb-3"><summary>ما الذي تتم مراجعته قبل قبول الطلب؟</summary><ul class="requirements-list">
      <li>اكتمال بيانات المواطن والتحقق الموثّق من الهوية واستيفاء الشروط لدى الجهة المختصة.</li>
      @if($service->minimum_age !== null)<li>الحد الأدنى للعمر المسجل: {{ $service->minimum_age }} سنة، ويحتاج تحققًا موثّقًا.</li>@endif
      @if($service->requires_driving_clearance)<li>يلزم اعتماد متطلبات رخصة القيادة.</li>@endif
      @if(in_array('phone', $service->required_fields ?? []))<li>رقم الهاتف مطلوب لهذه الخدمة.</li>@endif
      @if($service->operation === 'renewal')
        <li>وثيقة سابقة مرتبطة بحسابك من النوع نفسه، بحالة معتمدة وتواريخ إصدار وانتهاء معروفة ومتسقة.</li>
        <li>الحالات المقبولة: {{ implode('، ', $service->renewal_statuses ?? []) ?: 'لم تُحدّد' }}.</li>
        @if($service->renewal_window_days !== null)<li>تسمح القاعدة المسجلة بالتجديد قبل الانتهاء بـ {{ $service->renewal_window_days }} يومًا أو بعده، مع استيفاء بقية الفحوص.</li>@endif
      @endif
      <li>عدم وجود مانع معتمد أو طلب قائم يمنع البدء.</li>
    </ul></details>
    <div class="card"><h2>اعتماد الخدمة</h2><p>{{ $service->rules_approved ? 'بيانات الخدمة معتمدة وفق المرجع المسجل في المنصة.' : 'بيانات الخدمة وقواعدها بانتظار اعتماد الجهة المختصة.' }}</p></div>
  </div>
  <aside><div class="card">
    <h2>معلومات الخدمة</h2>
    <p>الحالة: {{ $service->is_active ? 'مفعّلة' : 'غير مفعّلة' }}</p>
    <p>الرسوم: {{ $service->fee !== null ? $service->fee.' '.$service->currency : 'لم تُحدّد' }}</p>
    <p>المدة التقديرية: {{ $service->estimated_duration ?? 'لم تُحدّد' }}</p>
    @auth
      @if(auth()->user()->role === 'citizen')
        @if($service->code === 'identity-first-issue' && $service->is_active && $service->rules_approved)
          <p>أكمل الاستمارة خطوة بخطوة، واحفظها كمسودة للعودة إليها لاحقًا.</p>
          <a class="btn btn-primary" href="{{ route('citizen.identity-card.index') }}">بدء الطلب أو استكمال مسودتي</a>
          <small>حفظ المسودة لا يعني قبول الطلب؛ تُراجع الشروط قبل الإرسال.</small>
        @else
        @if($service->is_active && $service->rules_approved)<p><a class="btn btn-outline" href="{{ route('citizen.services.attachments.index', $service) }}">استكمال الصور والمرفقات</a></p>@endif
        @if($reasons)
          <div class="alert alert-danger" role="alert"><h3>لا يمكنك بدء الخدمة حاليًا</h3><ul>@foreach($reasons as $reason)<li>{{ $reason }}</li>@endforeach</ul></div>
          <a href="{{ route('citizen.data') }}">مراجعة بياناتي</a>
        @else
          <form method="POST" action="{{ route('citizen.services.start', $service) }}">@csrf<button type="submit" class="btn btn-primary btn-block">بدء الخدمة</button></form>
        @endif
        @endif
      @else
        <div class="alert alert-info" role="status">أنت تتصفح بحساب {{ auth()->user()->role === 'admin' ? 'مدير النظام' : 'موظف' }}. تقديم الطلب متاح من حساب المواطن.</div>
        <a class="btn btn-outline" href="{{ route(auth()->user()->role.'.dashboard') }}">العودة إلى لوحة العمل</a>
        @if(auth()->user()->role === 'admin')<a class="btn btn-outline" href="{{ route('admin.services.edit', $service) }}">إعداد الخدمة</a>@endif
      @endif
    @else
      @if($service->is_active)
        <a class="btn btn-primary" href="{{ route('login', ['redirect' => $service->code === 'identity-first-issue' ? '/citizen/identity-card' : '/citizen/applications/new?service='.$service->code]) }}">تسجيل الدخول لبدء الطلب</a>
      @endif
    @endauth
  </div></aside>
</div><p><a href="{{ route('services.index') }}">جميع الخدمات الحكومية</a></p></div></section>
@if(isset($report))<section class="section"><div class="container">@include('components.requirements-result', ['requirementsResult' => $report->toArray()])</div></section>@endif
@if($service->document_type === 'identity')
@include('components.civil-registry-guide', ['guide' => config('civil_registry_guides.identity')])
@endif
@endsection
