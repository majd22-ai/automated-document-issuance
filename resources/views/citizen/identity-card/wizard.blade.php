@extends('layouts.public')
@section('title', 'استمارة طلب البطاقة الشخصية')
@push('styles')<link rel="stylesheet" href="{{ asset('css/identity-card-wizard.css') }}?v={{ filemtime(public_path('css/identity-card-wizard.css')) }}">@endpush
@push('scripts')<script src="{{ asset('js/identity-card-wizard.js') }}" defer></script>@endpush
@push('scripts')<script src="{{ asset('js/witness-selfies.js') }}?v={{ filemtime(public_path('js/witness-selfies.js')) }}" defer></script>@endpush
@push('scripts')<script src="{{ asset('js/witness-registry-lookup.js') }}?v={{ filemtime(public_path('js/witness-registry-lookup.js')) }}" defer></script>@endpush
@section('content')
@php
    $steps = [1=>'البيانات الشخصية',2=>'بيانات الميلاد',3=>'العنوان والحالة الاجتماعية',4=>'الصورة الشخصية',5=>'الشاهدان',6=>'مصادقة العاقل',7=>$form->submitted_at && ! $form->correctionIsOpen() ? 'تأكيد التقديم' : 'المراجعة والإقرار'];
    $witnessFields = ['document_type'=>'نوع البطاقة','document_number'=>'رقم البطاقة','name'=>'الاسم الكامل','phone'=>'رقم التلفون','issued_place'=>'محل صدور البطاقة','issued_at'=>'تاريخ صدورها','employer'=>'جهة العمل','work_address'=>'مقر جهة العمل','address'=>'عنوان السكن','nearby_landmark'=>'جوار / أقرب معلم'];
    $completion = (int) round(min(7, max(1, $step)) / count($steps) * 100);
@endphp
<section class="section identity-wizard"><div class="container">
    <a href="{{ route('citizen.identity-card.index') }}">جميع الاستمارات</a>
    <div class="identity-heading"><div><h1>استمارة طلب البطاقة الشخصية</h1><p>مرجع الاستمارة الداخلي: <b dir="ltr">{{ $form->reference }}</b> · {{ $form->statusLabel() }}</p></div><p>الخطوة {{ $step }} من 7</p></div>
    <nav aria-label="مراحل الاستمارة"><ol class="identity-progress">@foreach($steps as $number => $title)<li class="{{ $number === $step ? 'is-current' : '' }}" @if($number === $step) aria-current="step" @endif><span>{{ $number }}</span>{{ $title }}</li>@endforeach</ol></nav>
    <label for="identity-completion">نسبة تقدم الاستمارة: {{ $completion }}٪</label>
    <progress id="identity-completion" max="100" value="{{ $completion }}">{{ $completion }}٪</progress>
    @if(session('status') || session('success'))<p class="identity-notice" role="status">{{ session('status') ?? session('success') }}</p>@endif
    @if($errors->any())
        @php
            $reviewSubmission = $step === 7 && (! $form->submitted_at || $form->correctionIsOpen());
        @endphp
        <div class="identity-error" role="alert">
            <h3>{{ $reviewSubmission ? 'لم يتم تقديم الطلب' : 'تعذر حفظ الاستمارة' }}</h3>
            <p>
                @if($reviewSubmission && $errors->has('service'))
                    هذه أسباب تخص أهلية الخدمة واعتماد متطلباتها، وليست أخطاء في بيانات الحقول. لن يُنشأ رقم طلب حتى تستكمل الجهة المختصة المراجعة المطلوبة.
                @elseif($reviewSubmission)
                    راجع أسباب التعذر أدناه وأصلح ما أُشير إليه قبل إعادة التقديم.
                @else
                    راجع الحقول التالية ثم حاول مرة أخرى.
                @endif
            </p>
            <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            @if($reviewSubmission && $errors->has('service'))
                <p>بياناتك الحالية محفوظة كمسودة. لا تعِد إدخالها؛ بعد استكمال المراجعة، افتح خطوة المراجعة والإقرار وحاول التقديم مجددًا.</p>
                <div class="identity-actions">
                    <a class="btn btn-outline" href="{{ route('citizen.applications.index') }}">التحقق من طلباتي</a>
                    <a class="btn btn-outline" href="{{ $form->service->publicUrl() }}">تفاصيل الخدمة ومتطلباتها</a>
                    <a class="btn btn-outline" href="{{ route('citizen.services.attachments.index', $form->service) }}">رفع المستندات المطلوبة ومتابعة مراجعتها</a>
                </div>
            @endif
        </div>
    @endif
    <h2>{{ $steps[$step] }}</h2>
    @if($form->correctionIsOpen())<p class="identity-notice">مطلوب تصحيح النسخة {{ $form->revision }}: {{ $form->correction_reason }}</p>@endif
    @if($step === 7 && $form->submitted_at && ! $form->correctionIsOpen())
        <p>حالة خطوة البصمة: {{ $form->fingerprintStatusLabel() }}</p>
        @if($decision = $form->administrativeDecisions()->whereNotNull('citizen_reason')->latest('id')->first())<p class="identity-notice">سبب الإجراء المسموح بعرضه: {{ $decision->citizen_reason }}</p>@endif
        <p><a class="btn btn-outline" href="{{ route('identity-workflow.print', $form) }}">معاينة نموذج الطلب والطباعة</a></p>
        @include('components.identity-card-attestation', ['identityForm' => $form])
        @if($form->application?->status === 'requires_correction')<p><a class="btn btn-outline" href="{{ route('citizen.applications.attachments.index', $form->application->id) }}">استبدال الصورة أو استكمال المرفقات المطلوبة من الموظف</a></p>@endif
        <div class="identity-notice" role="status"><h3>تم تقديم الاستمارة</h3><p>مرجع الطلب الداخلي: <b dir="ltr">{{ $form->application?->reference ?? $form->reference }}</b></p><p>هذا مرجع للمتابعة داخل المنصة، وليس رقم بطاقة أو وثيقة رسمية. التقديم لا يعني اعتماد البيانات أو إصدار البطاقة.</p></div>
        @include('components.identity-card-summary', ['identityForm'=>$form])
        <a class="btn btn-primary" href="{{ route('citizen.identity-card.index') }}">العودة إلى الاستمارات</a>
    @elseif($step === 6)
        <p>بعد استكمال بيانات الشاهدين، اختر العاقل المسؤول عن حي إقامتك واطلب المصادقة. لن تنتقل إلى مراجعة الطلب إلا بعد تسجيل موافقة العاقل.</p>
        @include('components.identity-card-attestation', ['identityForm' => $form])
        <div class="identity-actions"><a class="btn btn-outline" href="{{ route('citizen.identity-card.show', ['form' => $form, 'step' => 5]) }}">السابق: الشاهدان</a></div>
    @else
        <form method="POST" action="{{ $step === 7 ? route('citizen.identity-card.submit', $form) : route('citizen.identity-card.save', $form) }}" enctype="multipart/form-data" novalidate>
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
                    @php
                        $errorKey = 'data.'.$key;
                    @endphp
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
                @foreach([0, 1] as $index)
                    @php
                        $witness = $form->witnesses->firstWhere('position', $index + 1);
                        $storedIssuedAt = data_get($witness, 'issued_at');
                        $storedIssuedAt = $storedIssuedAt instanceof \DateTimeInterface ? $storedIssuedAt->format('Y-m-d') : (string) $storedIssuedAt;
                        $issuedAtValue = old('witnesses.'.$index.'.issued_at', $storedIssuedAt);
                        $issuedAtParts = preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', (string) $issuedAtValue, $dateMatches) ? ['year' => $dateMatches[1], 'month' => $dateMatches[2], 'day' => $dateMatches[3]] : [];
                    @endphp
                    <fieldset class="identity-panel" data-witness-registry-lookup data-lookup-url="{{ route('citizen.identity-card.witness.lookup', $form) }}"><legend>الشاهد {{ $index === 0 ? 'الأول' : 'الثاني' }}</legend><div class="identity-field-grid">
                    @foreach($witnessFields as $key => $label)
                        @php
                            $errorKey = 'witnesses.'.$index.'.'.$key;
                        @endphp
                        <div class="identity-field"><label for="witness-{{ $index }}-{{ $key }}">{{ $label }} @unless(in_array($key, ['employer','work_address','nearby_landmark'], true))<span aria-label="مطلوب">*</span>@endunless</label>
                        @if($key === 'document_type')
                            <select id="witness-{{ $index }}-{{ $key }}" name="witnesses[{{ $index }}][{{ $key }}]" required @error($errorKey) aria-invalid="true" aria-describedby="witness-error-{{ $index }}-{{ $key }}" @enderror>
                                <option value="">اختر نوع البطاقة</option>
                                @foreach(['بطاقة شخصية', 'بطاقة عائلية', 'جواز سفر'] as $documentType)
                                    <option value="{{ $documentType }}" @selected(old($errorKey, data_get($witness, $key)) === $documentType)>{{ $documentType }}</option>
                                @endforeach
                            </select>
                        @endif
                        @if($key === 'issued_at')
                            <div class="witness-date-selects" data-witness-issued-date>
                                <label for="witness-{{ $index }}-issued_at_day">اليوم</label>
                                <select id="witness-{{ $index }}-issued_at_day" name="witnesses[{{ $index }}][issued_at_day]" required @error($errorKey) aria-invalid="true" @enderror>
                                    <option value="">اليوم</option>
                                    @for($day = 1; $day <= 31; $day++)<option value="{{ $day }}" @selected(old('witnesses.'.$index.'.issued_at_day', $issuedAtParts['day'] ?? '') == $day)>{{ $day }}</option>@endfor
                                </select>
                                <label for="witness-{{ $index }}-issued_at_month">الشهر</label>
                                <select id="witness-{{ $index }}-issued_at_month" name="witnesses[{{ $index }}][issued_at_month]" required @error($errorKey) aria-invalid="true" @enderror>
                                    <option value="">الشهر</option>
                                    @foreach([1=>'يناير',2=>'فبراير',3=>'مارس',4=>'أبريل',5=>'مايو',6=>'يونيو',7=>'يوليو',8=>'أغسطس',9=>'سبتمبر',10=>'أكتوبر',11=>'نوفمبر',12=>'ديسمبر'] as $month => $monthName)<option value="{{ $month }}" @selected(old('witnesses.'.$index.'.issued_at_month', $issuedAtParts['month'] ?? '') == $month)>{{ $monthName }}</option>@endforeach
                                </select>
                                <label for="witness-{{ $index }}-issued_at_year">السنة</label>
                                <select id="witness-{{ $index }}-issued_at_year" name="witnesses[{{ $index }}][issued_at_year]" required @error($errorKey) aria-invalid="true" @enderror>
                                    <option value="">السنة</option>
                                    @for($year = (int) now()->format('Y'); $year >= 1900; $year--)<option value="{{ $year }}" @selected(old('witnesses.'.$index.'.issued_at_year', $issuedAtParts['year'] ?? '') == $year)>{{ $year }}</option>@endfor
                                </select>
                            </div>
                        @endif
                        @if(!in_array($key, ['document_type', 'issued_at'], true))
                            <input id="witness-{{ $index }}-{{ $key }}" name="witnesses[{{ $index }}][{{ $key }}]" @required(!in_array($key, ['employer','work_address','nearby_landmark'], true)) type="{{ $key === 'phone' ? 'tel' : 'text' }}" value="{{ old($errorKey, data_get($witness, $key)) }}" @if(in_array($key, ['name','phone','issued_place'], true)) data-witness-autofill="{{ $key }}" @endif @if($key === 'document_number') data-witness-document-number @endif @error($errorKey) aria-invalid="true" aria-describedby="witness-error-{{ $index }}-{{ $key }}" @enderror>
                        @endif
                        @if($key === 'document_number' && \App\Services\Registration\MockCivilSystems::enabled())
                            <button class="btn btn-outline" type="button" data-witness-lookup-button>بحث وتعبئة من سجلات المحاكاة</button>
                            <p class="identity-notice" data-witness-lookup-status role="status" aria-live="polite">هذه بيانات تجريبية وليست سجلًا حكوميًا.</p>
                        @endif
                        @error($errorKey)<p class="identity-field-error" id="witness-error-{{ $index }}-{{ $key }}">{{ $message }}</p>@enderror</div>
                    <?php endforeach; ?>
                    </div>
                    <div class="identity-field" data-witness-selfie>
                        <label for="witness-selfie-{{ $index }}">صورة سلفي للشاهد وهو يحمل بطاقته الشخصية *</label>
                        <p id="witness-selfie-help-{{ $index }}">JPG أو JPEG أو PNG، بحد أقصى 2 ميجابايت، وأبعاد لا تقل عن 600 × 600 بكسل. يمكنك حفظ مسودة دون الصورة، ويلزم إرفاقها للمتابعة. لا ترفع صورة البطاقة وحدها.</p>
                        <input class="witness-selfie-input" id="witness-selfie-{{ $index }}" type="file" name="witnesses[{{ $index }}][selfie]" accept="image/jpeg,image/png,.jpg,.jpeg,.png" aria-describedby="witness-selfie-help-{{ $index }} witness-selfie-error-{{ $index }}">
                        <button class="witness-upload-button" type="button" data-open-selfie-picker aria-controls="witness-selfie-{{ $index }}"><span aria-hidden="true">▧</span> اختيار صورة الشاهد</button>
                        <dialog class="witness-picker-dialog" aria-labelledby="witness-picker-title-{{ $index }}">
                            <h3 id="witness-picker-title-{{ $index }}">اختيار صورة الشاهد</h3>
                            <p>التقط صورة مباشرة أو اختر صورة محفوظة على جهازك.</p>
                            <div class="witness-picker-actions">
                                <button type="button" data-selfie-source="camera">فتح كاميرا الهاتف</button>
                                <button type="button" data-selfie-source="gallery">اختيار من معرض الصور</button>
                            </div>
                            <button type="button" class="witness-picker-cancel" data-close-selfie-picker>إلغاء</button>
                        </dialog>
                        <p id="witness-selfie-error-{{ $index }}" class="identity-field-error">@error('witnesses.'.$index.'.selfie'){{ $message }}@enderror</p>
                        <img class="identity-photo" data-selfie-preview @if($witness?->selfie_path) src="{{ route('citizen.identity-card.witness-selfie', ['form'=>$form,'position'=>$index+1]) }}" @else hidden @endif alt="صورة الشاهد {{ $index === 0 ? 'الأول' : 'الثاني' }} وهو يحمل بطاقته">
                        @if($witness?->selfie_path)<p>توجد صورة محفوظة. اختيار صورة جديدة يستبدلها بعد نجاح الحفظ.</p>@endif
                    </div><p>الحضور والمراجعة يسجلهما الموظف المختص.</p></fieldset>
                @endforeach
            @elseif($step === 7)
                <input type="hidden" name="review_token" value="{{ $reviewToken }}">
                <nav class="identity-actions" aria-label="تعديل بيانات الملخص">@foreach([1=>'البيانات الشخصية',2=>'الميلاد والجنسية',3=>'العنوان والحالة',4=>'الصورة',5=>'الشاهدان'] as $editStep=>$editLabel)<a href="{{ route('citizen.identity-card.show', ['form'=>$form,'step'=>$editStep]) }}">تعديل {{ $editLabel }}</a>@endforeach</nav>
                <p>راجع جميع البيانات والصورة وبيانات الشاهدين قبل تقديم الاستمارة. إذا ظهر خطأ، ستجد سببه هنا ويمكنك العودة إلى المرحلة ذات الصلة لتصحيحه.</p>
                <p class="identity-notice">ارفع المستندات التجريبية المطلوبة وتأكد من اعتماد مراجعتها قبل التقديم.</p>
                <p><a class="btn btn-outline" href="{{ route('citizen.services.attachments.index', $form->service) }}">رفع المستندات المطلوبة ومتابعة مراجعتها</a></p>
                @include('components.identity-card-summary', ['identityForm'=>$form])
                <div class="identity-panel"><label class="identity-declaration"><input type="checkbox" name="declaration" value="1" required @checked(old('declaration')) aria-describedby="declaration-error">أقر بصحة البيانات المدخلة وأتحمل مسؤوليتها، وأفهم أن تقديم الاستمارة لا يعني إصدار وثيقة رسمية.</label>@error('declaration')<p id="declaration-error" class="identity-field-error">{{ $message }}</p>@enderror</div>
            @endif
            <div class="identity-actions">
                @if($step > 1)<a class="btn btn-outline" href="{{ route('citizen.identity-card.show', ['form'=>$form,'step'=>$step - 1]) }}">السابق</a>@endif
                @if($step < 7)<button class="btn btn-outline" name="action" value="save" type="submit">حفظ كمسودة</button>@endif
                <button class="btn btn-primary" name="action" value="{{ $step === 7 ? 'submit' : 'next' }}" type="submit">{{ $step === 7 ? 'تقديم الاستمارة' : 'حفظ ومتابعة' }}</button>
            </div>
        </form>
    @endif
</div></section>
@endsection
