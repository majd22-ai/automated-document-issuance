@extends('layouts.public')
@section('title', $service->name.' - منصة الإصدار الآلي')
@section('content')
<section class="page-hero"><div class="container"><h1>{{ $service->name }}</h1><p>{{ $service->description ?? 'الوصف قيد الاعتماد.' }}</p></div></section>
<section class="section"><div class="container"><div class="grid service-details-grid">
  <div>
    <div class="card mb-3"><h2>وصف الخدمة</h2><p>{{ $service->description ?? 'لم يعتمد وصف الخدمة بعد.' }}</p><p>نوع الوثيقة: {{ \App\Models\GovernmentService::DOCUMENT_TYPES[$service->document_type] }} — العملية: {{ \App\Models\GovernmentService::OPERATIONS[$service->operation] }}</p></div>
    @foreach(['conditions' => 'الشروط والمتطلبات', 'required_documents' => 'المستندات المطلوبة'] as $field => $label)
      <div class="card mb-3"><h2>{{ $label }}</h2><ul class="requirements-list">@forelse($service->{$field} ?? [] as $item)<li>{{ $item }}</li>@empty<li>لم تُحدّد؛ راجع الجهة المختصة.</li>@endforelse</ul></div>
    @endforeach
    <div class="card mb-3"><h2>فحوص الأهلية المسجلة</h2><ul class="requirements-list">
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
    </ul></div>
    <div class="card"><h2>اعتماد الخدمة</h2><p>{{ $service->rules_approved ? 'بيانات الخدمة معتمدة وفق المرجع المسجل في المنصة.' : 'بيانات الخدمة وقواعدها بانتظار اعتماد الجهة المختصة.' }}</p></div>
  </div>
  <aside><div class="card">
    <h2>معلومات الخدمة</h2>
    <p>الحالة: {{ $service->is_active ? 'مفعّلة' : 'غير مفعّلة' }}</p>
    <p>الرسوم: {{ $service->fee !== null ? $service->fee.' '.$service->currency : 'لم تُحدّد' }}</p>
    <p>المدة التقديرية: {{ $service->estimated_duration ?? 'لم تُحدّد' }}</p>
    @auth
      @if(auth()->user()->role === 'citizen')
        @if($service->is_active && $service->rules_approved)<p><a class="btn btn-outline" href="{{ route('citizen.services.attachments.index', $service) }}">استكمال الصور والمرفقات</a></p>@endif
        @if($reasons)
          <div class="alert alert-danger" role="alert"><h3>لا يمكنك بدء الخدمة حاليًا</h3><ul>@foreach($reasons as $reason)<li>{{ $reason }}</li>@endforeach</ul></div>
          <a href="{{ route('citizen.data') }}">مراجعة بياناتي</a>
        @else
          <form method="POST" action="{{ route('citizen.services.start', $service) }}">@csrf<button type="submit" class="btn btn-primary btn-block">بدء الخدمة</button></form>
        @endif
      @else
        <p>بدء الخدمات متاح لحسابات المواطنين فقط.</p>
      @endif
    @else
      @if($service->is_active)
        <a class="btn btn-primary" href="{{ route('login', ['redirect' => '/citizen/applications/new?service='.$service->code]) }}">سجّل الدخول للتحقق من الأهلية</a>
      @endif
    @endauth
  </div></aside>
</div><p><a href="{{ route('services.index') }}">جميع الخدمات الحكومية</a></p></div></section>
@if(isset($report))<section class="section"><div class="container">@include('components.requirements-result', ['requirementsResult' => $report->toArray()])</div></section>@endif
@endsection
