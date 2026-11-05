@extends('layouts.public')
@section('title', 'قائمة محاكاة المصادقات')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/simulation-modals.css') }}?v={{ filemtime(public_path('css/simulation-modals.css')) }}">
@endpush

@section('content')
<section class="section"><div class="container">
  <div class="ops-page-head">
    <h1>قائمة محاكاة المصادقات</h1>
    <p class="ops-help">
      سجل جميع طلبات المصادقة على هويات المواطنين في بيئة المحاكاة.
      اضغط "عرض" لمراجعة تفاصيل كل طلب واتخاذ القرار المناسب.
    </p>
  </div>

  <div class="sim-alert sim-alert-warning" role="note" style="margin-bottom:1.5rem">
    <span class="sim-alert-icon" aria-hidden="true">⚠</span>
    <div>
      <strong>تنبيه:</strong>
      لا تُمثّل هذه البيانات إجراءات حكومية حقيقية.
      بيئة محاكاة للتطوير والاختبار فقط.
    </div>
  </div>

  <div class="card ops-card">
    <div class="sim-table-wrap" style="margin:0">
      <table class="sim-table">
        <thead>
          <tr>
            <th>رقم الطلب</th>
            <th>المستفيد</th>
            <th>جهة المصادقة</th>
            <th>الحالة</th>
            <th>إجراء</th>
          </tr>
        </thead>
        <tbody>
          @forelse($attestations as $row)
            <tr>
              <td dir="ltr">{{ $row->form?->application?->reference }}</td>
              <td>{{ $row->recipient_name }}</td>
              <td>{{ $row->authorityLabel() }}</td>
              <td>
                <span class="sim-status-badge {{ $row->status }}">
                  {{ $row->statusLabel() }}
                </span>
              </td>
              <td>
                <a class="sim-btn sim-btn-outline"
                   style="font-size:.8rem;padding:6px 12px"
                   href="{{ route('simulation.identity-attestations.show', $row) }}">
                  عرض وإجراء
                </a>
              </td>
            </tr>
          @empty
            <tr>
              <td colspan="5" style="text-align:center;color:var(--text-secondary);padding:2.5rem">
                لا توجد طلبات مصادقة حالية.
              </td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
    {{ $attestations->links() }}
  </div>
</div></section>
@endsection
