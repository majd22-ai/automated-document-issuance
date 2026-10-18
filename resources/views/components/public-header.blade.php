@php
  $menus = [
    [
      'id' => 'services',
      'label' => 'بوابة الخدمات',
      'items' => [
        ['خدمات بطاقة الهوية', route('services.identity'), ''],
        ['خدمات جواز السفر', route('services.passports'), ''],
        ['خدمات رخصة القيادة', route('services.driving-licenses'), ''],
        ['خدمة حجز المواعيد', route('appointments.book'), ''],
      ],
    ],
    [
      'id' => 'requirements',
      'label' => 'متطلبات الخدمات',
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
        ☰ القائمة
      </button>

      <div class="gov-nav-links" id="gov-navigation">

        <a
          class="gov-nav-link {{ request()->routeIs('home') ? 'is-active' : '' }}"
          href="{{ route('home') }}"
        >
          الرئيسية
        </a>

        @foreach($menus as $menu)
          <div class="gov-nav-dropdown" data-menu="{{ $menu['id'] }}">
            <button
              type="button"
              class="gov-nav-link gov-dropdown-trigger"
              aria-expanded="false"
              aria-controls="gov-{{ $menu['id'] }}-menu"
            >
              {{ $menu['label'] }}
              <span class="gov-caret" aria-hidden="true">⌄</span>
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

      </div>
    </div>
  </nav>
</header>