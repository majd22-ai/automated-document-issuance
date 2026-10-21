<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AdminUserController;

// =====================================================
// الصفحة الرئيسية وأقسام الخدمات
// =====================================================

Route::get('/', function () {
    return view('public.home');
})->name('home');

Route::get('/services/identity', function () {
    return view('public.services.identity');
})->name('services.identity');

Route::get('/services/passports', function () {
    return view('public.services.passports');
})->name('services.passports');

Route::get('/services/driving-licenses', function () {
    return view('public.services.driving-licenses');
})->name('services.driving-licenses');

Route::get('/appointments/book', function () {
    return view('public.appointments');
})->name('appointments.book');

// =====================================================
// الاستعلام والتواصل — واجهات تجريبية
// =====================================================

Route::view('/inquiry/request', 'public.inquiry', [
    'mode' => 'reference',
])->name('inquiry.reference');

Route::view('/inquiry/my-requests', 'public.inquiry', [
    'mode' => 'national',
])->name('inquiry.mine');

Route::view('/contact', 'public.contact', [
    'mode' => 'contact',
])->name('contact.index');

Route::view('/contact/report', 'public.contact', [
    'mode' => 'report',
])->name('contact.report');

Route::view('/contact/complaint', 'public.contact', [
    'mode' => 'complaint',
])->name('contact.complaint');

// =====================================================
// تفاصيل خدمات بطاقة الهوية
// =====================================================

Route::get('/services/identity/{type}', function ($type) {
    $services = [
        'first-issue' => [
            'name' => 'إصدار بطاقة شخصية لأول مرة',
            'short_description' => 'خدمة إصدار بطاقة الهوية الشخصية لأول مرة للمواطنين الذين بلغوا السن القانونية.',
            'description' => 'تتيح هذه الخدمة للمواطنين اليمنيين الذين بلغوا سن الخامسة عشرة الحصول على بطاقة الهوية الشخصية لأول مرة. تُعدّ البطاقة الشخصية وثيقة رسمية إلزامية لجميع المواطنين، وتُستخدم في مختلف المعاملات الرسمية والحكومية.',
            'eligibility' => [
                'أن يكون مقدم الطلب يمني الجنسية',
                'أن يكون قد بلغ 15 سنة أو أكثر',
                'أن لا يكون قد حصل على بطاقة شخصية مسبقاً',
            ],
            'documents' => [
                'شهادة الميلاد أو مستخرج رسمي',
                'صورة شخصية حديثة (4×6) بخلفية بيضاء',
                'إفادة من مكتب السجل المدني بعدم وجود بطاقة سابقة',
            ],
            'steps' => [
                'تسجيل الدخول أو إنشاء حساب جديد على المنصة',
                'تعبئة نموذج طلب الإصدار الأول',
                'رفع المستندات المطلوبة إلكترونياً',
                'دفع الرسوم المقررة',
                'حجز موعد لاستكمال الإجراءات بالحضور الشخصي',
                'استلام البطاقة بعد مراجعة الطلب',
            ],
            'fees' => '1,500 ريال يمني',
            'duration' => '7-10 أيام عمل',
            'requires_appointment' => true,
            'category_name' => 'خدمات البطاقة الشخصية',
            'category_url' => url('/services/identity'),
            'apply_url' => url('/citizen/applications/new?service=identity-first-issue'),
        ],

        'renewal' => [
            'name' => 'تجديد البطاقة الشخصية',
            'short_description' => 'تجديد بطاقة الهوية الشخصية قبل أو بعد انتهاء صلاحيتها مع الاحتفاظ بنفس الرقم الوطني.',
            'description' => 'تُتيح هذه الخدمة للمواطنين تجديد بطاقة الهوية الشخصية منتهية الصلاحية أو التي تقترب من انتهاء صلاحيتها. يمكن تقديم طلب التجديد قبل ثلاثة أشهر من تاريخ الانتهاء.',
            'eligibility' => [
                'امتلاك بطاقة شخصية سارية أو منتهية الصلاحية',
                'أن يكون الرقم الوطني مطابقاً للسجلات الرسمية',
            ],
            'documents' => [
                'البطاقة الشخصية الحالية (سارية أو منتهية)',
                'صورة شخصية حديثة (4×6) بخلفية بيضاء',
            ],
            'steps' => [
                'تسجيل الدخول إلى المنصة',
                'تعبئة نموذج طلب التجديد',
                'رفع صورة البطاقة الحالية والصورة الشخصية',
                'دفع رسوم التجديد',
                'حجز موعد لاستلام البطاقة الجديدة',
            ],
            'fees' => '1,000 ريال يمني',
            'duration' => '5-7 أيام عمل',
            'requires_appointment' => true,
            'category_name' => 'خدمات البطاقة الشخصية',
            'category_url' => url('/services/identity'),
            'apply_url' => url('/citizen/applications/new?service=identity-renewal'),
        ],

        'lost-replacement' => [
            'name' => 'إصدار بدل فاقد للبطاقة الشخصية',
            'short_description' => 'إصدار بدل فاقد عند فقدان البطاقة، مع تسجيل بلاغ الفقدان ومتابعة الطلب.',
            'description' => 'في حال فقدان بطاقة الهوية الشخصية، تتيح هذه الخدمة للمواطن تقديم بلاغ الفقدان واستخراج بدل فاقد عبر المنصة الإلكترونية، مما يُوفّر الوقت والجهد.',
            'eligibility' => [
                'إثبات فقدان البطاقة الشخصية',
                'تقديم بلاغ فقدان من الجهة المختصة',
            ],
            'documents' => [
                'نسخة من بلاغ الفقدان الرسمي',
                'صورة شخصية حديثة (4×6) بخلفية بيضاء',
                'إثبات هوية بديل (جواز سفر أو أي وثيقة رسمية)',
            ],
            'steps' => [
                'تقديم بلاغ الفقدان في أقرب مركز شرطة',
                'تسجيل الدخول إلى المنصة',
                'تعبئة نموذج بدل الفاقد',
                'رفع نسخة من بلاغ الفقدان والمستندات',
                'دفع رسوم بدل الفاقد',
                'حجز موعد واستلام البطاقة الجديدة',
            ],
            'fees' => '2,000 ريال يمني',
            'duration' => '7-14 يوم عمل',
            'requires_appointment' => true,
            'category_name' => 'خدمات البطاقة الشخصية',
            'category_url' => url('/services/identity'),
            'apply_url' => url('/citizen/applications/new?service=identity-lost'),
        ],
    ];

    if (!array_key_exists($type, $services)) {
        abort(404);
    }

    return view('public.services.show', [
        'service' => (object) $services[$type],
    ]);
})->name('services.identity.show');

// =====================================================
// تفاصيل خدمات جواز السفر
// =====================================================

Route::get('/services/passports/{type}', function ($type) {
    $services = [
        'first-issue' => [
            'name' => 'إصدار جواز سفر لأول مرة',
            'short_description' => 'استخراج جواز سفر يمني لأول مرة لجميع الفئات العمرية.',
            'description' => 'تُتيح هذه الخدمة للمواطنين اليمنيين استخراج جواز سفر لأول مرة بطريقة إلكترونية مبسّطة. جواز السفر اليمني وثيقة سفر دولية تُعرّف حاملها خارج الأراضي اليمنية.',
            'eligibility' => [
                'أن يكون مقدم الطلب يمني الجنسية',
                'امتلاك بطاقة هوية شخصية سارية',
                'عدم وجود حكم قضائي بمنع السفر',
            ],
            'documents' => [
                'بطاقة الهوية الشخصية',
                'شهادة الميلاد أو مستخرج رسمي',
                'صورة شخصية بيضاء الخلفية بمواصفات جوازات السفر',
            ],
            'steps' => [
                'تسجيل الدخول وتعبئة النموذج الإلكتروني',
                'رفع المستندات المطلوبة',
                'دفع الرسوم',
                'حجز موعد للتحقق البيومتري',
                'استلام جواز السفر',
            ],
            'fees' => '15,000 ريال يمني',
            'duration' => '14-21 يوم عمل',
            'requires_appointment' => true,
            'category_name' => 'خدمات جواز السفر',
            'category_url' => url('/services/passports'),
            'apply_url' => url('/citizen/applications/new?service=passport-first-issue'),
        ],

        'renewal' => [
            'name' => 'تجديد جواز السفر',
            'short_description' => 'تجديد جواز السفر المنتهية صلاحيته أو القريب من الانتهاء.',
            'description' => 'تُتيح هذه الخدمة تجديد جواز السفر اليمني المنتهية صلاحيته. يُنصح بتجديد الجواز قبل ستة أشهر على الأقل من تاريخ الانتهاء.',
            'eligibility' => [
                'امتلاك جواز سفر يمني سارٍ أو منتهٍ',
                'عدم وجود حكم قضائي بمنع السفر',
            ],
            'documents' => [
                'جواز السفر الحالي',
                'بطاقة الهوية الشخصية',
                'صورة شخصية بمواصفات جوازات السفر',
            ],
            'steps' => [
                'تسجيل الدخول وتعبئة نموذج التجديد',
                'رفع صورة الجواز الحالي',
                'دفع الرسوم',
                'حجز موعد للتحقق',
                'استلام الجواز الجديد',
            ],
            'fees' => '12,000 ريال يمني',
            'duration' => '10-14 يوم عمل',
            'requires_appointment' => true,
            'category_name' => 'خدمات جواز السفر',
            'category_url' => url('/services/passports'),
            'apply_url' => url('/citizen/applications/new?service=passport-renewal'),
        ],
    ];

    if (!array_key_exists($type, $services)) {
        abort(404);
    }

    return view('public.services.show', [
        'service' => (object) $services[$type],
    ]);
})->name('services.passports.show');

// =====================================================
// تفاصيل خدمات رخصة القيادة
// =====================================================

Route::get('/services/driving-licenses/{type}', function ($type) {
    $services = [
        'first-issue' => [
            'name' => 'إصدار رخصة قيادة لأول مرة',
            'short_description' => 'الحصول على رخصة قيادة يمنية لأول مرة بعد اجتياز الاختبارات المطلوبة.',
            'description' => 'تُتيح هذه الخدمة للمواطنين الراغبين في الحصول على رخصة قيادة لأول مرة تقديم طلباتهم إلكترونياً وحجز موعد لاختبارات القيادة النظرية والعملية.',
            'eligibility' => [
                'أن يكون عمر المتقدم 18 سنة فأكثر',
                'اجتياز الفحص الطبي المطلوب',
                'عدم وجود حكم قضائي بسحب الرخصة',
            ],
            'documents' => [
                'بطاقة الهوية الشخصية',
                'شهادة اللياقة الطبية من مستشفى معتمد',
                'صورتان شخصيتان حديثتان',
            ],
            'steps' => [
                'التسجيل وتعبئة نموذج الطلب',
                'رفع المستندات',
                'دفع رسوم التسجيل',
                'حجز موعد الاختبار النظري',
                'اجتياز الاختبار العملي',
                'استلام الرخصة',
            ],
            'fees' => '5,000 ريال يمني',
            'duration' => '21-30 يوم عمل',
            'requires_appointment' => true,
            'category_name' => 'خدمات رخصة القيادة',
            'category_url' => url('/services/driving-licenses'),
            'apply_url' => url('/citizen/applications/new?service=license-first-issue'),
        ],

        'renewal' => [
            'name' => 'تجديد رخصة القيادة',
            'short_description' => 'تجديد رخصة القيادة المنتهية الصلاحية أو التي تقترب من انتهائها.',
            'description' => 'تُتيح هذه الخدمة لحاملي رخص القيادة تجديد رخصهم منتهية الصلاحية أو التي تقترب من الانتهاء. يُنصح بتقديم طلب التجديد قبل شهر من تاريخ الانتهاء.',
            'eligibility' => [
                'امتلاك رخصة قيادة يمنية سارية أو منتهية منذ أقل من سنة',
                'اجتياز الفحص الطبي المجدد',
            ],
            'documents' => [
                'رخصة القيادة الحالية',
                'بطاقة الهوية الشخصية',
                'شهادة اللياقة الطبية المجددة',
            ],
            'steps' => [
                'تسجيل الدخول وتعبئة نموذج التجديد',
                'رفع المستندات',
                'دفع رسوم التجديد',
                'حجز موعد لمراجعة الطلب',
                'استلام الرخصة المجددة',
            ],
            'fees' => '3,000 ريال يمني',
            'duration' => '7-10 أيام عمل',
            'requires_appointment' => false,
            'category_name' => 'خدمات رخصة القيادة',
            'category_url' => url('/services/driving-licenses'),
            'apply_url' => url('/citizen/applications/new?service=license-renewal'),
        ],
    ];

    if (!array_key_exists($type, $services)) {
        abort(404);
    }

    return view('public.services.show', [
        'service' => (object) $services[$type],
    ]);
})->name('services.driving-licenses.show');

// =====================================================
// تسجيل الدخول
// =====================================================

Route::get('/login', function () {
    return view('auth.login');
})->middleware('guest')->name('login');

Route::post('/login', [AuthController::class, 'login'])->middleware(['guest', 'throttle:6,1'])->name('login.post');

// =====================================================
// إنشاء حساب
// =====================================================

Route::get('/register', function () {
    return view('auth.register');
})->middleware('guest')->name('register');

Route::post('/register', [AuthController::class, 'register'])->middleware(['guest', 'throttle:10,1'])->name('register.post');

// =====================================================
// استعادة كلمة المرور وتسجيل الخروج
// =====================================================

Route::get('/forgot-password', function () {
    return view('auth.forgot-password');
})->middleware('guest')->name('password.request');

Route::post('/forgot-password', function (Request $request) {
    $request->validate([
        'email' => 'required|email',
    ], [
        'email.required' => 'البريد الإلكتروني مطلوب.',
        'email.email' => 'البريد الإلكتروني غير صحيح.',
    ]);

    // سلوك النموذج الأكاديمي الحالي: لا يوجد إرسال بريد فعلي.
    return back()->with(
        'status',
        'إذا كان بريدك الإلكتروني مسجلاً، ستصلك رسالة باستعادة كلمة المرور قريباً.'
    );
})->middleware('guest')->name('password.email');

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');
Route::get('/profile', [ProfileController::class, 'edit'])->middleware('auth')->name('profile.edit');
Route::patch('/profile', [ProfileController::class, 'update'])->middleware('auth')->name('profile.update');

// =====================================================
// صفحات المواطن
// =====================================================

Route::middleware(['auth', 'role:citizen'])
    ->prefix('citizen')
    ->name('citizen.')
    ->group(function () {

        Route::get('/dashboard', function () {
            return view('citizen.dashboard');
        })->name('dashboard');

        Route::get('/applications', function () {
            return view('citizen.applications.index');
        })->name('applications.index');

        Route::get('/applications/new', function () {
            return view('citizen.applications.create');
        })->name('applications.create');

        Route::get('/applications/{id}', function ($id) {
            return view('citizen.applications.show', [
                'applicationId' => $id,
            ]);
        })->name('applications.show');

        Route::get('/appointments', function () {
            return view('citizen.appointments.index');
        })->name('appointments.index');

        Route::get('/documents', function () {
            return view('citizen.documents.index');
        })->name('documents.index');

        Route::get('/notifications', function () {
            return view('citizen.notifications.index');
        })->name('notifications.index');

        Route::get('/payments', function () {
            return view('citizen.payments.index');
        })->name('payments.index');

    });

// =====================================================
// صفحات الموظف
// =====================================================

Route::middleware(['auth', 'role:employee'])
    ->prefix('employee')
    ->name('employee.')
    ->group(function () {

        Route::get('/dashboard', function () {
            return view('employee.dashboard');
        })->name('dashboard');

        Route::get('/applications', function () {
            return view('employee.applications.index');
        })->name('applications.index');

        Route::get('/applications/{id}/review', function ($id) {
            return view('employee.reviews.show', [
                'applicationId' => $id,
            ]);
        })->name('applications.review');

        Route::get('/citizens', function () {
            return view('employee.citizens.index');
        })->name('citizens.index');

        Route::get('/issuance', function () {
            return view('employee.issuance.index');
        })->name('issuance.index');

        Route::get('/deliveries', function () {
            return view('employee.deliveries.index');
        })->name('deliveries.index');

    });

// =====================================================
// صفحات المدير
// =====================================================

Route::middleware(['auth', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        Route::get('/dashboard', function () {
            return view('admin.dashboard');
        })->name('dashboard');

        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::patch('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');

        Route::get('/employees', function () {
            return view('admin.employees.index');
        })->name('employees.index');

        Route::get('/services', function () {
            return view('admin.services.index');
        })->name('services.index');

        Route::get('/reports', function () {
            return view('admin.reports.index');
        })->name('reports.index');

        Route::get('/roles', function () {
            return view('admin.roles.index');
        })->name('roles.index');

        Route::get('/audit-logs', function () {
            return view('admin.audit-logs.index');
        })->name('audit-logs.index');

        Route::get('/settings', function () {
            return view('admin.settings.index');
        })->name('settings.index');

    });

// نهاية الملف — لا تضف وسم إغلاق PHP