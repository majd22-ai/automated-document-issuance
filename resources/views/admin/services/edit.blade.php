@extends('layouts.public')
@section('title', 'إعداد الخدمة - لوحة المدير')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/service-settings.css') }}?v={{ filemtime(public_path('css/service-settings.css')) }}">
@endpush
@push('scripts')
<script src="{{ asset('js/service-settings.js') }}?v={{ filemtime(public_path('js/service-settings.js')) }}" defer></script>
@endpush
@section('content')
@php
 $hasError = fn($prefixes) => collect($errors->keys())->contains(fn($key) => collect((array)$prefixes)->contains(fn($prefix) => $key === $prefix || str_starts_with($key,$prefix.'.')));
 $advancedFields = ['application_fields','verification_rules','workflow_rules','issuance_rules','integration_rules','minimum_age','renewal_window_days','renewal_statuses','terminal_request_statuses','require_separate_approver','requires_driving_clearance'];
 $listText = fn($value) => is_array($value) ? implode("\n",$value) : $value;
 $requiredFields = old('required_fields',$service->required_fields ?? []);
 $requiredFields = is_array($requiredFields) ? $requiredFields : preg_split('/\R/u',(string)$requiredFields);
@endphp
<section class="ops-section service-settings"><div class="container">
 <div class="ops-page-head"><h1>{{ $service->exists ? 'إعداد الخدمة' : 'إضافة خدمة جديدة' }}</h1><p class="ops-help">ابدأ بمعلومات الخدمة، ثم راجع المتطلبات والرسوم وحالة النشر.</p>
 <div class="ops-toolbar"><a href="{{ route('admin.services.index') }}">العودة إلى الخدمات</a>@if($service->exists)<a class="btn btn-outline" href="{{ route('admin.services.assessments.create',$service) }}">تقييم أهلية مواطن</a>@endif</div></div>
 @if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
 @if($errors->any())<div class="alert alert-danger" role="alert"><strong>راجع الحقول المشار إليها قبل الحفظ.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
 <form method="POST" action="{{ $service->exists ? route('admin.services.update',$service) : route('admin.services.store') }}" data-service-settings>
 @csrf @if($service->exists)@method('PATCH')@endif
 <details class="service-setting-panel" open>
  <summary><span class="setting-number">1</span><span><strong>معلومات الخدمة</strong><small>اسم واضح ووصف مختصر لما تقدمه</small></span><x-icon name="chevron-down" /></summary>
  <div class="setting-body">
   <div class="setting-field"><label for="service-name">اسم الخدمة <span aria-label="مطلوب">*</span></label><input class="form-control" id="service-name" name="name" value="{{ old('name',$service->name) }}" maxlength="200" required>@error('name')<p class="setting-error">{{ $message }}</p>@enderror</div>
   <div class="setting-field"><label for="service-description">وصف مختصر</label><textarea class="form-control" id="service-description" name="description" maxlength="10000" rows="3" placeholder="ما الذي يحصل عليه المواطن من هذه الخدمة؟">{{ old('description',$service->description) }}</textarea>@error('description')<p class="setting-error">{{ $message }}</p>@enderror</div>
   @if($service->exists)
   <p class="setting-meta">{{ \App\Models\GovernmentService::DOCUMENT_TYPES[$service->document_type] ?? $service->document_type }} · {{ \App\Models\GovernmentService::OPERATIONS[$service->operation] ?? $service->operation }} <span dir="ltr">{{ $service->code }}</span><small>نوع الخدمة ورمزها ثابتان لحماية الطلبات السابقة.</small></p>
   @else
   <div class="setting-grid">
    <div class="setting-field"><label for="service-type">نوع الوثيقة *</label><select class="form-control" id="service-type" name="document_type" required>@foreach(\App\Models\GovernmentService::DOCUMENT_TYPES as $value=>$label)<option value="{{ $value }}" @selected(old('document_type') === $value)>{{ $label }}</option>@endforeach</select>@error('document_type')<p class="setting-error">{{ $message }}</p>@enderror</div>
    <div class="setting-field"><label for="service-operation">نوع الخدمة *</label><select class="form-control" id="service-operation" name="operation" required>@foreach(\App\Models\GovernmentService::OPERATIONS as $value=>$label)<option value="{{ $value }}" @selected(old('operation') === $value)>{{ $label }}</option>@endforeach</select>@error('operation')<p class="setting-error">{{ $message }}</p>@enderror</div>
   </div>
   <div class="setting-field"><label for="service-code">رمز الخدمة *</label><input class="form-control" id="service-code" name="code" value="{{ old('code') }}" maxlength="100" required dir="ltr" placeholder="example-service"><small>معرّف إنجليزي فريد، يُحدد مرة واحدة عند إنشاء الخدمة.</small>@error('code')<p class="setting-error">{{ $message }}</p>@enderror</div>
   @endif
  </div>
 </details>
 <details class="service-setting-panel" @if($hasError(['conditions','required_documents','required_fields'])) open @endif>
  <summary><span class="setting-number">2</span><span><strong>الشروط والمستندات</strong><small>ما الذي يحتاجه المواطن للتقديم؟</small></span><x-icon name="chevron-down" /></summary>
  <div class="setting-body">
   <div class="setting-grid">@foreach(['conditions'=>'شروط الحصول على الخدمة','required_documents'=>'المستندات المطلوبة'] as $field=>$label)
    <div class="setting-field"><label for="service-{{ $field }}">{{ $label }}</label><textarea class="form-control" id="service-{{ $field }}" name="{{ $field }}" rows="5" placeholder="اكتب كل بند في سطر مستقل">{{ $listText(old($field,$service->{$field} ?? [])) }}</textarea><small>بند واحد في كل سطر، وفق المتطلبات المعتمدة.</small>@error($field)<p class="setting-error">{{ $message }}</p>@enderror</div>
   @endforeach</div>
   <fieldset class="setting-field"><legend>بيانات الحساب المطلوبة من المواطن</legend><p class="ops-help">اختر البيانات التي يجب استكمالها قبل بدء هذه الخدمة.</p><input type="hidden" name="required_fields" value=""><div class="setting-choices">@foreach(['name'=>'الاسم','username'=>'اسم المستخدم','email'=>'البريد الإلكتروني','phone'=>'رقم الجوال'] as $value=>$label)<label><input type="checkbox" name="required_fields[]" value="{{ $value }}" @checked(in_array($value,$requiredFields,true))> {{ $label }}</label>@endforeach</div>@error('required_fields')<p class="setting-error">{{ $message }}</p>@enderror</fieldset>
  </div>
 </details>
 <details class="service-setting-panel" @if($hasError(['fee','currency','estimated_duration'])) open @endif>
  <summary><span class="setting-number">3</span><span><strong>الرسوم ومدة الإنجاز</strong><small>تُترك فارغة إذا لم تُعتمد بعد</small></span><x-icon name="chevron-down" /></summary>
  <div class="setting-body"><div class="setting-grid">
   <div class="setting-field"><label for="service-fee">قيمة الرسوم</label><input class="form-control" id="service-fee" name="fee" type="number" min="0" step="0.01" value="{{ old('fee',$service->fee) }}" placeholder="غير محددة"><small>أدخل 0 فقط إذا كانت الخدمة مجانية بقرار معتمد.</small>@error('fee')<p class="setting-error">{{ $message }}</p>@enderror</div>
   <div class="setting-field"><label for="service-currency">العملة</label><input class="form-control" id="service-currency" name="currency" maxlength="20" value="{{ old('currency',$service->currency) }}">@error('currency')<p class="setting-error">{{ $message }}</p>@enderror</div>
  </div><div class="setting-field"><label for="service-estimated_duration">مدة الإنجاز المعتمدة</label><input class="form-control" id="service-estimated_duration" name="estimated_duration" maxlength="255" value="{{ old('estimated_duration',$service->estimated_duration) }}" placeholder="المدة كما وردت في مرجع الخدمة">@error('estimated_duration')<p class="setting-error">{{ $message }}</p>@enderror</div></div>
 </details>

 <details class="service-setting-panel" @if($hasError(['source_reference','rules_approved','is_active'])) open @endif>
  <summary><span class="setting-number">4</span><span><strong>الاعتماد وإتاحة الخدمة</strong><small>راجع المرجع قبل تفعيل الخدمة للمواطنين</small></span><x-icon name="chevron-down" /></summary>
  <div class="setting-body">
   <div class="setting-field"><label for="service-source_reference">مرجع اعتماد الخدمة</label><input class="form-control" id="service-source_reference" name="source_reference" value="{{ old('source_reference',$service->source_reference) }}" maxlength="255"><small>اسم القرار أو المرجع الذي اعتمدت بناءً عليه الشروط والرسوم. مطلوب عند الاعتماد.</small>@error('source_reference')<p class="setting-error">{{ $message }}</p>@enderror</div>
   <div class="setting-field"><input type="hidden" name="rules_approved" value="0"><label class="setting-check"><input id="service-rules_approved" type="checkbox" name="rules_approved" value="1" @checked(old('rules_approved',$service->rules_approved))> راجعت الشروط والرسوم وأؤكد اعتمادها وفق المرجع المذكور.</label>@error('rules_approved')<p class="setting-error">{{ $message }}</p>@enderror</div>
   <div class="setting-field"><label for="service-is_active">ظهور الخدمة للمواطنين</label><select class="form-control" id="service-is_active" name="is_active"><option value="0" @selected(!old('is_active',$service->is_active))>غير متاحة — إعداد أو إيقاف مؤقت</option><option value="1" @selected(old('is_active',$service->is_active))>متاحة — يمكن للمواطن بدء الخدمة</option></select><small>لا يمكن إتاحة الخدمة قبل اعتماد شروطها ومرجعها.</small>@error('is_active')<p class="setting-error">{{ $message }}</p>@enderror</div>
  </div>
 </details>
 <details class="service-setting-panel setting-advanced" @if($hasError($advancedFields)) open @endif>
  <summary><x-icon name="settings" /><span><strong>خيارات متقدمة للمختص</strong><small>الأهلية، نموذج الطلب، المرفقات، الإصدار والربط الخارجي</small></span><x-icon name="chevron-down" /></summary>
  <div class="setting-body"><p class="setting-note">لا تحتاج تعديل هذا القسم عند تغيير اسم الخدمة أو وصفها أو رسومها. تغيير قواعد الأهلية يتطلب إعادة تقييم المواطنين.</p>
   @include('admin.services.technical')
  </div>
 </details>
 <div class="setting-save-bar"><span>لا تُطبّق التغييرات إلا بعد الحفظ.</span><button class="btn btn-primary" type="submit">حفظ إعدادات الخدمة</button><a href="{{ route('admin.services.index') }}" class="btn btn-outline">إلغاء</a></div>
 </form>
</div></section>
@endsection
