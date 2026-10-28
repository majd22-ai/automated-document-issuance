@php
  $account = auth()->user();
  $employeeAccount = $account->role === 'employee';
  $accountLabel = $employeeAccount ? (preg_split('/\s+/u', trim($account->name), 2)[0] ?? '') : $account->name;
  $inboxRoute = $employeeAccount ? 'employee.notifications.index' : 'citizen.notifications.index';
@endphp
<div class="gov-nav-dropdown gov-account-dropdown" data-menu="account">
  <button type="button" class="gov-nav-link gov-dropdown-trigger" aria-expanded="false" aria-controls="gov-account-menu" title="{{ $account->name }}">
    <x-icon name="user" /><span class="gov-account-label">{{ $employeeAccount ? 'الوكيل' : 'المواطن' }} {{ $accountLabel }}</span>
    <span class="gov-caret" aria-hidden="true"><x-icon name="chevron-down" /></span>
  </button>
  <div class="gov-dropdown-content gov-account-menu" id="gov-account-menu">
    @if($employeeAccount)
      @can('manageCitizenActivation', \App\Models\User::class)
        <a href="{{ route('employee.accounts.index') }}">تفعيل حسابات المواطنين</a>
      @endcan
      <a href="{{ route('employee.citizens.index') }}">بيانات المواطنين</a>
      <a href="{{ route('employee.tracking.index') }}">متابعة الطلبات</a>
      <a href="{{ route('employee.verifications.index') }}">التحقق الإلكتروني</a>
      @if($account->report_scope === 'department')<a href="{{ route('employee.reports.index') }}">تقارير القسم</a>@endif
    @else
      <a href="{{ route('citizen.account.status') }}">حالة الحساب والتفعيل</a>
      <a href="{{ route('citizen.data') }}">بياناتي</a>
      <a href="{{ route('citizen.applications.index') }}">طلباتي</a>
    @endif
    <form method="POST" action="{{ route('logout') }}" class="gov-account-logout">@csrf<button type="submit"><x-icon name="log-out" /> تسجيل الخروج</button></form>
  </div>
</div>
<a class="gov-nav-link" href="{{ route($inboxRoute) }}">الإشعارات <span class="gov-notification-count" aria-label="عدد الإشعارات غير المقروءة">{{ $account->unreadNotifications()->count() }}</span></a>
<a class="gov-nav-link" href="{{ route('profile.edit') }}"><x-icon name="user" /> الملف الشخصي</a>
