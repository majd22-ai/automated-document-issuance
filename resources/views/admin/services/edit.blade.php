@extends('layouts.public')
@section('title', 'إعداد الخدمة - لوحة المدير')
@section('content')
<section class="section"><div class="container">
  <h1>{{ $service->exists ? 'تعديل الخدمة' : 'إضافة خدمة' }}</h1>
  <a href="{{ route('admin.services.index') }}">العودة إلى إدارة الخدمات</a>
  @if(session('status'))<div class="alert alert-success" role="status">{{ session('status') }}</div>@endif
  @if($errors->any())<div class="alert alert-danger" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
  <div class="card" style="padding:1.5rem;margin-top:1rem;">
    <p>لا تدخل رسومًا أو شروطًا أو مددًا تقديرية دون مرجع موثوق. الحقول الفارغة تُعرض كبيانات غير متوفرة. تغيير قواعد الأهلية يتطلب تحديث تقييمات المواطنين.</p>
    <form method="POST" action="{{ $service->exists ? route('admin.services.update', $service) : route('admin.services.store') }}">
      @csrf
      @if($service->exists)@method('PATCH')@endif
      <div class="form-group"><label class="form-label" for="service-name">اسم الخدمة</label><input class="form-control" id="service-name" name="name" value="{{ old('name', $service->name) }}" maxlength="200" required></div>
      @if($service->exists)
        <p>الرمز الثابت: {{ $service->code }} — {{ \App\Models\GovernmentService::DOCUMENT_TYPES[$service->document_type] }} — {{ \App\Models\GovernmentService::OPERATIONS[$service->operation] }}</p>
      @else
        <div class="form-group"><label class="form-label" for="service-code">رمز فريد وثابت للخدمة (إنجليزي)</label><input class="form-control" id="service-code" name="code" value="{{ old('code') }}" maxlength="100" required dir="ltr"></div>
        <div class="form-group"><label class="form-label" for="service-type">نوع الوثيقة</label><select class="form-control" id="service-type" name="document_type">@foreach(\App\Models\GovernmentService::DOCUMENT_TYPES as $value => $label)<option value="{{ $value }}" @selected(old('document_type') === $value)>{{ $label }}</option>@endforeach</select></div>
        <div class="form-group"><label class="form-label" for="service-operation">نوع العملية</label><select class="form-control" id="service-operation" name="operation">@foreach(\App\Models\GovernmentService::OPERATIONS as $value => $label)<option value="{{ $value }}" @selected(old('operation') === $value)>{{ $label }}</option>@endforeach</select></div>
      @endif
      <div class="form-group"><label class="form-label" for="service-description">وصف الخدمة</label><textarea class="form-control" id="service-description" name="description" maxlength="10000">{{ old('description', $service->description) }}</textarea></div>
      @php($applicationFields = old('application_fields', $service->application_fields ?? []))
      <div class="form-group"><label class="form-label" for="service-application-fields">حقول نموذج الطلب بصيغة JSON (اختياري)</label>
        <p>اترك [] عند عدم الحاجة. مثال بنيوي: [{"key":"address","label":"العنوان","type":"text","required":true,"max_length":200}]. الأنواع: text وtextarea وinteger وdate. لا تضف متطلبًا إلا وفق قواعد الخدمة المعتمدة.</p>
        <textarea class="form-control" id="service-application-fields" name="application_fields" dir="ltr" rows="5">{{ is_array($applicationFields) ? json_encode($applicationFields, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : $applicationFields }}</textarea>
      </div>
      @foreach(['conditions' => 'الشروط المعتمدة', 'required_documents' => 'المستندات المطلوبة', 'required_fields' => 'حقول إضافية مطلوبة (name / username / email / phone)', 'renewal_statuses' => 'حالات الوثيقة السابقة المقبولة للتجديد', 'terminal_request_statuses' => 'حالات الطلب المنتهية التي لا تمنع طلبًا جديدًا'] as $field => $label)
        @php($fieldValue = old($field, $service->{$field} ?? []))
        <div class="form-group"><label class="form-label" for="service-{{ $field }}">{{ $label }} — عنصر في كل سطر</label><textarea class="form-control" id="service-{{ $field }}" name="{{ $field }}">{{ is_array($fieldValue) ? implode("\n", $fieldValue) : $fieldValue }}</textarea></div>
      @endforeach
      @foreach(['fee' => 'الرسوم (فارغة إن لم تعتمد)', 'currency' => 'العملة', 'estimated_duration' => 'المدة التقديرية المعتمدة', 'minimum_age' => 'الحد الأدنى للعمر (فقط عند وجود قاعدة معتمدة)', 'renewal_window_days' => 'السماح بالتجديد قبل الانتهاء بعدد أيام (فارغ إن لم توجد قاعدة)', 'source_reference' => 'مرجع اعتماد بيانات وقواعد الخدمة'] as $field => $label)
        <div class="form-group"><label class="form-label" for="service-{{ $field }}">{{ $label }}</label><input class="form-control" id="service-{{ $field }}" name="{{ $field }}" value="{{ old($field, $service->{$field}) }}" @if(in_array($field, ['fee', 'minimum_age', 'renewal_window_days'])) type="number" min="0" step="{{ $field === 'fee' ? '0.01' : '1' }}" @else type="text" maxlength="255" @endif></div>
      @endforeach
      @php($verificationRules = old('verification_rules', $service->verification_rules ?? []))
      <p>للمستند الاختياري أضف "optional":true في قاعدته ولا تضع اسمه ضمن المستندات المطلوبة. ملفات الصور بحد افتراضي 2048 كيلوبايت، والمستندات 5120 كيلوبايت؛ ويمكن ضبطها في إعدادات الخادم.</p>
      <div class="form-group"><label class="form-label" for="service-verification-rules">قواعد التحقق المنظمة بصيغة JSON</label>
        <p>مثال بنيوي فقط: {"documents":[{"code":"photo","name":"اسم مطابق لقائمة المستندات المطلوبة","kind":"photo","allowed_mime_types":["image/jpeg","image/png"],"requires_review":true}],"medical":{"required":false,"requires_expiry":true},"renewal_validity":"any","document_authenticity_required":false,"restriction_source":"manual"}.</p>
        <p>القيم any/current/expired تحدد صلاحية وثيقة التجديد وفق القاعدة المعتمدة. المصدر external يحتاج ربطًا فعليًا؛ غيابه يمنع المتابعة. وجود مستند مطلوب دون قاعدة نوع ومراجعة معتمدة ينتج «تعذّر التحقق». لا تنشئ هذه الإعدادات نظام رفع أو نتائج فحوصات.</p>
        <textarea class="form-control" id="service-verification-rules" name="verification_rules" dir="ltr" rows="8">{{ is_array($verificationRules) ? json_encode($verificationRules, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : $verificationRules }}</textarea>
      </div>
      @foreach(['rules_approved' => 'أؤكد اعتماد شروط الخدمة وبياناتها وفق المرجع المسجل', 'requires_driving_clearance' => 'تتطلب اعتماد متطلبات رخصة القيادة', 'is_active' => 'الخدمة مفعّلة'] as $field => $label)
        <div class="form-group"><input type="hidden" name="{{ $field }}" value="0"><label for="service-{{ $field }}"><input id="service-{{ $field }}" type="checkbox" name="{{ $field }}" value="1" @checked(old($field, $service->{$field}))> {{ $label }}</label></div>
      @endforeach
      <button class="btn btn-primary" type="submit">حفظ الخدمة</button>
    </form>
  </div>
  @if($service->exists)
    <div class="card" style="padding:1.5rem;margin-top:1rem;">
      <h2>تسجيل تقييم أهلية موثّق لمواطن</h2>
      <p>للمسؤول المخوّل فقط وبعد التحقق من المرجع الرسمي. هذا إدخال يدوي موثّق وليس تحققًا حكوميًا آليًا. الإصدار الحالي للشروط: {{ $service->rules_version }}.</p>
      <form method="POST" action="{{ route('admin.services.assessments.store', $service) }}">
        @csrf
        <input type="hidden" name="expected_rules_version" value="{{ $service->rules_version }}">
        <div class="form-group"><label class="form-label" for="assessment-username">الرقم الوطني للمواطن</label><input class="form-control" id="assessment-username" name="username" value="{{ old('username') }}" maxlength="255" required></div>
        <div class="form-group"><label class="form-label" for="assessment-reference">مرجع التحقق من الجهة المختصة</label><input class="form-control" id="assessment-reference" name="verification_reference" value="{{ old('verification_reference') }}" maxlength="255" required></div>
        @foreach(['identity_verified' => 'الهوية وارتباط الحساب موثّقان', 'conditions_met' => 'تم التحقق من استيفاء الشروط المسجلة', 'age_requirement_met' => 'تم التحقق من شرط العمر المحدد للخدمة، إن وجد', 'driving_requirements_met' => 'تم اعتماد متطلبات القيادة، إن كانت مطلوبة', 'is_blocked' => 'يوجد مانع رسمي معتمد'] as $field => $label)
          <div class="form-group"><input type="hidden" name="{{ $field }}" value="0"><label for="assessment-{{ $field }}"><input id="assessment-{{ $field }}" type="checkbox" name="{{ $field }}" value="1" @checked(old($field, false))> {{ $label }}</label></div>
        @endforeach
        <div class="form-group"><label class="form-label" for="assessment-reason">سبب المانع الداخلي (مطلوب عند وجوده، لا يعرض للمواطن)</label><input class="form-control" id="assessment-reason" name="block_reason" value="{{ old('block_reason') }}" maxlength="255"></div>
        <div class="form-group"><label class="form-label" for="assessment-public-reason">رسالة المانع المسموح بعرضها للمواطن (اختياري)</label><input class="form-control" id="assessment-public-reason" name="public_block_reason" value="{{ old('public_block_reason') }}" maxlength="255"></div>
        <button class="btn btn-outline" type="submit">حفظ التقييم الموثّق</button>
      </form>
    </div>
  @endif
</div></section>
@endsection
