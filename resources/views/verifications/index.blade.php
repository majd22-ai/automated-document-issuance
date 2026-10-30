@extends('layouts.public')
@section('title','قائمة التحقق الإلكتروني')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/simulation-modals.css') }}?v={{ filemtime(public_path('css/simulation-modals.css')) }}">
@endpush

@section('content')
@if(\App\Services\Integrations\LocalCivilRegistryProvider::enabled())
  <div class="container" style="padding-block:1rem">
    <a class="sim-btn sim-btn-outline" href="{{ route('integrations.civil-registry-demo') }}">
      <span aria-hidden="true">🔍</span>
      محاكاة خدمة السجل المدني
    </a>
  </div>
@endif

<section class="section"><div class="container">
  <div class="ops-page-head">
    <h1>التحقق الإلكتروني</h1>
    <p class="ops-help">قائمة الطلبات التي تحتاج إلى تحقق إلكتروني. اختر طلباً لعرض تفاصيله وتشغيل عمليات التحقق.</p>
  </div>

  <p style="color:var(--text-secondary);font-size:.9rem;line-height:1.8;margin-bottom:1rem">
    يمكن إجراء عمليات التحقق الإلكتروني من خلال صفحة تفاصيل كل طلب.
  </p>

  <div class="card ops-card">
    @forelse($applications as $application)
      <article class="ops-card" style="padding:1rem;border-bottom:1px solid var(--border-light)">
        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;flex-wrap:wrap">
          <div>
            <h2 style="margin:0 0 4px;font-size:1rem" dir="ltr">{{ $application->reference }}</h2>
            <p style="margin:0;font-size:.88rem;color:var(--text-secondary)">
              {{ $application->service_name }}
              <span class="sim-status-badge {{ str_contains($application->status, 'completed') ? 'approved' : 'pending' }}" style="margin-inline-start:6px">
                {{ $application->statusLabel() }}
              </span>
            </p>
          </div>
          <a class="sim-btn sim-btn-outline"
             style="font-size:.85rem;padding:8px 16px"
             href="{{ route('employee.verifications.show', $application->id) }}">
            <span aria-hidden="true">⚡</span>
            عرض التحقق الإلكتروني
          </a>
        </div>
      </article>
    @empty
      <div class="sim-alert sim-alert-info" style="margin:0">
        <span class="sim-alert-icon" aria-hidden="true">ℹ</span>
        <div>لا توجد طلبات تحتاج إلى تحقق إلكتروني حالياً.</div>
      </div>
    @endforelse
    {{ $applications->links() }}
  </div>
</div></section>
@endsection
