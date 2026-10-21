<div class="card" style="padding:1.5rem;margin-top:1rem;overflow-x:auto;">
  <h2>طلبات الخدمات المرتبطة</h2>
  <table class="table" style="width:100%;text-align:start;">
    <thead><tr><th>رقم الطلب</th><th>رمز الخدمة</th><th>الحالة المسجلة</th><th>تاريخ التسجيل</th></tr></thead>
    <tbody>
      @forelse($applications as $application)
        <tr>
          <td>
            @if(auth()->user()->role === 'citizen')
              <a href="{{ route('citizen.applications.show', $application->id) }}">{{ $application->reference }}</a>
            @else
              {{ $application->reference }}
            @endif
          </td>
          <td>{{ $application->service_name ?? $application->service_code }}</td><td>{{ $application->statusLabel() }}</td>
          <td>{{ $application->submitted_at?->format('Y-m-d') ?? $application->created_at?->format('Y-m-d') ?? 'غير متوفر' }}</td>
        </tr>
      @empty
        <tr><td colspan="4">لا توجد طلبات مرتبطة بحسابك أو بهذا السجل في المنصة.</td></tr>
      @endforelse
    </tbody>
  </table>
  {{ $applications->links() }}
</div>
