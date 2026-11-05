@php
  $account = auth()->user();
  $employeeAccount = $account->role === 'employee';
  $accountNameParts = preg_split('/\s+/u', trim($account->name), -1, PREG_SPLIT_NO_EMPTY);
  $accountLabel = implode(' ', array_slice($accountNameParts, 0, $employeeAccount ? 1 : 2));
  $inboxRoute = $employeeAccount ? 'employee.notifications.index' : 'citizen.notifications.index';
@endphp
<div class="gov-nav-dropdown gov-account-dropdown" data-menu="account">
  <button type="button" class="gov-nav-link gov-dropdown-trigger" aria-expanded="false" aria-controls="gov-account-menu" title="{{ $account->name }}">
    <x-icon name="user" /><span class="gov-account-label">{{ $employeeAccount ? 'الوكيل' : 'المواطن' }} {{ $accountLabel }}</span>
    <span class="gov-caret" aria-hidden="true"><x-icon name="chevron-down" /></span>
  </button>
  <div class="gov-dropdown-content gov-account-menu" id="gov-account-menu">
    @if($employeeAccount)
      @unless(request()->routeIs('employee.*','profile.*','identity-workflow.*'))
        @foreach(app(\App\Services\Workspace\EmployeeWorkspace::class)->modules($account) as $item)
        <a href="{{ route($item['route']) }}">{{ $item['label'] }}</a>
        @endforeach
      @endunless
    @else
      @foreach(['citizen.dashboard'=>'ملخص حسابي','citizen.account.status'=>'تفعيل حسابي','citizen.data'=>'بياناتي','citizen.identity-card.index'=>'إصدار البطاقة الشخصية','citizen.applications.index'=>'طلباتي','citizen.appointments.index'=>'مواعيدي','citizen.payments.index'=>'رسومي ومدفوعاتي','citizen.documents.index'=>'وثائقي'] as $destination=>$label)
      <a href="{{ route($destination) }}" @if(request()->routeIs($destination)) aria-current="page" @endif>{{ $label }}</a>
      @endforeach
    @endif
    <form method="POST" action="{{ route('logout') }}" class="gov-account-logout">@csrf<button type="submit"><x-icon name="log-out" /> تسجيل الخروج</button></form>
  </div>
</div>
<a class="gov-nav-link" href="{{ route($inboxRoute) }}">الإشعارات <span class="gov-notification-count" aria-label="عدد الإشعارات غير المقروءة">{{ $account->unreadNotifications()->count() }}</span></a>
<a class="gov-nav-link" href="{{ route('profile.edit') }}"><x-icon name="user" /> الملف الشخصي</a>
