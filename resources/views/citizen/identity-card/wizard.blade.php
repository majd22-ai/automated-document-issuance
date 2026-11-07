@extends('layouts.public')
@section('title', 'استمارة طلب البطاقة الشخصية')
@push('styles')<link rel="stylesheet" href="{{ asset('css/identity-card-wizard.css') }}?v={{ filemtime(public_path('css/identity-card-wizard.css')) }}">@endpush
@push('scripts')<script src="{{ asset('js/identity-card-wizard.js') }}" defer></script>@endpush
@section('content')
@php
    $steps = [1=>'البيانات الشخصية',2=>'بيانات الميلاد',3=>'العنوان والحالة الاجتماعية',4=>'الصورة الشخصية',5=>'الشاهدان',6=>'المراجعة والإقرار',7=>'تأكيد التقديم'];
    $witnessFields = ['name'=>'الاسم الكامل','employer'=>'جهة العمل','work_address'=>'مقر جهة العمل','phone'=>'رقم التلفون','document_type'=>'نوع البطاقة','document_number'=>'رقم البطاقة','issued_place'=>'محل صدور البطاقة','issued_at'=>'تاريخ صدورها','address'=>'عنوان السكن','nearby_landmark'=>'جوار / أقرب معلم'];
    $completion = (int) round(min(7, max(1, (int) $form->current_step)) / 7 * 100);
@endphp
<section class="section identity-wizard"><div class="container">
    <a href="{{ route('citizen.identity-card.index') }}">جميع الاستمارات</a>
    <div class="identity-heading"><div><h1>استمارة طلب البطاقة الشخصية</h1><p>مرجع الاستمارة الداخلي: <b dir="ltr">{{ $form->reference }}</b> · {{ $form->statusLabel() }}</p></div><p>الخطوة {{ $step }} من 7</p></div>
    <nav aria-label="مراحل الاستمارة"><ol class="identity-progress">@foreach($steps as $number => $title)<li class="{{ $number === $step ? 'is-current' : '' }}" @if($number === $step) aria-current="step" @endif><span>{{ $number }}</span>{{ $title }}</li>@endforeach</ol></nav>
    <label for="identity-completion">نسبة تقدم الاستمارة المحفوظة: {{ $completion }}٪</label>
    <progress id="identity-completion" max="100" value="{{ $completion }}">{{ $completion }}٪</progress>
    @if(session('status') || session('success'))<p class="identity-notice" role="status">{{ session('status') ?? session('success') }}</p>@endif
    @if($errors->any())<div class="identity-error" role="alert"><p>تعذر حفظ الاستمارة. راجع الحقول التالية:</p><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <h2>{{ $steps[$step] }}</h2>
    @if($form->correctionIsOpen())<p class="identity-notice">مطلوب تصحيح النسخة {{ $form->revision }}: {{ $form->correction_reason }}</p>@endif
    @if($step === 7)
        <p>حالة خطوة البصمة: {{ $form->fingerprintStatusLabel() }}</p>
        @if($decision = $form->administrativeDecisions()->whereNotNull('citizen_reason')->latest('id')->first())<p class="identity-notice">سبب الإجراء المسموح بعرضه: {{ $decision->citizen_reason }}</p>@endif
        <p><a class="btn btn-outline" href="{{ route('identity-workflow.print', $form) }}">معاينة نموذج الطلب والطباعة</a></p>
        @include('components.identity-card-attestation', ['identityForm' => $form])
        @if($form->application?->status === 'requires_correction')<p><a class="btn btn-outline" href="{{ route('citizen.applications.attachments.index', $form->application->id) }}">استبدال الصورة أو استكمال المرفقات المطلوبة من الموظف</a></p>@endif
        <div class="identity-notice" role="status"><h3>تم تقديم الاستمارة</h3><p>مرجع الطلب الداخلي: <b dir="ltr">{{ $form->application?->reference ?? $form->reference }}</b></p><p>هذا مرجع للمتابعة داخل المنصة، وليس رقم بطاقة أو وثيقة رسمية. التقديم لا يعني اعتماد البيانات أو إصدار البطاقة.</p></div>
        @include('components.identity-card-summary', ['identityForm'=>$form])
        <a class="btn btn-primary" href="{{ route('citizen.identity-card.index') }}">العودة إلى الاستمارات</a>
    @else
        <form method="POST" action="{{ $step === 6 ? route('citizen.identity-card.submit', $form) : route('citizen.identity-card.save', $form) }}" enctype="multipart/form-data" novalidate>
            @csrf<input type="hidden" name="step" value="{{ $step }}"><input type="hidden" name="version" value="{{ $form->version }}">
            @if($step <= 3)
                <p>الحقول المشار إليها بـ * مطلوبة للانتقال إلى الخطوة التالية. يمكنك حفظ مسودة غير مكتملة.</p>
                <div class="identity-field-grid identity-panel" @if($step === 3) data-residence-selects data-location-values="names" @endif>
                @if($step === 3)
                    @include('components.residence-selects', ['prefix'=>'identity-residence', 'byName'=>true, 'governorateName'=>'data[residence_governorate]', 'districtName'=>'data[residence_district]', 'selectedGovernorate'=>old('data.residence_governorate',data_get($form->data,'residence_governorate')), 'selectedDistrict'=>old('data.residence_district',data_get($form->data,'residence_district'))])
                @endif
                @foreach($fields[$step] as $key => $field)
                    @if($step === 2 && $loop->first)@include('components.birth-details')@endif
                    @continue($step === 2 && str_starts_with($key,'birth_'))
                    @continue($step === 3 && in_array($key,['residence_governorate','residence_district']))
                    @php($errorKey = 'data.'.$key)
                    <div class="identity-field"><label for="field-{{ $key }}">{{ $field['label'] }} @if($field['required'] ?? false)<span aria-label="مطلوب">*</span>@endif</label>
                    @if(($field['type'] ?? 'text') === 'select')
                        <select id="field-{{ $key }}" name="data[{{ $key }}]" @required($field['required'] ?? false) @error($errorKey) aria-invalid="true" aria-describedby="error-{{ $key }}" @enderror><option value="">اختر</option>@foreach($field['options'] as $value => $label)<option value="{{ $value }}" @selected((string)old($errorKey, data_get($form->data,$key)) === (string)$value)>{{ $label }}</option>@endforeach</select>
                    @else
                        <input id="field-{{ $key }}" type="{{ $field['type'] ?? 'text' }}" name="data[{{ $key }}]" value="{{ old($errorKey, data_get($form->data,$key)) }}" @required($field['required'] ?? false) @error($errorKey) aria-invalid="true" aria-describedby="error-{{ $key }}" @enderror>
                    @endif
                    @error($errorKey)<p class="identity-field-error" id="error-{{ $key }}">{{ $message }}</p>@enderror</div>
                @endforeach
                </div>
            @elseif($step === 4)
                <div class="identity-panel" aria-labelledby="photo-instructions-title">
                    <h3 id="photo-instructions-title">تعليمات رفع الصورة الشخصية</h3>
                    <p>عزيزي المواطن، يرجى التأكد من مطابقة صورتك للشروط التالية قبل رفعها:</p>
                    <ol class="requirements-list">
                        <li>أن تكون الصورة حديثة وملونة.</li>
                        <li>أن تكون الخلفية بيضاء وموحدة.</li>
                        <li>أن يكون الوجه مقابلاً للكاميرا مباشرة.</li>
                        <li>أن تكون جميع ملامح الوجه واضحة.</li>
                        <li>أن يظهر الرأس كاملاً دون قص.</li>
                        <li>أن تكون الصورة بإضاءة جيدة وخالية من الظلال.</li>
                        <li>يمنع استخدام الفلاتر أو المؤثرات أو تعديل ملامح الوجه.</li>
                        <li>يمنع رفع صور الأشخاص الآخرين أو الصور الجماعية.</li>
                        <li>يجب رفع صورة أصلية ملتقطة بالكاميرا وليست صورة لشاشة هاتف أو صورة مطبوعة.</li>
                    </ol>
                    <p class="identity-notice"><strong>تنبيه:</strong> سيتم فحص الصورة إلكترونياً، وقد يُطلب منك إعادة رفعها إذا لم تستوفِ شروط القبول.</p>
                </div>
                <div class="identity-panel"><label for="identity-photo">الصورة الشخصية</label><p id="photo-help">ارفع صورة شخصية واضحة بصيغة JPG أو JPEG أو PNG، بحد أقصى {{ config('identity_card.photo.max_kb', 2048) }} كيلوبايت، وأبعاد لا تقل عن {{ config('identity_card.photo.min_width', 600) }} × {{ config('identity_card.photo.min_height', 600) }} بكسل. رفع الصورة لا يغني عن إجراءات الحضور والتحقق لدى الجهة المختصة.</p>
                    <input id="identity-photo" name="photo" type="file" accept=".jpg,.jpeg,.png,image/jpeg,image/png" @required(!$form->photo) data-identity-photo aria-describedby="photo-help photo-error" @error('photo') aria-invalid="true" @enderror>
                    <p id="photo-error" class="identity-field-error">@error('photo'){{ $message }}@enderror</p>
                    <p class="identity-notice" role="status" data-photo-check-status hidden>جارٍ فحص جودة الصورة إلكترونيًا، يرجى الانتظار…</p>
                    <img class="identity-photo" data-identity-photo-preview @if($form->photo) src="{{ route('citizen.identity-card.photo', $form) }}" @else hidden @endif alt="معاينة الصورة الشخصية">
                    @if($form->photo)<p>توجد صورة محفوظة. يمكنك اختيار صورة بديلة.</p>@endif
                </div>
            @elseif($step === 5)
                <p>سجّل بيانات كل شاهد كما في بطاقته، وأرفق صورة سلفي يظهر فيها وجهه وهو يحمل بطاقته الشخصية بدل التوقيع والبصمة في هذه الاستمارة الإلكترونية.</p>
                <p class="identity-notice">يجب أن يكون وجه الشاهد والبطاقة ظاهرين بوضوح، مع إظهار بيانات البطاقة دون قص أو انعكاس. رفع الصورة لا يمثل وحده إثبات الحضور أو اعتماد الهوية؛ يراجع الموظف المختص البيانات والمرفق.</p>
                @push('scripts')<script src="{{ asset('js/witness-selfies.js') }}?v={{ filemtime(public_path('js/witness-selfies.js')) }}" defer></script>@endpush
                @foreach([0,1] as $index)
                    @php($witness = $form->witnesses->firstWhere('position', $index + 1))
                    <fieldset class="identity-panel"><legend>الشاهد {{ $index === 0 ? 'الأول' : 'الثاني' }}</legend><div class="identity-field-grid">
                    @foreach($witnessFields as $key => $label)
                        @php($errorKey = 'witnesses.'.$index.'.'.$key)
                        <div class="identity-field"><label for="witness-{{ $index }}-{{ $key }}">{{ $label }} @unless(in_array($key, ['employer','work_address','nearby_landmark'], true))<span aria-label="مطلوب">*</span>@endunless</label><input id="witness-{{ $index }}-{{ $key }}" name="witnesses[{{ $index }}][{{ $key }}]" @required(!in_array($key, ['employer','work_address','nearby_landmark'], true)) type="{{ $key === 'issued_at' ? 'date' : ($key === 'phone' ? 'tel' : 'text') }}" value="{{ old($errorKey, $key === 'issued_at' && data_get($witness, $key) instanceof \DateTimeInterface ? data_get($witness, $key)->format('Y-m-d') : data_get($witness, $key)) }}" @error($errorKey) aria-invalid="true" aria-describedby="witness-error-{{ $index }}-{{ $key }}" @enderror>
                        @error($errorKey)<p class="identity-field-error" id="witness-error-{{ $index }}-{{ $key }}">{{ $message }}</p>@enderror</div>
                    @endforeach
                    </div>
                    <div class="identity-field" data-witness-selfie>
                        <label for="witness-selfie-{{ $index }}">صورة سلفي للشاهد وهو يحمل بطاقته الشخصية *</label>
                        <p id="witness-selfie-help-{{ $index }}">JPG أو JPEG أو PNG، بحد أقصى 2 ميجابايت، وأبعاد لا تقل عن 600 × 600 بكسل. يمكنك حفظ مسودة دون الصورة، ويلزم إرفاقها للمتابعة. لا ترفع صورة البطاقة وحدها.</p>
                        <input id="witness-selfie-{{ $index }}" type="file" name="witnesses[{{ $index }}][selfie]" accept="image/jpeg,image/png,.jpg,.jpeg,.png" aria-describedby="witness-selfie-help-{{ $index }} witness-selfie-error-{{ $index }}">
                        <p id="witness-selfie-error-{{ $index }}" class="identity-field-error">@error('witnesses.'.$index.'.selfie'){{ $message }}@enderror</p>
                        <img class="identity-photo" data-selfie-preview @if($witness?->selfie_path) src="{{ route('citizen.identity-card.witness-selfie', ['form'=>$form,'position'=>$index+1]) }}" @else hidden @endif alt="صورة الشاهد {{ $index === 0 ? 'الأول' : 'الثاني' }} وهو يحمل بطاقته">
                        @if($witness?->selfie_path)<p>توجد صورة محفوظة. اختيار صورة جديدة يستبدلها بعد نجاح الحفظ.</p>@endif
                    </div><p>الحضور والمراجعة يسجلهما الموظف المختص.</p></fieldset>
                @endforeach
            @elseif($step === 6)
                <input type="hidden" name="review_token" value="{{ $reviewToken }}">
                <nav class="identity-actions" aria-label="تعديل بيانات الملخص">@foreach([1=>'البيانات الشخصية',2=>'الميلاد والجنسية',3=>'العنوان والحالة',4=>'الصورة',5=>'الشاهدان'] as $editStep=>$editLabel)<a href="{{ route('citizen.identity-card.show', ['form'=>$form,'step'=>$editStep]) }}">تعديل {{ $editLabel }}</a>@endforeach</nav>
                <p>راجع جميع البيانات والصورة وبيانات الشاهدين قبل تقديم الاستمارة. يمكنك العودة لتعديل أي خطوة.</p>
                @include('components.identity-card-summary', ['identityForm'=>$form])
                <div class="identity-panel"><label class="identity-declaration"><input type="checkbox" name="declaration" value="1" required @checked(old('declaration')) aria-describedby="declaration-error">أقر بصحة البيانات المدخلة وأتحمل مسؤوليتها، وأفهم أن تقديم الاستمارة لا يعني إصدار وثيقة رسمية.</label>@error('declaration')<p id="declaration-error" class="identity-field-error">{{ $message }}</p>@enderror</div>
            @endif
            <div class="identity-actions">
                @if($step > 1)<a class="btn btn-outline" href="{{ route('citizen.identity-card.show', ['form'=>$form,'step'=>$step - 1]) }}">السابق</a>@endif
                <button class="btn btn-outline" name="action" value="save" type="submit" @if($step === 6) formaction="{{ route('citizen.identity-card.save', $form) }}" @endif>حفظ كمسودة</button>
                <button class="btn btn-primary" name="action" value="{{ $step === 6 ? 'submit' : 'next' }}" type="submit">{{ $step === 6 ? 'تقديم الاستمارة' : 'حفظ ومتابعة' }}</button>
            </div>
        </form>
    @endif
</div></section>
@endsection
