@php
 $birthLocations = \App\Models\ResidenceGovernorate::with('districts')->orderBy('name')->get()->map(fn($g)=>['name'=>$g->name,'districts'=>$g->districts->sortBy('name')->pluck('name')->values()]);
 $birthValues = fn($key)=>old('data.'.$key,data_get($form->data,$key));
 $hijriMonths = [1=>'محرم',2=>'صفر',3=>'ربيع الأول',4=>'ربيع الآخر',5=>'جمادى الأولى',6=>'جمادى الآخرة',7=>'رجب',8=>'شعبان',9=>'رمضان',10=>'شوال',11=>'ذو القعدة',12=>'ذو الحجة'];
@endphp
<div class="birth-details identity-field-grid" data-birth-details>
 <p class="birth-help">اختر الموقع من الاقتراحات، أو اكتب الاسم كما ورد في وثيقة الميلاد إذا لم تجده. تتغير اقتراحات المديريات حسب المحافظة.</p>
 @foreach(['birth_gregorian'=>'تاريخ الميلاد الميلادي','birth_hijri'=>'تاريخ الميلاد الهجري'] as $dateKey=>$dateLabel)
 @php
  $dateValue = $birthValues($dateKey);
  $parsedDate = preg_match('/^(\d{4})-(\d{2})-(\d{2})$/',(string)$dateValue,$dateParts);
  $dateYear = $parsedDate ? (int)$dateParts[1] : null;
  $yearEnd = $dateKey === 'birth_gregorian' ? (int)date('Y') : 1500;
  $yearStart = $dateKey === 'birth_gregorian' ? 1850 : 1250;
 @endphp
 <fieldset class="identity-field birth-date"><legend>{{ $dateLabel }} *</legend>
 <input type="hidden" name="data[{{ $dateKey }}]" value="{{ $dateValue }}">
 <div class="birth-date-parts">
 @foreach(['day'=>'اليوم','month'=>'الشهر','year'=>'السنة'] as $part=>$partLabel)
 <label>{{ $partLabel }}<select name="birth_dates[{{ $dateKey }}][{{ $part }}]" aria-label="{{ $partLabel }} — {{ $dateLabel }}">
 <option value="">{{ $partLabel }}</option>
 @php($selectedPart = old('birth_dates.'.$dateKey.'.'.$part,$parsedDate ? (int)$dateParts[['year'=>1,'month'=>2,'day'=>3][$part]] : null))
 @if($part === 'year' && $dateYear && ($dateYear < $yearStart || $dateYear > $yearEnd))<option selected value="{{ $dateYear }}">{{ $dateYear }}</option>@endif
 @foreach($part === 'year' ? range($yearEnd,$yearStart) : range(1,$part === 'month' ? 12 : 31) as $number)<option value="{{ $number }}" @selected((string)$selectedPart === (string)$number)>{{ $dateKey === 'birth_hijri' && $part === 'month' ? $hijriMonths[$number] : $number }}</option>@endforeach
 </select></label>
 @endforeach
 </div>
 @if($dateValue && !$parsedDate)<small>القيمة المحفوظة: {{ $dateValue }}. يمكنك استبدالها باختيار اليوم والشهر والسنة.</small>@endif
 @error('data.'.$dateKey)<p class="identity-field-error">{{ $message }}</p>@enderror
 </fieldset>
 @endforeach
 @foreach(['birth_place'=>'مكان الميلاد','birth_country'=>'دولة الميلاد','birth_governorate'=>'محافظة الميلاد','birth_district'=>'مديرية الميلاد','birth_village'=>'عزلة / قرية الميلاد'] as $key=>$label)
 <div class="identity-field"><label for="field-{{ $key }}">{{ $label }} {{ $key === 'birth_village' ? '(اختياري)' : '*' }}</label>
 <input id="field-{{ $key }}" name="data[{{ $key }}]" value="{{ $birthValues($key) ?: ($key === 'birth_country' ? 'اليمن' : '') }}" @if($key !== 'birth_village') list="options-{{ $key }}" required @endif autocomplete="off" maxlength="200" placeholder="{{ $key === 'birth_village' ? 'اكتب اسم العزلة أو القرية إن وجد' : 'اختر أو اكتب '.$label }}" @error('data.'.$key) aria-invalid="true" aria-describedby="error-{{ $key }}" @enderror>
 <datalist id="options-{{ $key }}">
 @if($key === 'birth_place')
 @foreach(['مستشفى','مركز صحي','منزل'] as $place)<option value="{{ $place }}"></option>@endforeach
 @endif
 @if($key === 'birth_country')
 @foreach(['اليمن','السعودية','عُمان','الإمارات العربية المتحدة','مصر','الأردن','الصومال','جيبوتي'] as $country)<option value="{{ $country }}"></option>@endforeach
 @endif
 @if($key === 'birth_governorate')
 @foreach($birthLocations as $g)<option value="{{ $g['name'] }}"></option>@endforeach
 @endif
 @if($key === 'birth_village')
 @foreach(config('identity_card.birth_villages',[]) as $village)<option value="{{ $village }}"></option>@endforeach
 @endif
 </datalist>
 @if($key === 'birth_place')<small>اختر نوع المكان أو اكتب اسم مكان الميلاد المثبت في الوثيقة.</small>@endif
 @if($key === 'birth_village')<small>يمكنك ترك هذا الحقل فارغًا.</small>@endif
 @error('data.'.$key)<p id="error-{{ $key }}" class="identity-field-error">{{ $message }}</p>@enderror
 </div>
 @endforeach
 <script type="application/json" data-birth-locations>@json($birthLocations)</script>
</div>
@push('scripts')<script src="{{ asset('js/birth-details.js') }}?v={{ filemtime(public_path('js/birth-details.js')) }}" defer></script>@endpush
