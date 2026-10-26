@php
  $menus = [
    [
      'id' => 'services',
      'label' => 'بوابة الخدمات',
      'icon' => 'briefcase',
      'items' => [
        ['جميع الخدمات الحكومية', route('services.index'), ''],
        ['خدمات بطاقة الهوية', route('services.identity'), ''],
        ['خدمات جواز السفر', route('services.passports'), ''],
        ['خدمات رخصة القيادة', route('services.driving-licenses'), ''],
        ['خدمة حجز المواعيد', route('appointments.book'), ''],
      ],
    ],
    [
      'id' => 'requirements',
      'label' => 'متطلبات الخدمات',
      'icon' => 'clipboard-list',
      'items' => [
        [
          'متطلبات الهوية الوطنية',
          route('services.identity').'#requirements',
          'الإصدار لأول مرة والتجديد وتحديث البيانات',
        ],
        [
          'متطلبات جواز السفر',
          route('services.passports').'#requirements',
          'إصدار وتجديد جواز السفر',
        ],
        [
          'متطلبات رخصة القيادة',
          route('services.driving-licenses').'#requirements',
          'إصدار وتجديد رخصة القيادة',
        ],
      ],
    ],
    [
      'id' => 'inquiry',
      'label' => 'الاستعلام',
      'icon' => 'search',
      'items' => [
        [
          'الاستعلام عن حالة طلب',
          route('inquiry.reference'),
          'متابعة معاملة محددة باستخدام رقم الطلب المرجعي',
        ],
        [
          'الاستعلام عن جميع طلباتي',
          route('inquiry.mine'),
          'عرض المعاملات المرتبطة بالرقم الوطني',
        ],
      ],
    ],
    [
      'id' => 'contact',
      'label' => 'تواصل معنا',
      'icon' => 'phone',
      'items' => [
        ['تواصل معنا', route('contact.index'), ''],
        ['تقديم بلاغ إلى الجهة المعنية', route('contact.report'), ''],
        ['تقديم شكوى', route('contact.complaint'), 'خاص بالمواطنين'],
      ],
    ],
  ];
@endphp

<header class="gov-site-header">
  <div class="gov-contact-strip">
    <div class="gov-container gov-contact-inner">
      منصة تجريبية أكاديمية وليست جهة حكومية لإصدار الوثائق
    </div>
  </div>

  <nav class="gov-main-nav" aria-label="القائمة الرئيسية">
    <div class="gov-container gov-nav-inner">

      <button
        type="button"
        class="gov-nav-toggle"
        aria-expanded="false"
        aria-controls="gov-navigation"
      >
        <x-icon name="menu" /> القائمة
      </button>

      <div class="gov-nav-links" id="gov-navigation">

        <a
          class="gov-nav-link {{ request()->routeIs('home') ? 'is-active' : '' }}"
          href="{{ route('home') }}"
        >
          <x-icon name="home" /> الرئيسية
        </a>

        @foreach($menus as $menu)
          <div class="gov-nav-dropdown" data-menu="{{ $menu['id'] }}">
            <button
              type="button"
              class="gov-nav-link gov-dropdown-trigger"
              aria-expanded="false"
              aria-controls="gov-{{ $menu['id'] }}-menu"
            >
              <x-icon name="{{ $menu['icon'] }}" /> {{ $menu['label'] }}
              <span class="gov-caret" aria-hidden="true"><x-icon name="chevron-down" /></span>
            </button>

            <div
              class="gov-dropdown-content"
              id="gov-{{ $menu['id'] }}-menu"
            >
              @foreach($menu['items'] as [$title, $href, $description])
                <a href="{{ $href }}">
                  <span>{{ $title }}</span>

                  @if($description)
                    <small>{{ $description }}</small>
                  @endif
                </a>
              @endforeach
            </div>
          </div>
        @endforeach

        @auth
          @if(auth()->user()->role === 'citizen')
            <a class="gov-nav-link" href="{{ route('citizen.account.status') }}">حالة الحساب والتفعيل</a>
            <a class="gov-nav-link" href="{{ route('citizen.data') }}"><x-icon name="identity" /> بياناتي</a>
            <a class="gov-nav-link" href="{{ route('citizen.applications.index') }}"><x-icon name="clipboard-list" /> طلباتي</a>
            <a class="gov-nav-link" href="{{ route('citizen.notifications.index') }}">الإشعارات ({{ auth()->user()->unreadNotifications()->count() }})</a>
          @elseif(auth()->user()->role === 'employee')
            @can('manageCitizenActivation', \App\Models\User::class)<a class="gov-nav-link" href="{{ route('employee.accounts.index') }}">تفعيل حسابات المواطنين</a>@endcan
            <a class="gov-nav-link" href="{{ route('employee.citizens.index') }}"><x-icon name="users" /> بيانات المواطنين</a>
            <a class="gov-nav-link" href="{{ route('employee.tracking.index') }}">متابعة الطلبات</a>
            <a class="gov-nav-link" href="{{ route('employee.verifications.index') }}">التحقق الإلكتروني</a>
            @if(auth()->user()->report_scope==='department')<a class="gov-nav-link" href="{{ route('employee.reports.index') }}">تقارير القسم</a>@endif
          @elseif(auth()->user()->role === 'admin')
            <a class="gov-nav-link" href="{{ route('admin.dashboard') }}">الإدارة والرقابة</a>
          @endif
          <a class="gov-nav-link" href="{{ route('profile.edit') }}"><x-icon name="user" /> الملف الشخصي</a>
          <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="gov-nav-link" type="submit"><x-icon name="log-out" /> تسجيل الخروج</button>
          </form>
        @endauth
      </div>
    </div>
  </nav>
</header>
