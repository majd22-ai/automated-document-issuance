<details class="setting-subsection" @if($hasError(['minimum_age','renewal_window_days','renewal_statuses','terminal_request_statuses','requires_driving_clearance','require_separate_approver'])) open @endif>
 <summary>شروط الأهلية والمراجعة</summary><div class="setting-grid">
 @foreach(['minimum_age'=>'الحد الأدنى للعمر','renewal_window_days'=>'عدد أيام السماح بالتجديد قبل الانتهاء'] as $field=>$label)
 <div class="setting-field"><label for="service-{{ $field }}">{{ $label }}</label><input class="form-control" id="service-{{ $field }}" name="{{ $field }}" type="number" min="0" step="1" value="{{ old($field,$service->{$field}) }}"><small>اتركه فارغًا إذا لم توجد قاعدة معتمدة.</small>@error($field)<p class="setting-error">{{ $message }}</p>@enderror</div>@endforeach
 </div>
 @foreach(['renewal_statuses'=>'حالات الوثيقة السابقة المقبولة للتجديد','terminal_request_statuses'=>'حالات الطلب المنتهية التي تسمح بطلب جديد'] as $field=>$label)
 <div class="setting-field"><label for="service-{{ $field }}">{{ $label }}</label><textarea class="form-control" id="service-{{ $field }}" name="{{ $field }}" rows="3">{{ $listText(old($field,$service->{$field} ?? [])) }}</textarea><small>حالة معتمدة في كل سطر.</small>@error($field)<p class="setting-error">{{ $message }}</p>@enderror</div>@endforeach
 @foreach(['require_separate_approver'=>'يشترط أن يختلف المعتمد النهائي عن موظف قبول الطلب','requires_driving_clearance'=>'يشترط اعتماد متطلبات القيادة لهذه الخدمة'] as $field=>$label)<div class="setting-field"><input type="hidden" name="{{ $field }}" value="0"><label class="setting-check"><input name="{{ $field }}" type="checkbox" value="1" @checked(old($field,$service->{$field}))> {{ $label }}</label>@error($field)<p class="setting-error">{{ $message }}</p>@enderror</div>@endforeach
</details>
@foreach([
 'application_fields'=>['حقول إضافية في نموذج المواطن','يستخدمها المختص لتعريف الحقول الخاصة بالخدمة وأنواعها وإلزاميتها.'],
 'verification_rules'=>['قواعد مراجعة المستندات والفحوص','تحدد نوع كل مرفق وصيغته ومراجعته، والفحص الطبي وقواعد وثيقة التجديد. يجب أن تطابق أسماء المستندات القائمة المسجلة في القسم الثاني.'],
 'workflow_rules'=>['توقيت المواعيد والفحص والسداد','تحدد في أي مرحلة يلزم الموعد أو الفحص أو السداد، وفق سياسة الخدمة المعتمدة.'],
 'issuance_rules'=>['إصدار الوثيقة وطرق استلامها','تحدد مرجع الإصدار والصلاحية ومراكز الاستلام والنسخة الداخلية. تركها فارغة يبقي الإصدار غير متاح.'],
 'integration_rules'=>['التحقق عبر الأنظمة الخارجية','تحدد مزود التحقق ونوعه وحداثة النتيجة. لا تضف ربطًا إلزاميًا دون مزود موصول بالفعل.']
] as $field=>$info)
 @php($value = old($field,$service->exists ? $service->{$field} : ($field === 'issuance_rules' ? null : [])))
 <details class="setting-subsection" @if($hasError($field)) open @endif><summary>{{ $info[0] }}</summary><p class="ops-help">{{ $info[1] }}</p><div class="setting-field"><label for="service-{{ $field }}">الإعداد المنظم للمختص (JSON)</label><textarea class="form-control setting-code" id="service-{{ $field }}" name="{{ $field }}" dir="ltr" rows="7" spellcheck="false">{{ is_array($value) ? json_encode($value,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) : $value }}</textarea>
 @foreach($errors->get($field.'.*') as $messages)@foreach($messages as $message)<p class="setting-error">{{ $message }}</p>@endforeach @endforeach
 @error($field)<p class="setting-error">{{ $message }}</p>@enderror</div></details>
@endforeach
