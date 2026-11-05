      <form method="POST" action="{{ route('admin.services.assessments.store', $service) }}">
        @csrf
        <input type="hidden" name="expected_rules_version" value="{{ $service->rules_version }}">
        <div class="form-group"><label class="form-label" for="assessment-username">الرقم الوطني للمواطن</label><input class="form-control" id="assessment-username" name="username" value="{{ old('username') }}" maxlength="255"></div>
        @if($service->code === 'identity-first-issue')
        <div class="form-group"><label class="form-label" for="assessment-account-id">أو معرّف الحساب الأولي الداخلي دون رقم هوية</label><input class="form-control" id="assessment-account-id" type="number" name="citizen_account_id" min="1" value="{{ old('citizen_account_id') }}"><small>أدخل أحد الحقلين فقط. المعرّف الداخلي ظاهر في بيانات المواطن؛ لا يُعد رقمًا وطنيًا ولا يعفي من التحقق الرسمي.</small></div>
        @endif
        <div class="form-group"><label class="form-label" for="assessment-reference">مرجع التحقق من الجهة المختصة</label><input class="form-control" id="assessment-reference" name="verification_reference" value="{{ old('verification_reference') }}" maxlength="255" required></div>
        @foreach(['identity_verified' => 'الهوية وارتباط الحساب موثّقان', 'conditions_met' => 'تم التحقق من استيفاء الشروط المسجلة', 'age_requirement_met' => 'تم التحقق من شرط العمر المحدد للخدمة، إن وجد', 'driving_requirements_met' => 'تم اعتماد متطلبات القيادة، إن كانت مطلوبة', 'is_blocked' => 'يوجد مانع رسمي معتمد'] as $field => $label)
          <div class="form-group"><input type="hidden" name="{{ $field }}" value="0"><label for="assessment-{{ $field }}"><input id="assessment-{{ $field }}" type="checkbox" name="{{ $field }}" value="1" @checked(old($field, false))> {{ $label }}</label></div>
        @endforeach
        <div class="form-group"><label class="form-label" for="assessment-reason">سبب المانع الداخلي (مطلوب عند وجوده، لا يعرض للمواطن)</label><input class="form-control" id="assessment-reason" name="block_reason" value="{{ old('block_reason') }}" maxlength="255"></div>
        <div class="form-group"><label class="form-label" for="assessment-public-reason">رسالة المانع المسموح بعرضها للمواطن (اختياري)</label><input class="form-control" id="assessment-public-reason" name="public_block_reason" value="{{ old('public_block_reason') }}" maxlength="255"></div>
        <button class="btn btn-outline" type="submit">حفظ التقييم الموثّق</button>
      </form>