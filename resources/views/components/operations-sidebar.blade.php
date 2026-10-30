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
  } else {
    $grants = $operator->reviewPermissions()->get();
    $has = fn ($ability) => $grants->contains(fn ($permission) => (bool) $permission->{$ability});
    $navigation = [['مهامي اليومية','employee.dashboard','employee.dashboard'],['الإشعارات','employee.notifications.index','employee.notifications.*']];
    if ($operator->can('manageCitizenActivation', \App\Models\User::class)) $navigation[] = ['تفعيل حسابات المواطنين','employee.accounts.index','employee.accounts.*'];
    $navigation[] = ['بيانات المواطنين','employee.citizens.index','employee.citizens.*'];
    if ($has('can_review') || $has('can_approve') || $has('can_assign')) $navigation[] = ['مراجعة الطلبات','employee.applications.index','employee.applications.*'];
    if ($has('can_review') || $has('can_approve') || $has('can_assign') || $has('can_issue') || $has('can_deliver')) $navigation[] = ['متابعة المعاملات','employee.tracking.index','employee.tracking.*'];
    $navigation[] = ['المرفقات والصور','employee.attachments.index','employee.attachments.*'];
    $navigation[] = ['المواعيد','employee.appointments.index','employee.appointments.*'];
    $navigation[] = ['فترات الحجز','employee.appointment-slots.index','employee.appointment-slots.*'];
    $navigation[] = ['الرسوم والمدفوعات','employee.payments.index','employee.payments.*'];
    if ($has('can_verify')) $navigation[] = ['التحقق الإلكتروني','employee.verifications.index','employee.verifications.*'];
    if ($has('can_issue')) $navigation[] = ['إصدار الوثائق','employee.issuance.index','employee.issuance.*'];
    if ($has('can_deliver')) $navigation[] = ['التسليم','employee.deliveries.index','employee.deliveries.*'];
    if ($operator->report_scope === 'department') $navigation[] = ['تقارير القسم','employee.reports.index','employee.reports.*'];
  }
  if (\App\Services\FingerprintService::canUse($operator)) $navigation[] = ['استقبال المواطنين وتسجيل البصمة','identity-workflow.reception','identity-workflow.reception'];
  if (\App\Services\FingerprintService::canUse($operator, 'can_approve')) $navigation[] = ['المراجعة الإدارية التجريبية','identity-workflow.administration.index','identity-workflow.administration.*'];
@endphp
<aside class="ops-sidebar" aria-label="التنقل داخل مساحة العمل">
  <div class="ops-sidebar-identity"><span class="ops-role">{{ $adminWorkspace ? 'الإدارة والرقابة' : 'الموظف المختص' }}</span><strong>{{ $operator->name }}</strong><small>اختر القسم للوصول إلى إجراءاته</small></div>
  <nav id="ops-navigation" aria-label="أقسام مساحة العمل">
    @foreach($navigation as [$label,$destination,$pattern])
    <a href="{{ route($destination) }}" class="{{ request()->routeIs($pattern) ? 'is-current' : '' }}" @if(request()->routeIs($pattern)) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
    <a href="{{ route('profile.edit') }}" @if(request()->routeIs('profile.*')) aria-current="page" @endif>الملف الشخصي</a>
  </nav>
</aside>
