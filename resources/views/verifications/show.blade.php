@extends('layouts.public')
@section('title','التحقق الإلكتروني')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/simulation-modals.css') }}?v={{ filemtime(public_path('css/simulation-modals.css')) }}">
@endpush
@push('scripts')
<script src="{{ asset('js/simulation-modals.js') }}?v={{ filemtime(public_path('js/simulation-modals.js')) }}" defer></script>
@endpush

@section('content')
<section class="section"><div class="container">
  <div class="ops-page-head">
    <h1>التحقق الإلكتروني — طلب {{ $application->reference }}</h1>
    <p class="ops-help">يمكن تشغيل عمليات التحقق الإلكتروني وعرض نتائجها وسجل المحاولات السابقة.</p>
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
      <div>@foreach($errors->all() as $error)<p style="margin:2px 0">{{ $error }}</p>@endforeach</div>
    </div>
  @endif
  @if($configurationError)
    <div class="sim-alert sim-alert-warning" role="alert">
      <span class="sim-alert-icon" aria-hidden="true">⚠</span>
      <div>خطأ في إعدادات التحقق الإلكتروني — يرجى التحقق من الإعدادات.</div>
    </div>
  @endif

  <p>يمكن بدء عمليات التحقق من خلال الأزرار أدناه. كل عملية تُسجَّل في سجل المحاولات وقاعدة البيانات.</p>

  {{-- ─── بطاقات حالة التحقق ─── --}}
  @foreach($states as $state)
    <article class="card ops-card" style="margin-bottom:1rem">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap">
        <div>
          <h2 style="margin:0 0 6px;font-size:1.05rem">{{ $state['label'] }}</h2>
          <p style="margin:0 0 6px;font-size:.88rem;color:var(--text-secondary)">{{ $state['message'] }}</p>
          <p style="margin:0;font-size:.82rem;color:var(--text-secondary)">
            المزوّد: <strong>{{ $state['provider'] ?? 'غير محدد' }}</strong>
            · {{ $state['required'] ? 'إلزامي' : 'اختياري' }}
            · آخر محاولة: <span dir="ltr">{{ $state['attempted_at']?->format('Y-m-d H:i') ?? 'لم تُجرَ محاولة بعد' }}</span>
          </p>
        </div>
        @can('verifyExternal', $application)
          @if($state['provider'] && !in_array($application->status, ['completed','cancelled','rejected'], true))
            <div>
              <button class="sim-trigger-btn" type="button"
                      data-verify-open="{{ $state['kind'] }}"
                      aria-haspopup="dialog"
                      aria-controls="sim-verify-modal-{{ $state['kind'] }}">
                <span aria-hidden="true">▶</span>
                بدء / إعادة التحقق
              </button>
            </div>
          @endif
        @endcan
      </div>
    </article>

    {{-- ─── نافذة بدء التحقق لهذا النوع ─── --}}
    @can('verifyExternal', $application)
      @if($state['provider'] && !in_array($application->status, ['completed','cancelled','rejected'], true))
        <dialog id="sim-verify-modal-{{ $state['kind'] }}" class="sim-modal-dialog"
                aria-labelledby="verify-title-{{ $state['kind'] }}">
          <div class="sim-modal-head">
            <h2 id="verify-title-{{ $state['kind'] }}" class="sim-modal-title">
              <span class="sim-icon" aria-hidden="true">⚡</span>
              بدء تحقق — {{ $state['label'] }}
            </h2>
            <button class="sim-modal-close" type="button" data-sim-close aria-label="إغلاق النافذة">✕</button>
          </div>
          <div class="sim-modal-body">
            <div class="sim-details">
              <div class="sim-detail-group">
                <dt>رقم الطلب</dt>
                <dd dir="ltr">{{ $application->reference }}</dd>
              </div>
              <div class="sim-detail-group">
                <dt>نوع التحقق</dt>
                <dd>{{ $state['label'] }}</dd>
              </div>
              <div class="sim-detail-group">
                <dt>المزوّد</dt>
                <dd>{{ $state['provider'] }}</dd>
              </div>
              <div class="sim-detail-group">
                <dt>الحالة الحالية</dt>
                <dd>{{ $state['message'] }}</dd>
              </div>
            </div>

            <div class="sim-alert sim-alert-warning" role="note">
              <span class="sim-alert-icon" aria-hidden="true">⚠</span>
              <div>ستُسجَّل هذه المحاولة في قاعدة البيانات. تأكد من المعلومات قبل البدء.</div>
            </div>

            <div id="sim-verify-state"></div>

            <div id="sim-verify-actions">
              <form method="POST" action="{{ route('employee.verifications.start', $application->id) }}"
                    data-verify-form novalidate>
                @csrf
                <input type="hidden" name="kind" value="{{ $state['kind'] }}">
                <input type="hidden" name="idempotency_key" value="{{ (string)\Illuminate\Support\Str::uuid() }}">

                @if($state['kind'] === 'document')
                  <div class="sim-field">
                    <label>اختر الوثيقة المُراد التحقق منها</label>
                    <select name="target_document_id" class="sim-field input" required>
                      <option value="">— اختر الوثيقة —</option>
                      @foreach($application->citizen->documents()->where('document_type_code', $application->service?->document_type)->get() as $document)
                        <option value="{{ $document->id }}">{{ $document->document_number }}</option>
                      @endforeach
                    </select>
                  </div>
                @endif

                <div class="sim-actions">
                  <button type="submit" class="sim-btn sim-btn-primary">
                    <span aria-hidden="true">▶</span> بدء عملية التحقق
                  </button>
                  <button type="button" class="sim-btn sim-btn-outline" data-sim-close>إلغاء</button>
                </div>
              </form>
            </div>
          </div>
        </dialog>
      @endif
    @endcan
  @endforeach

  {{-- ─── سجل المحاولات ─── --}}
  <div class="card ops-card" style="margin-top:2rem">
    <h2 style="font-size:1rem;margin-top:0">سجل محاولات التحقق</h2>
    <p style="font-size:.85rem;color:var(--text-secondary);margin-bottom:1rem">
      سجل كامل لجميع المحاولات السابقة بترتيب زمني تنازلي.
    </p>
    <div class="sim-table-wrap">
      <table class="sim-table">
        <thead>
          <tr>
            <th>النوع</th>
            <th>المزوّد</th>
            <th>الحالة</th>
            <th>التاريخ</th>
          </tr>
        </thead>
        <tbody>
          @forelse($attempts as $attempt)
            <tr>
              <td>{{ \App\Services\Integrations\IntegrationRules::KINDS[$attempt->kind] ?? 'غير معروف' }}</td>
              <td>{{ $attempt->provider }}</td>
              <td>
                <span class="sim-status-badge {{ $attempt->status }}">
                  {{ \App\Enums\VerificationStatus::tryFrom($attempt->status)?->label() ?? 'غير معروف' }}
                </span>
              </td>
              <td dir="ltr">{{ $attempt->started_at->format('Y-m-d H:i') }}</td>
            </tr>
          @empty
            <tr><td colspan="4" style="text-align:center;color:var(--text-secondary);padding:2rem">لا توجد محاولات تحقق سابقة.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
    {{ $attempts->links() }}
  </div>
</div></section>
@endsection
