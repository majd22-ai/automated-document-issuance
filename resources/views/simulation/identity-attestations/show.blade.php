@extends('layouts.public')
@section('title', 'محاكاة المصادقة — ' . ($attestation->form?->application?->reference ?? $attestation->form->reference))
@push('styles')
<link rel="stylesheet" href="{{ asset('css/identity-attestation.css') }}">
<link rel="stylesheet" href="{{ asset('css/simulation-modals.css') }}?v={{ filemtime(public_path('css/simulation-modals.css')) }}">
@endpush
@push('scripts')
<script src="{{ asset('js/simulation-modals.js') }}?v={{ filemtime(public_path('js/simulation-modals.js')) }}" defer></script>
@endpush

@section('content')
<section class="section"><div class="container attestation-workspace">
  <h1>محاكاة المصادقة على طلب هوية</h1>

  <div class="sim-alert sim-alert-warning" role="note" style="margin-bottom:1.25rem">
    <span class="sim-alert-icon" aria-hidden="true">⚠</span>
    <div>
      <strong>تنبيه هام:</strong>
      هذه محاكاة تجريبية — لا تُمثّل إجراءً حكومياً حقيقياً.
      القرارات المُسجَّلة هنا تُغيّر حالة الطلب في قاعدة البيانات.
    </div>
  </div>

  @if(session('status'))
    <div class="sim-alert sim-alert-success" role="status">
      <span class="sim-alert-icon" aria-hidden="true">✓</span>
      <div>{{ session('status') }}</div>
    </div>
  @endif
  @if($errors->any())
    <div class="sim-alert sim-alert-error" role="alert">
      <span class="sim-alert-icon" aria-hidden="true">✗</span>
      <ul style="margin:0;padding-inline-start:1.2rem">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
      </ul>
    </div>
  @endif

  {{-- ─── تفاصيل المصادقة ─── --}}
  <div class="attestation-panel">
    <div class="sim-details">
      <div class="sim-detail-group">
        <dt>اسم مقدّم الطلب</dt>
        <dd>{{ $attestation->citizen_name ?? data_get($attestation->form?->data, 'applicant_name') ?: 'غير محدد' }}</dd>
      </div>
      <div class="sim-detail-group">
        <dt>رقم الطلب</dt>
        <dd dir="ltr">{{ $attestation->form?->application?->reference ?? $attestation->form?->reference }}</dd>
      </div>
      <div class="sim-detail-group">
        <dt>جهة المصادقة</dt>
        <dd>{{ $attestation->authorityLabel() }}</dd>
      </div>
      <div class="sim-detail-group">
        <dt>المستفيد</dt>
        <dd>{{ $attestation->recipient_name ?: 'غير محدد' }}</dd>
      </div>
      <div class="sim-detail-group" style="grid-column:1/-1">
        <dt>الحالة</dt>
        <dd>
          <span class="sim-status-badge {{ $attestation->status }}">
            {{ $attestation->statusLabel() }}
          </span>
        </dd>
      </div>
    </div>
  </div>

  {{-- ─── أزرار اتخاذ القرار (حالة pending فقط) ─── --}}
  @if($attestation->status === 'pending')
    @if($attestation->neighborhood_official_id)
      @include('components.neighborhood-phone', ['attestation'=>$attestation])
    @elseif($attestation->authority_type === 'witnesses')
      {{-- ─── مصادقة الشهود ─── --}}
      <div style="margin-block:1.25rem">
        <button class="sim-trigger-btn" type="button"
                data-attest-approve-open
                aria-haspopup="dialog" aria-controls="sim-attest-approve-modal">
          <span aria-hidden="true">✓</span>
          المصادقة والموافقة — تحقق الشهود
        </button>
        <button class="sim-trigger-btn" type="button"
                data-attest-reject-open
                aria-haspopup="dialog" aria-controls="sim-attest-reject-modal"
                style="border-color:#7a1c1c;color:#e06060;background:#1a0505;margin-inline-start:10px">
          <span aria-hidden="true">✗</span>
          رفض الطلب
        </button>
      </div>

      {{-- نافذة قبول الشهود --}}
      <dialog id="sim-attest-approve-modal" class="sim-modal-dialog" aria-labelledby="approve-modal-title">
        <div class="sim-modal-head">
          <h2 id="approve-modal-title" class="sim-modal-title">
            <span class="sim-icon" aria-hidden="true">✓</span>
            المصادقة بموافقة الشهود
          </h2>
          <button class="sim-modal-close" type="button" data-sim-close aria-label="إغلاق النافذة">✕</button>
        </div>
        <div class="sim-modal-body">
          <div class="sim-alert sim-alert-info" role="note">
            <span class="sim-alert-icon" aria-hidden="true">ℹ</span>
            <div>أدخل بيانات حضور الشهود وتحققهم. يُشترط حضور الشاهدين وتحقق هويتيهما.</div>
          </div>

          <div id="sim-attest-approve-state" aria-live="polite"></div>

          <form method="POST" action="{{ route('simulation.identity-attestations.decide', $attestation) }}"
                data-attest-approve-form novalidate>
            @csrf
            @foreach([0, 1] as $index)
              @php($witness = $attestation->form?->witnesses?->firstWhere('position', $index + 1))
              <fieldset class="sim-fieldset">
                <legend>الشاهد {{ $index === 0 ? 'الأول' : 'الثاني' }}</legend>
                <div class="sim-details">
                  @foreach(['name' => 'الاسم', 'document_type' => 'نوع الوثيقة', 'document_number' => 'رقم الوثيقة', 'issued_place' => 'مكان الإصدار', 'issued_at' => 'تاريخ الإصدار'] as $key => $label)
                    <div class="sim-detail-group">
                      <dt>{{ $label }}</dt>
                      <dd>
                        {{ data_get($witness, $key) instanceof \DateTimeInterface
                           ? data_get($witness, $key)->format('Y-m-d')
                           : (data_get($witness, $key) ?: '—') }}
                      </dd>
                    </div>
                  @endforeach
                </div>

                <div class="sim-field">
                  <label for="witness-attendance-{{ $index }}">الحضور</label>
                  <select id="witness-attendance-{{ $index }}" name="witnesses[{{ $index }}][attendance]" required>
                    <option value="">— اختر —</option>
                    <option value="present" @selected(old("witnesses.$index.attendance") === 'present')>حاضر</option>
                    <option value="absent"  @selected(old("witnesses.$index.attendance") === 'absent')>غائب</option>
                  </select>
                </div>
                <div class="sim-field">
                  <label for="witness-verification-{{ $index }}">التحقق من الهوية</label>
                  <select id="witness-verification-{{ $index }}" name="witnesses[{{ $index }}][verification]" required>
                    <option value="">— اختر —</option>
                    <option value="approved" @selected(old("witnesses.$index.verification") === 'approved')>تمّ التحقق</option>
                    <option value="rejected" @selected(old("witnesses.$index.verification") === 'rejected')>لم يتحقق</option>
                  </select>
                </div>
                <div class="sim-field">
                  <label for="witness-note-{{ $index }}">ملاحظات اختيارية</label>
                  <textarea id="witness-note-{{ $index }}" name="witnesses[{{ $index }}][note]"
                            maxlength="500">{{ old("witnesses.$index.note") }}</textarea>
                </div>
              </fieldset>
            @endforeach

            <p style="font-size:.85rem;color:var(--text-secondary);line-height:1.7">
              يُشترط حضور كلا الشاهدين وتحقق هويتيهما لإتمام المصادقة بنجاح.
            </p>

            <div class="sim-actions">
              <button class="sim-btn sim-btn-primary" type="submit" name="decision" value="approved">
                <span aria-hidden="true">✓</span> تأكيد المصادقة
              </button>
              <button type="button" class="sim-btn sim-btn-outline" data-sim-close>إلغاء</button>
            </div>
          </form>
        </div>
      </dialog>

      {{-- نافذة رفض الشهود --}}
      <dialog id="sim-attest-reject-modal" class="sim-modal-dialog" aria-labelledby="reject-modal-title">
        <div class="sim-modal-head">
          <h2 id="reject-modal-title" class="sim-modal-title" style="color:#e06060">
            <span class="sim-icon" aria-hidden="true">✗</span>
            رفض طلب المصادقة
          </h2>
          <button class="sim-modal-close" type="button" data-sim-close aria-label="إغلاق النافذة">✕</button>
        </div>
        <div class="sim-modal-body">
          <div class="sim-alert sim-alert-error" role="note">
            <span class="sim-alert-icon" aria-hidden="true">⚠</span>
            <div>سيُسجَّل قرار الرفض بشكل دائم. تأكد من توثيق سبب الرفض بدقة.</div>
          </div>

          <div id="sim-attest-reject-state" aria-live="polite"></div>

          <form method="POST" action="{{ route('simulation.identity-attestations.decide', $attestation) }}"
                data-attest-reject-form novalidate>
            @csrf
            <input type="hidden" name="decision" value="rejected">
            <div class="sim-field">
              <label for="reject-reason">سبب الرفض <span style="color:#e06060">*</span></label>
              <textarea id="reject-reason" name="reason" required maxlength="1500"
                        placeholder="وصف واضح وكافٍ لسبب الرفض...">{{ old('reason') }}</textarea>
            </div>
            <div class="sim-actions">
              <button class="sim-btn sim-btn-danger" type="submit">
                <span aria-hidden="true">✗</span> تأكيد الرفض
              </button>
              <button type="button" class="sim-btn sim-btn-outline" data-sim-close>إلغاء</button>
            </div>
          </form>
        </div>
      </dialog>

    @else
      {{-- ─── مصادقة عادية (غير شهود) ─── --}}
      <div style="margin-block:1.25rem;display:flex;gap:10px;flex-wrap:wrap">
        <button class="sim-trigger-btn" type="button"
                data-attest-approve-open
                aria-haspopup="dialog" aria-controls="sim-attest-approve-modal">
          <span aria-hidden="true">✓</span>
          تأكيد المصادقة وإتمامها
        </button>
        <button class="sim-trigger-btn" type="button"
                data-attest-reject-open
                aria-haspopup="dialog" aria-controls="sim-attest-reject-modal"
                style="border-color:#7a1c1c;color:#e06060;background:#1a0505">
          <span aria-hidden="true">✗</span>
          رفض المصادقة
        </button>
      </div>

      {{-- نافذة قبول عادية --}}
      <dialog id="sim-attest-approve-modal" class="sim-modal-dialog" aria-labelledby="approve-modal-title">
        <div class="sim-modal-head">
          <h2 id="approve-modal-title" class="sim-modal-title">
            <span class="sim-icon" aria-hidden="true">✓</span>
            تأكيد إتمام المصادقة
          </h2>
          <button class="sim-modal-close" type="button" data-sim-close aria-label="إغلاق النافذة">✕</button>
        </div>
        <div class="sim-modal-body">
          <div class="sim-details" style="margin-bottom:1rem">
            <div class="sim-detail-group">
              <dt>الطلب</dt>
              <dd dir="ltr">{{ $attestation->form?->application?->reference ?? $attestation->form?->reference }}</dd>
            </div>
            <div class="sim-detail-group">
              <dt>الجهة</dt>
              <dd>{{ $attestation->authorityLabel() }}</dd>
            </div>
          </div>

          <div class="sim-alert sim-alert-info" role="note">
            <span class="sim-alert-icon" aria-hidden="true">ℹ</span>
            <div>بالضغط على تأكيد ستُسجَّل الموافقة على هذه المصادقة في قاعدة البيانات.</div>
          </div>

          <div id="sim-attest-approve-state" aria-live="polite"></div>

          <form method="POST" action="{{ route('simulation.identity-attestations.decide', $attestation) }}"
                data-attest-approve-form novalidate>
            @csrf
            <input type="hidden" name="decision" value="approved">
            <div class="sim-actions">
              <button class="sim-btn sim-btn-primary" type="submit">
                <span aria-hidden="true">✓</span> تأكيد إتمام المصادقة
              </button>
              <button type="button" class="sim-btn sim-btn-outline" data-sim-close>إلغاء</button>
            </div>
          </form>
        </div>
      </dialog>

      {{-- نافذة رفض عادية --}}
      <dialog id="sim-attest-reject-modal" class="sim-modal-dialog" aria-labelledby="reject-modal-title">
        <div class="sim-modal-head">
          <h2 id="reject-modal-title" class="sim-modal-title" style="color:#e06060">
            <span class="sim-icon" aria-hidden="true">✗</span>
            رفض المصادقة
          </h2>
          <button class="sim-modal-close" type="button" data-sim-close aria-label="إغلاق النافذة">✕</button>
        </div>
        <div class="sim-modal-body">
          <div class="sim-alert sim-alert-error" role="note">
            <span class="sim-alert-icon" aria-hidden="true">⚠</span>
            <div>سيُسجَّل قرار الرفض بشكل نهائي. يرجى توثيق السبب بوضوح.</div>
          </div>

          <div id="sim-attest-reject-state" aria-live="polite"></div>

          <form method="POST" action="{{ route('simulation.identity-attestations.decide', $attestation) }}"
                data-attest-reject-form novalidate>
            @csrf
            <input type="hidden" name="decision" value="rejected">
            <div class="sim-field">
              <label for="rejection-reason">سبب الرفض <span style="color:#e06060">*</span></label>
              <textarea id="rejection-reason" name="reason" required maxlength="1500"
                        placeholder="وصف واضح وكافٍ لسبب الرفض...">{{ old('reason') }}</textarea>
            </div>
            <div class="sim-actions">
              <button class="sim-btn sim-btn-danger" type="submit">
                <span aria-hidden="true">✗</span> تأكيد الرفض
              </button>
              <button type="button" class="sim-btn sim-btn-outline" data-sim-close>إلغاء</button>
            </div>
          </form>
        </div>
      </dialog>
    @endif

  @else
    {{-- ─── حالة مكتملة / مرفوضة ─── --}}
    <div class="attestation-panel">
      الحالة النهائية للمصادقة:
      <strong>{{ $attestation->form?->statusLabel() }}</strong>
    </div>

    @if($attestation->status === 'approved' && $attestation->security_required)
      <div class="sim-alert sim-alert-warning" role="note">
        <span class="sim-alert-icon" aria-hidden="true">⚠</span>
        <div>
          تنبيه إضافي: هذه المصادقة تستوجب إجراءً أمنياً قبل اكتمال إصدار الهوية.
          يرجى إكمال الإجراء عبر نافذة الاستقبال.
        </div>
      </div>
    @endif

    @if($attestation->rejection_reason)
      <div class="attestation-panel">
        <strong>سبب الرفض:</strong> {{ $attestation->rejection_reason }}
      </div>
      @can('administer', $attestation->form)<p><a class="btn btn-outline" href="{{ route('identity-workflow.administration.show',$attestation->form) }}">إعادة البيانات للمواطن للتصحيح واختيار العاقل الصحيح</a></p>@endcan
    @endif

    @include('components.identity-attestation-stamp', ['attestation' => $attestation])
  @endif

</div></section>
@endsection
