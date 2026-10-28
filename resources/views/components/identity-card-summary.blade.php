@php
    $summaryGroups = config('identity_card.fields', []);
    $groupTitles = [1 => 'البيانات الشخصية', 2 => 'بيانات الميلاد', 3 => 'العنوان والحالة الاجتماعية'];
    $witnessLabels = ['name'=>'الاسم الكامل','document_type'=>'نوع الوثيقة','document_number'=>'رقم الوثيقة','issued_place'=>'مكان الإصدار','issued_at'=>'تاريخ الإصدار','employer'=>'جهة العمل','work_address'=>'عنوان العمل','phone'=>'رقم الهاتف','address'=>'عنوان السكن'];
@endphp
<div class="identity-summary">
    @include('components.identity-attestation-stamp', ['attestation' => $identityForm->attestation])
    <section class="identity-panel"><h3>بيانات الطلب</h3><dl class="identity-details">
        <div><dt>نوع الطلب</dt><dd>إصدار البطاقة الشخصية</dd></div>
        <div><dt>المرجع الداخلي</dt><dd dir="ltr">{{ $identityForm->application?->reference ?? $identityForm->reference }}</dd></div>
        <div><dt>تاريخ التقديم</dt><dd>{{ $identityForm->submitted_at?->format('Y-m-d H:i') ?? 'مسودة — لم تُقدّم بعد' }}</dd></div>
        <div><dt>الحالة</dt><dd>{{ $identityForm->statusLabel() }}</dd></div>
    </dl></section>
    @foreach($summaryGroups as $number => $group)
        <section class="identity-panel" aria-labelledby="summary-group-{{ $number }}">
            <h3 id="summary-group-{{ $number }}">{{ $groupTitles[$number] ?? 'البيانات' }}</h3>
            <dl class="identity-details">
                @foreach($group as $key => $field)
                    @php($value = data_get($identityForm->data, $key))
                    <div><dt>{{ $field['label'] }}</dt><dd>{{ ($field['options'][$value ?? ''] ?? $value) ?: 'غير مسجل' }}@error('data.'.$key)<p class="identity-field-error">{{ $message }}</p>@enderror</dd></div>
                @endforeach
            </dl>
        </section>
    @endforeach
    <section class="identity-panel"><h3>الصورة الشخصية</h3>
        @error('photo')<p class="identity-field-error">{{ $message }}</p>@enderror
        @if($identityForm->photo)<a href="{{ route('citizen.identity-card.photo', $identityForm) }}" target="_blank" rel="noopener"><img class="identity-photo" src="{{ route('citizen.identity-card.photo', $identityForm) }}" alt="الصورة الشخصية المرفقة"></a>@else<p>غير مسجلة</p>@endif
    </section>
    @foreach([1, 2] as $position)
        @php($witness = $identityForm->witnesses->firstWhere('position', $position))
        <section class="identity-panel"><h3>الشاهد {{ $position === 1 ? 'الأول' : 'الثاني' }}</h3>
            <dl class="identity-details">@foreach($witnessLabels as $key => $label)<div><dt>{{ $label }}</dt><dd>{{ data_get($witness, $key) ?: 'غير مسجل' }}</dd></div>@endforeach</dl>
            <p>الحضور: {{ ['present'=>'حاضر','absent'=>'غائب','not_recorded'=>'غير مسجل'][$witness?->attendance_status ?? 'not_recorded'] ?? 'غير مسجل' }} · التحقق: {{ ['approved'=>'تم التحقق في المراجعة التجريبية','rejected'=>'لم يُقبل التحقق','not_verified'=>'لم يتم التحقق'][$witness?->verification_status ?? 'not_verified'] ?? 'لم يتم التحقق' }}</p>
            @if($witness?->reviewed_at)<p>وقت تسجيل المراجعة: {{ $witness->reviewed_at->format('Y-m-d H:i') }}</p>@endif
        </section>
    @endforeach
</div>
