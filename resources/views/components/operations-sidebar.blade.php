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
      ['الأقسام','admin.departments.index','admin.departments.*'],
      ['صلاحيات الخدمات','admin.review-permissions.index','admin.review-permissions.*'],
      ['الخدمات والمتطلبات','admin.services.index','admin.services.*'],
      ['التكامل الإلكتروني','admin.integrations.index','admin.integrations.*'],
      ['التقارير والإحصاءات','admin.reports.index','admin.reports.*'],
      ['سجل العمليات','admin.audit-logs.index','admin.audit-logs.*'],
      ['الإعدادات','admin.settings.index','admin.settings.*'],
    ];
    if (\App\Services\Registration\MockCivilSystems::enabled()) $navigation[] = ['أنظمة المحاكاة المدنية','admin.mock-systems.index','admin.mock-systems.*'];
  } else {
    $navigation = array_map(fn($item)=>[$item['label'],$item['route'],$item['pattern']], app(\App\Services\Workspace\EmployeeWorkspace::class)->modules($operator));
  }
  if (\App\Services\FingerprintService::canUse($operator)) $navigation[] = ['استقبال المواطنين وتسجيل البصمة','identity-workflow.reception','identity-workflow.reception'];
  if (\App\Services\FingerprintService::canUse($operator, 'can_approve')) $navigation[] = ['المراجعة الإدارية التجريبية','identity-workflow.administration.index','identity-workflow.administration.*'];
  if (\App\Models\NeighborhoodOfficial::residenceOnly($operator)) $navigation = array_values(array_filter($navigation, fn ($item) => in_array($item[1], ['employee.dashboard','employee.notifications.index','employee.neighborhood-attestations.index'], true)));
@endphp
<aside class="ops-sidebar" aria-label="التنقل داخل مساحة العمل">
  <div class="ops-sidebar-identity"><span class="ops-role">{{ $adminWorkspace ? 'الإدارة والرقابة' : 'الموظف المختص' }}</span><strong>{{ $operator->name }}</strong><small>اختر القسم للوصول إلى إجراءاته</small></div>
  <button class="workspace-menu-title" type="button" aria-controls="ops-navigation" aria-expanded="true" data-workspace-menu>أقسام مساحة العمل <span aria-hidden="true">☰</span></button>
  <nav id="ops-navigation" aria-label="أقسام مساحة العمل">
    @foreach($navigation as [$label,$destination,$pattern])
    <a href="{{ route($destination) }}" class="{{ request()->routeIs($pattern) ? 'is-current' : '' }}" @if(request()->routeIs($pattern)) aria-current="page" @endif>{{ $label }}</a>
    @endforeach

  </nav>
</aside>
