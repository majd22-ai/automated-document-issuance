@extends('layouts.public')
@section('title', 'جهاز بصمة الأصابع — ' . $form->application?->reference ?? $form->reference)
@push('styles')
<link rel="stylesheet" href="{{ asset('css/identity-workflow.css') }}">
<link rel="stylesheet" href="{{ asset('css/simulation-modals.css') }}?v={{ filemtime(public_path('css/simulation-modals.css')) }}">
@endpush
@push('scripts')
<script src="{{ asset('js/simulation-modals.js') }}?v={{ filemtime(public_path('js/simulation-modals.js')) }}" defer></script>
@endpush

@section('content')
<section class="section"><div class="container identity-workflow">
  <h1>جهاز بصمة الأصابع</h1>
  <p class="workflow-warning">
    محاكاة جهاز القياس الحيومتري داخل بيئة الاختبار.
    لا يُمثّل عملية بيومترية حقيقية ولا يتصل بأجهزة فعلية.
    هذه المحاكاة لأغراض التطوير والاختبار فحسب.
  </p>

  <a href="{{ route('identity-workflow.reception', ['reference' => $form->application?->reference]) }}"
     class="sim-btn sim-btn-outline" style="display:inline-flex;margin-bottom:1.25rem">
    ← العودة إلى الاستقبال
  </a>

  {{-- معلومات الطلب --}}
  <div class="workflow-panel">
    <div class="sim-details">
      <div class="sim-detail-group">
        <dt>رقم الطلب</dt>
        <dd dir="ltr">{{ $form->application?->reference ?? $form->reference }}</dd>
      </div>
      <div class="sim-detail-group">
        <dt>اسم مقدّم الطلب</dt>
        <dd>{{ $form->data['applicant_name'] ?? 'غير محدد' }}</dd>
      </div>
      <div class="sim-detail-group">
        <dt>رمز العملية</dt>
        <dd dir="ltr" style="font-family:monospace;font-size:.85rem">{{ $operationToken }}</dd>
      </div>
      <div class="sim-detail-group">
        <dt>حالة البصمة</dt>
        <dd>{{ $form->fingerprintStatusLabel() }}</dd>
      </div>
    </div>
  </div>

  @if($errors->any())
    <div class="sim-alert sim-alert-error" role="alert" style="margin-bottom:1rem">
      <span class="sim-alert-icon" aria-hidden="true">✗</span>
      <div>{{ $errors->first() }}</div>
    </div>
  @endif

  @if(count($reasons))
    <div class="sim-alert sim-alert-warning" role="alert" style="margin-bottom:1rem">
      <span class="sim-alert-icon" aria-hidden="true">⚠</span>
      <ul style="margin:0;padding-inline-start:1.2rem">
        @foreach($reasons as $reason)<li>{{ $reason }}</li>@endforeach
      </ul>
    </div>
  @endif

  @if($latestSuccess)
    <div class="sim-alert sim-alert-success" role="status" style="margin-bottom:1rem">
      <span class="sim-alert-icon" aria-hidden="true">✓</span>
      <div>{{ $latestSuccess->resultLabel() }} — تمّ تسجيل بصمة الأصابع بنجاح.</div>
    </div>
  @endif

  {{-- زر فتح نافذة البصمة --}}
  <div style="margin-bottom:1.5rem">
    <button class="sim-trigger-btn" type="button"
            data-fp-open
            @disabled(count($reasons) || $latestSuccess)
            aria-haspopup="dialog" aria-controls="sim-fp-modal">
      <span aria-hidden="true">👆</span>
      {{ $latestSuccess ? 'تمّ التسجيل' : (count($reasons) ? 'لا يمكن البدء (توجد موانع)' : 'بدء تسجيل بصمة الأصابع') }}
    </button>
  </div>

  {{-- نافذة بصمة الأصابع --}}
  <dialog id="sim-fp-modal" class="sim-modal-dialog" aria-labelledby="fp-modal-title">
    <div class="sim-modal-head">
      <h2 id="fp-modal-title" class="sim-modal-title">
        <span class="sim-icon" aria-hidden="true">👆</span>
        تسجيل بصمة الأصابع
      </h2>
      <button class="sim-modal-close" type="button" data-sim-close aria-label="إغلاق النافذة">✕</button>
    </div>
    <div class="sim-modal-body">
      <div class="sim-alert sim-alert-warning" role="note">
        <span class="sim-alert-icon" aria-hidden="true">⚠</span>
        <div>محاكاة بيئة اختبار — لا تتصل بجهاز بصمة حقيقي.</div>
      </div>

      {{-- أيقونة البصمة والحالة --}}
      <div class="sim-fingerprint-wrap">
        <svg class="sim-fingerprint-svg" viewBox="0 0 160 180" role="img" aria-label="مؤشر جهاز بصمة الأصابع">
          <g fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round">
            <path d="M25 82C25 10 135 10 135 82M36 92C26 30 125 20 124 83M48 110C29 49 112 30 113 83L110 111M60 128C35 64 99 45 101 83L98 130M73 145C55 113 50 64 81 63C107 65 80 116 90 149M20 106L29 132M135 98L127 139M42 142L53 158M75 81C68 97 80 126 77 159"/>
          </g>
        </svg>

        <p id="sim-fp-state" role="status">
          {{ $latestSuccess ? $latestSuccess->resultLabel() : (count($reasons) ? 'لا يمكن البدء: توجد موانع' : 'جاهز — اضغط بدء التسجيل') }}
        </p>

        <progress id="sim-fp-progress" class="sim-progress" max="100"
                  value="{{ $latestSuccess ? 100 : 0 }}"
                  aria-label="تقدم عملية البصمة" style="width:100%">
        </progress>
      </div>

      <div id="sim-fp-result" aria-live="polite"></div>

      <form method="POST" action="{{ route('identity-workflow.capture', $form) }}" data-fp-form novalidate>
        @csrf
        <input type="hidden" name="version" value="{{ $form->version }}">
        <input type="hidden" name="operation_token" value="{{ $operationToken }}">

        <label class="sim-check">
          <input type="checkbox" name="simulate_failure" value="1">
          <span>محاكاة فشل العملية (للاختبار)</span>
        </label>

        <div class="sim-actions">
          <button class="sim-btn sim-btn-primary" type="submit"
                  @disabled(count($reasons) || $latestSuccess)
                  data-fp-start>
            <span aria-hidden="true">▶</span> بدء تسجيل البصمة
          </button>
          <a class="sim-btn sim-btn-outline"
             href="{{ route('identity-workflow.device', $form) }}">
            تحديث / إعادة التهيئة
          </a>
        </div>
      </form>
    </div>
  </dialog>

  {{-- ─── التسجيل اليدوي (إن كان مُفعَّلاً) ─── --}}
  @if(config('identity_card.fingerprint.manual_result_enabled', false))
    <div class="workflow-panel">
      <h2>إدخال نتيجة يدوية</h2>
      <p class="workflow-warning">
        هذا الخيار للحالات الطارئة فقط. يُسجَّل الإدخال اليدوي بشكل مستقل.
      </p>
      <form method="POST" action="{{ route('identity-workflow.manual', $form) }}">
        @csrf
        <input type="hidden" name="version" value="{{ $form->version }}">
        <input type="hidden" name="operation_token" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
        <div class="workflow-field">
          <label for="manual-result">النتيجة</label>
          <select name="result" id="manual-result" required>
            <option value="success">نجاح</option>
            <option value="failure">فشل</option>
          </select>
          @error('result')<p class="workflow-error">{{ $message }}</p>@enderror
        </div>
        <div class="workflow-field">
          <label for="manual-evidence">رقم مرجع الإثبات</label>
          <input name="evidence_reference" id="manual-evidence" required value="{{ old('evidence_reference') }}">
          @error('evidence_reference')<p class="workflow-error">{{ $message }}</p>@enderror
        </div>
        <div class="workflow-field">
          <label for="manual-executed">تاريخ ووقت التنفيذ الفعلي للعملية</label>
          <input type="datetime-local" name="executed_at" id="manual-executed" required value="{{ old('executed_at') }}">
          @error('executed_at')<p class="workflow-error">{{ $message }}</p>@enderror
        </div>
        <label class="sim-check">
          <input type="checkbox" name="manual_confirmed" value="1" required>
          <span>أؤكد أن هذه النتيجة صحيحة وتستوفي متطلبات السياسة المعتمدة</span>
        </label>
        @error('manual_confirmed')<p class="workflow-error">{{ $message }}</p>@enderror
        <div class="workflow-actions">
          <button class="btn btn-outline">حفظ النتيجة اليدوية</button>
        </div>
      </form>
    </div>
  @endif

  {{-- ─── سجل العمليات السابقة ─── --}}
  <div class="workflow-panel">
    <h2>سجل العمليات</h2>
    <div class="sim-table-wrap">
      <table class="sim-table">
        <thead>
          <tr>
            <th>المرجع</th>
            <th>المصدر</th>
            <th>النتيجة</th>
            <th>التاريخ</th>
            <th>المشغّل</th>
            <th>ملاحظات</th>
          </tr>
        </thead>
        <tbody>
          @forelse($operations as $operation)
            <tr>
              <td dir="ltr">{{ $operation->reference }}</td>
              <td>{{ $operation->sourceLabel() }}</td>
              <td>
                <span class="sim-status-badge {{ $operation->result === 'success' ? 'approved' : 'rejected' }}">
                  {{ $operation->resultLabel() }}
                </span>
              </td>
              <td dir="ltr">{{ $operation->executed_at?->format('Y-m-d H:i') }}</td>
              <td>{{ $operation->operator?->name }}</td>
              <td>{{ $operation->error_detail ?: $operation->evidence_reference ?: '—' }}</td>
            </tr>
          @empty
            <tr><td colspan="6" style="text-align:center;color:var(--text-secondary);padding:2rem">لا توجد عمليات مسجّلة.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
</div></section>
@endsection
