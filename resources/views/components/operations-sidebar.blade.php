@php
  $operator = auth()->user();
  $adminWorkspace = $operator->role === 'admin';
  $navigation = [];
  if ($adminWorkspace) {
    $navigation = [
      ['نظرة عامة','admin.dashboard','admin.dashboard'],
      ['المستخدمون','admin.users.index','admin.users.*'],
      ['عُقّال الحارات','admin.neighborhood-officials.index','admin.neighborhood-officials.*'],
      ['الموظفون','admin.employees.index','admin.employees.*'],
      ['صلاحيات الخدمات','admin.review-permissions.index','admin.review-permissions.*'],
      ['الخدمات والمتطلبات','admin.services.index','admin.services.*'],
      ['التقارير والإحصاءات','admin.reports.index','admin.reports.*'],
      ['سجل العمليات','admin.audit-logs.index','admin.audit-logs.*'],
    ];
  } else {
    $navigation = array_map(fn($item)=>[$item['label'],$item['route'],$item['pattern']], array_filter(
      app(\App\Services\Workspace\EmployeeWorkspace::class)->modules($operator),
      fn ($item) => $item['key'] !== 'verification'
    ));
  }
  if (\App\Models\NeighborhoodOfficial::residenceOnly($operator)) $navigation = array_values(array_filter($navigation, fn ($item) => in_array($item[1], ['employee.dashboard','employee.notifications.index','employee.neighborhood-attestations.index'], true)));
@endphp
<aside class="ops-sidebar" aria-label="التنقل داخل مساحة العمل">
  <div class="ops-sidebar-identity"><span class="ops-role">{{ $adminWorkspace ? 'الإدارة والرقابة' : 'الموظف المختص' }}</span><strong>{{ $operator->name }}</strong><small>اختر القسم للوصول إلى إجراءاته</small></div>
  <button class="workspace-menu-title" type="button" aria-controls="ops-navigation" aria-expanded="true" data-workspace-menu>أقسام مساحة العمل <span aria-hidden="true">☰</span></button>
  <nav id="ops-navigation" aria-label="أقسام مساحة العمل">
    @foreach($navigation as [$label,$destination,$pattern])
    <a href="{{ route($destination) }}" class="{{ request()->routeIs(...explode(',', $pattern)) ? 'is-current' : '' }}" @if(request()->routeIs(...explode(',', $pattern))) aria-current="page" @endif>{{ $label }}</a>
    @endforeach

  </nav>
</aside>
