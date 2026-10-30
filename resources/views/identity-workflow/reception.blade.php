@extends('layouts.public')
@section('title', 'استقبال طلبات بصمة الأصابع')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/identity-workflow.css') }}">
<link rel="stylesheet" href="{{ asset('css/simulation-modals.css') }}?v={{ filemtime(public_path('css/simulation-modals.css')) }}">
@endpush
@push('scripts')
<script src="{{ asset('js/simulation-modals.js') }}?v={{ filemtime(public_path('js/simulation-modals.js')) }}" defer></script>
@endpush

@section('content')
<section class="section"><div class="container identity-workflow">
  <h1>استقبال طلبات بصمة الأصابع</h1>
  <p class="workflow-warning">
    نافذة استقبال المواطنين لإجراءات بصمة الأصابع.
    ابحث عن الطلب بالمرجع لعرض التفاصيل وتسجيل الحضور وتوجيه المواطن لجهاز القياس.
  </p>

  @if(session('status'))
    <div class="sim-alert sim-alert-success" role="status" style="margin-bottom:1rem">
      <span class="sim-alert-icon" aria-hidden="true">✓</span>
      <div>{{ session('status') }}</div>
    </div>
  @endif
  @if($errors->any())
    <div class="sim-alert sim-alert-error" role="alert" style="margin-bottom:1rem">
      <span class="sim-alert-icon" aria-hidden="true">✗</span>
      <ul style="margin:0;padding-inline-start:1.2rem">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
      </ul>
    </div>
  @endif

  {{-- ─── نموذج البحث ─── --}}
  <form method="GET" action="{{ route('identity-workflow.reception') }}" class="workflow-panel">
    <label for="reference" style="font-weight:700;display:block;margin-bottom:.5rem">
      ابحث عن طلب بمرجعه
    </label>
    <div class="workflow-actions">
      <input id="reference" name="reference" value="{{ request('reference') }}"
             placeholder="مثال: APP-2026-XXXXX" style="flex:1">
      <button class="btn btn-primary">بحث</button>
    </div>
  </form>

  {{-- ─── تفاصيل الطلب المُختار ─── --}}
  @if($form)
    <section class="workflow-panel">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap;margin-bottom:1rem">
        <div>
          <h2 style="margin:0 0 4px">{{ data_get($form->data, 'applicant_name') }}</h2>
          <p dir="ltr" style="margin:0;font-size:.88rem;color:var(--text-secondary)">{{ $form->application?->reference ?? $form->reference }}</p>
          <p style="margin:4px 0 0">
            <span class="sim-status-badge pending">{{ $form->statusLabel() }}</span>
          </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          @if($canRecord)
            <a class="sim-btn sim-btn-secondary" href="{{ route('identity-workflow.device', $form) }}">
              <span aria-hidden="true">👆</span> الانتقال لجهاز البصمة
            </a>
          @endif
          @can('administer', $form)
            <a class="sim-btn sim-btn-outline" href="{{ route('identity-workflow.administration.show', $form) }}">
              إدارة الطلب
            </a>
          @endcan
        </div>
      </div>

      @if(count($reasons))
        <div class="sim-alert sim-alert-warning" role="alert">
          <span class="sim-alert-icon" aria-hidden="true">⚠</span>
          <ul style="margin:0;padding-inline-start:1.2rem">
            @foreach($reasons as $reason)<li>{{ $reason }}</li>@endforeach
          </ul>
        </div>
      @endif

      @if($form->arrived_at)
        <div class="sim-alert sim-alert-success" role="status">
          <span class="sim-alert-icon" aria-hidden="true">✓</span>
          <div>
            تمّ تسجيل الحضور:
            <strong dir="ltr">{{ $form->arrived_at->format('Y-m-d H:i') }}</strong>
            · مرجع: <strong>{{ $form->arrival_reference }}</strong>
          </div>
        </div>
      @endif

      {{-- ─── زر تسجيل الحضور ─── --}}
      @if(!$form->arrived_at && in_array($form->status, ['pending_fingerprint_attendance','fingerprint_failed'], true))
        <div style="margin-top:1rem">
          <button class="sim-trigger-btn" type="button"
                  data-arrival-open
                  @disabled(count($reasons))
                  aria-haspopup="dialog" aria-controls="sim-arrival-modal">
            <span aria-hidden="true">📋</span>
            تسجيل حضور المواطن
          </button>
        </div>
      @endif

      {{-- ─── إجراء أمني ─── --}}
      @if($form->attestation?->status === 'approved' && $form->attestation?->authority_type === 'neighborhood' && $form->attestation?->security_required && $form->attestation?->security_status !== 'recorded_manually')
        @if(count($fingerprintReasons))
          <div class="sim-alert sim-alert-warning" role="alert" style="margin-top:1rem">
            <span class="sim-alert-icon" aria-hidden="true">⚠</span>
            <ul style="margin:0;padding-inline-start:1.2rem">
              @foreach($fingerprintReasons as $reason)<li>{{ $reason }}</li>@endforeach
            </ul>
          </div>
        @endif
        <div style="margin-top:1rem">
          <button class="sim-trigger-btn" type="button"
                  data-security-open
                  aria-haspopup="dialog" aria-controls="sim-security-modal">
            <span aria-hidden="true">🔒</span>
            تسجيل الإجراء الأمني
          </button>
        </div>
      @endif

      {{-- ─── إعادة الإدراج في القائمة (returned_to_staff) ─── --}}
      @if($form->status === 'returned_to_staff')
        <div style="margin-top:1rem">
          <button class="sim-trigger-btn" type="button"
                  data-requeue-open
                  aria-haspopup="dialog" aria-controls="sim-requeue-modal">
            <span aria-hidden="true">🔄</span>
            إعادة الطلب إلى قائمة الانتظار
          </button>
        </div>
      @endif
    </section>

    @if(request('reference') && !$form)
      <p role="status">لم يُعثر على طلب بهذا المرجع.</p>
    @endif
  @endif

  {{-- ─── قائمة الطلبات الحالية ─── --}}
  <section class="workflow-panel">
    <h2>الطلبات الحالية</h2>
    <div class="sim-table-wrap">
      <table class="sim-table">
        <thead>
          <tr>
            <th>المرجع</th>
            <th>اسم مقدّم الطلب</th>
            <th>الحالة</th>
            <th>إجراء</th>
          </tr>
        </thead>
        <tbody>
          @forelse($forms as $item)
            <tr>
              <td dir="ltr">{{ $item->application?->reference ?? $item->reference }}</td>
              <td>{{ data_get($item->data, 'applicant_name') }}</td>
              <td>
                <span class="sim-status-badge pending">{{ $item->statusLabel() }}</span>
              </td>
              <td>
                <a class="sim-btn sim-btn-outline" style="font-size:.8rem;padding:6px 12px"
                   href="{{ route('identity-workflow.reception', ['reference' => $item->reference]) }}">
                  عرض
                </a>
              </td>
            </tr>
          @empty
            <tr><td colspan="4" style="text-align:center;color:var(--text-secondary);padding:2rem">لا توجد طلبات حالية.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    {{ $forms->links() }}
  </section>
</div></section>

{{-- ═══════════════════════════════════════════
     نافذة تسجيل الحضور
══════════════════════════════════════════ --}}
@if($form && !$form->arrived_at && in_array($form->status, ['pending_fingerprint_attendance','fingerprint_failed'], true))
  <dialog id="sim-arrival-modal" class="sim-modal-dialog" aria-labelledby="arrival-modal-title">
    <div class="sim-modal-head">
      <h2 id="arrival-modal-title" class="sim-modal-title">
        <span class="sim-icon" aria-hidden="true">📋</span>
        تسجيل حضور المواطن
      </h2>
      <button class="sim-modal-close" type="button" data-sim-close aria-label="إغلاق النافذة">✕</button>
    </div>
    <div class="sim-modal-body">
      <div class="sim-details">
        <div class="sim-detail-group">
          <dt>رقم الطلب</dt>
          <dd dir="ltr">{{ $form->application?->reference ?? $form->reference }}</dd>
        </div>
        <div class="sim-detail-group">
          <dt>مقدّم الطلب</dt>
          <dd>{{ data_get($form->data, 'applicant_name') ?? 'غير محدد' }}</dd>
        </div>
      </div>

      <div id="sim-arrival-state" aria-live="polite"></div>

      <form method="POST" action="{{ route('identity-workflow.arrival', $form) }}" data-arrival-form novalidate>
        @csrf
        <input type="hidden" name="version" value="{{ $form->version }}">

        <label class="sim-check">
          <input type="checkbox" name="identity_checked" value="1" required @checked(old('identity_checked'))>
          <span>تحققت من هوية المواطن وتطابق بياناته مع الطلب</span>
        </label>

        <label class="sim-check">
          <input type="checkbox" name="documents_checked" value="1" required @checked(old('documents_checked'))>
          <span>تحققت من اكتمال المستندات المطلوبة وصلاحيتها</span>
        </label>

        <div class="sim-field">
          <label for="arrival-evidence">رقم مرجع وثيقة الإثبات / وصل الحضور</label>
          <input type="text" id="arrival-evidence" name="evidence_reference"
                 required value="{{ old('evidence_reference') }}"
                 placeholder="رقم وصل الحضور أو المرجع">
        </div>

        <div class="sim-actions">
          <button class="sim-btn sim-btn-primary" type="submit" @disabled(count($reasons))>
            <span aria-hidden="true">✓</span> تأكيد تسجيل الحضور
          </button>
          <button type="button" class="sim-btn sim-btn-outline" data-sim-close>إلغاء</button>
        </div>
      </form>
    </div>
  </dialog>
@endif

{{-- ═══════════════════════════════════════════
     نافذة الإجراء الأمني
══════════════════════════════════════════ --}}
@if($form && $form->attestation?->status === 'approved' && $form->attestation?->security_required && $form->attestation?->security_status !== 'recorded_manually')
  <dialog id="sim-security-modal" class="sim-modal-dialog" aria-labelledby="security-modal-title">
    <div class="sim-modal-head">
      <h2 id="security-modal-title" class="sim-modal-title">
        <span class="sim-icon" aria-hidden="true">🔒</span>
        تسجيل الإجراء الأمني
      </h2>
      <button class="sim-modal-close" type="button" data-sim-close aria-label="إغلاق النافذة">✕</button>
    </div>
    <div class="sim-modal-body">
      <div class="sim-alert sim-alert-info" role="note">
        <span class="sim-alert-icon" aria-hidden="true">ℹ</span>
        <div>هذا الطلب يستوجب إجراء أمنياً إضافياً قبل أخذ البصمة. تحقق من الورقة الأمنية ثم سجّل المرجع.</div>
      </div>

      <div id="sim-security-state" aria-live="polite"></div>

      <form method="POST" action="{{ route('identity-workflow.security', $form) }}" data-security-form novalidate>
        @csrf
        <input type="hidden" name="version" value="{{ $form->version }}">

        <label class="sim-check">
          <input type="checkbox" name="paper_checked" value="1" required>
          <span>تحققت من الوثيقة الأمنية وصلاحيتها وتوقيعاتها</span>
        </label>

        <div class="sim-field">
          <label for="security-evidence">رقم مرجع الوثيقة الأمنية</label>
          <input type="text" id="security-evidence" name="evidence_reference"
                 required value="{{ old('evidence_reference') }}"
                 placeholder="رقم الوثيقة الأمنية">
        </div>

        <div class="sim-actions">
          <button class="sim-btn sim-btn-primary" type="submit">
            <span aria-hidden="true">✓</span> تسجيل الإجراء الأمني
          </button>
          <button type="button" class="sim-btn sim-btn-outline" data-sim-close>إلغاء</button>
        </div>
      </form>
    </div>
  </dialog>
@endif

{{-- ═══════════════════════════════════════════
     نافذة إعادة الإدراج في القائمة
══════════════════════════════════════════ --}}
@if($form && $form->status === 'returned_to_staff')
  <dialog id="sim-requeue-modal" class="sim-modal-dialog" aria-labelledby="requeue-modal-title">
    <div class="sim-modal-head">
      <h2 id="requeue-modal-title" class="sim-modal-title">
        <span class="sim-icon" aria-hidden="true">🔄</span>
        إعادة الطلب إلى قائمة الانتظار
      </h2>
      <button class="sim-modal-close" type="button" data-sim-close aria-label="إغلاق النافذة">✕</button>
    </div>
    <div class="sim-modal-body">
      <div id="sim-requeue-state" aria-live="polite"></div>

      <form method="POST" action="{{ route('identity-workflow.administration.requeue', $form) }}" data-requeue-form novalidate>
        @csrf
        <input type="hidden" name="version" value="{{ $form->version }}">

        <div class="sim-field">
          <label for="requeue-note">ملاحظات إعادة الإدراج (موجبات الإعادة)</label>
          <textarea id="requeue-note" name="note" required></textarea>
        </div>

        <div class="sim-actions">
          <button class="sim-btn sim-btn-primary" type="submit">
            <span aria-hidden="true">↩</span> إعادة الطلب للانتظار
          </button>
          <button type="button" class="sim-btn sim-btn-outline" data-sim-close>إلغاء</button>
        </div>
      </form>
    </div>
  </dialog>
@endif
@endsection
