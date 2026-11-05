@extends('layouts.public')
@section('title', 'محاكاة خدمة السجل المدني — بيئة التجربة')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/simulation-modals.css') }}?v={{ filemtime(public_path('css/simulation-modals.css')) }}">
@endpush
@push('scripts')
<script src="{{ asset('js/simulation-modals.js') }}?v={{ filemtime(public_path('js/simulation-modals.js')) }}" defer></script>
@endpush

@section('content')
<section class="section"><div class="container">
  <div class="ops-page-head">
    <h1>محاكاة خدمة السجل المدني</h1>
    <p class="ops-help">بيئة اختبار تفاعلية تُتيح التحقق من بيانات السجل المدني باستخدام سجلات تجريبية محلية. لا تُمثّل بيانات مواطنين حقيقيين.</p>
  </div>

  {{-- إشعار بيئة المحاكاة --}}
  <div class="sim-alert sim-alert-warning" style="margin-bottom:1.5rem" role="note">
    <span class="sim-alert-icon" aria-hidden="true">⚠</span>
    <div>
      <strong>بيئة محاكاة تجريبية.</strong>
      لا تُمثّل هذه الصفحة واجهة حكومية حقيقية.
      البيانات التجريبية محلية بالكامل ولا تتصل بأنظمة خارجية.
    </div>
  </div>

  {{-- بطاقة السجلات المتاحة --}}
  <div class="card ops-card" style="margin-bottom:1.5rem">
    <h2 style="margin-top:0;font-size:1rem;color:var(--text-secondary)">السجلات التجريبية المتاحة</h2>
    <div class="sim-records">
      @foreach($records as $number => $record)
        <div class="sim-record-item">
          <span class="sim-record-id">{{ $number }}</span>
          <span>{{ $record['name'] }}</span>
          <span class="sim-record-available {{ $record['available'] ? 'yes' : 'no' }}">
            {{ $record['available'] ? 'متاح للتحقق' : 'غير متاح' }}
          </span>
        </div>
      @endforeach
    </div>
    <p style="font-size:.82rem;color:var(--text-secondary);margin:0">
      للتحقق من التطابق: أدخل رقم هوية وطنية مطابقاً مع الاسم الصحيح.
      للاختبار بحالة عدم تطابق استخدم: <strong dir="ltr">99000000009</strong>.
    </p>
  </div>

  {{-- زر فتح نافذة المحاكاة --}}
  <div>
    <button class="sim-trigger-btn" type="button" data-civil-demo-open aria-haspopup="dialog" aria-controls="sim-civil-modal">
      <span aria-hidden="true">🔍</span>
      تشغيل محاكاة التحقق من السجل المدني
    </button>
  </div>

  {{-- نتيجة سابقة (fallback للـ non-JS) --}}
  @if($outcome)
    <div class="sim-alert sim-alert-{{ $outcome === 'match' ? 'success' : 'error' }}" style="margin-top:1.5rem" role="status">
      <span class="sim-alert-icon" aria-hidden="true">{{ $outcome === 'match' ? '✓' : '✗' }}</span>
      <div>
        <strong>نتيجة التحقق:</strong>
        {{ match($outcome) {
          'match'       => 'تطابقت البيانات مع سجلات قاعدة البيانات التجريبية.',
          'mismatch'    => 'البيانات المُدخلة لا تطابق ما في السجل.',
          'not_found'   => 'لم يُعثر على رقم هوية بهذا الرقم.',
          'unavailable' => 'خدمة السجل المدني غير متاحة حالياً.',
          default       => 'نتيجة غير معروفة.'
        } }}
        <p style="margin:4px 0 0;font-size:.85rem">لا تُولّد هذه المحاكاة بيانات حقيقية ولا ترتبط بأنظمة حكومية.</p>
      </div>
    </div>
  @endif
</div></section>

{{-- ═══════════════════════════════════════════
     نافذة محاكاة السجل المدني
══════════════════════════════════════════ --}}
<dialog id="sim-civil-modal" class="sim-modal-dialog" aria-labelledby="civil-modal-title">
  <div class="sim-modal-head">
    <h2 id="civil-modal-title" class="sim-modal-title">
      <span class="sim-icon" aria-hidden="true">🗂</span>
      محاكاة التحقق من السجل المدني
    </h2>
    <button class="sim-modal-close" type="button" data-sim-close aria-label="إغلاق النافذة">✕</button>
  </div>
  <div class="sim-modal-body">
    <div class="sim-alert sim-alert-warning" role="note">
      <span class="sim-alert-icon" aria-hidden="true">⚠</span>
      <div>بيئة محاكاة محلية — لا تتصل بأنظمة حكومية حقيقية.</div>
    </div>

    <form id="sim-civil-form" method="POST" action="{{ route('integrations.civil-registry-demo.run') }}" novalidate>
      @csrf
      <div class="sim-field">
        <label for="sim-national-id">رقم الهوية الوطنية</label>
        <input id="sim-national-id" type="text" name="national_id"
               value="{{ old('national_id', $input['national_id'] ?? '') }}"
               pattern="[0-9]{11}" maxlength="11" inputmode="numeric" dir="ltr"
               placeholder="00000000000" required>
        @error('national_id')<p class="sim-field-error">{{ $message }}</p>@enderror
      </div>
      <div class="sim-field">
        <label for="sim-name">الاسم الكامل</label>
        <input id="sim-name" type="text" name="name"
               value="{{ old('name', $input['name'] ?? '') }}"
               maxlength="150" placeholder="الاسم كما في السجل المدني" required>
        @error('name')<p class="sim-field-error">{{ $message }}</p>@enderror
      </div>

      {{-- منطقة النتيجة --}}
      <div id="sim-civil-result" role="status" aria-live="polite"></div>

      <div class="sim-actions">
        <button type="submit" class="sim-btn sim-btn-primary">
          <span aria-hidden="true">🔍</span> تنفيذ المحاكاة
        </button>
        <button type="button" class="sim-btn sim-btn-outline" data-sim-close>إغلاق</button>
      </div>
    </form>
  </div>
</dialog>
@endsection
