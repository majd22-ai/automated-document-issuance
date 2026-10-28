<dl>
  <dt>الرقم المرجعي</dt><dd>{{ $application->reference }}</dd>
  <dt>الخدمة المسجلة وقت التقديم</dt><dd>{{ $application->service_name ?? $application->service_code }}</dd>
  <dt>نوع الطلب</dt><dd>{{ \App\Models\GovernmentService::OPERATIONS[$application->request_type] ?? 'غير متوفر للسجل السابق' }}</dd>
  <dt>الحالة</dt><dd>{{ $application->statusLabel() }}</dd>
  @if($application->identityCardForm)
  <dt>مرحلة استمارة البطاقة</dt><dd>{{ $application->identityCardForm->statusLabel() }}</dd>
  <dt>خطوة البصمة ومصدرها</dt><dd>{{ $application->identityCardForm->fingerprintStatusLabel() }}</dd>
  @if(auth()->user()->role === 'citizen')<dt>المصادقة والبيانات التفصيلية</dt><dd><a href="{{ route('citizen.identity-card.show', $application->identityCardForm) }}">فتح استمارة البطاقة وحالة المصادقة</a></dd>@endif
  @endif
  <dt>تاريخ التقديم</dt><dd>{{ $application->submitted_at?->format('Y-m-d H:i') ?? $application->created_at?->format('Y-m-d') ?? 'غير متوفر' }}</dd>
  @foreach($application->service_snapshot['application_fields'] ?? [] as $field)
    <dt>{{ $field['label'] }}</dt><dd>{{ $application->request_data[$field['key']] ?? 'غير مسجل' }}</dd>
  @endforeach
  @if($application->notes)<dt>الملاحظات</dt><dd>{{ $application->notes }}</dd>@endif
  @if($application->citizen_visible_reason)<dt>سبب الاستكمال أو الرفض المسموح بعرضه</dt><dd>{{ $application->citizen_visible_reason }}</dd>@endif
</dl>
@include('components.review-history')
@include('components.integration-status')
@can('viewVerification',$application)<p><a class="btn btn-outline" href="{{ route(auth()->user()->role==='citizen'?'citizen.verifications.show':'employee.verifications.show',$application->id) }}">حالة التحقق الإلكتروني ومصدره</a></p>@endcan
@if($application->government_service_id)<p><a class="btn btn-primary" href="{{ route((auth()->user()->role==='employee'?'employee':'citizen').'.applications.operations',$application->id) }}">المواعيد والفحوصات والرسوم</a></p>@endif
<h2>المرفقات المرتبطة بهذا الطلب</h2>
@include('components.attachment-list', ['attachments' => $application->attachments])
@if(auth()->user()->role === 'citizen' && $application->government_service_id)<p><a class="btn btn-outline" href="{{ route('citizen.applications.attachments.index', $application->id) }}">عرض وإدارة مرفقات الطلب</a></p>@endif
@if(auth()->user()->role === 'employee')<p><a href="{{ route('employee.attachments.index') }}">مراجعة المرفقات</a></p>@endif
@if($application->requirements_result)
  @include('components.requirements-result', ['requirementsResult' => $application->requirements_result])
@endif
@if($application->government_service_id)
  <form method="POST" action="{{ auth()->user()->role === 'citizen' ? route('citizen.applications.requirements.check', $application->id) : route('employee.applications.requirements.check', $application->id) }}">
    @csrf
    <button type="submit" class="btn btn-outline">إعادة التحقق من المتطلبات الحالية</button>
  </form>
@endif
