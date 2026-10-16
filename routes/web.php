<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/*
|--------------------------------------------------------------------------
| Public Routes - الصفحات العامة
|--------------------------------------------------------------------------
*/

// الصفحة الرئيسية
Route::get('/', function () {
    return view('public.home');
})->name('home');

// قسم خدمات البطاقة الشخصية
Route::get('/services/identity', function () {
    return view('public.services.identity');
})->name('services.identity');

// قسم خدمات جواز السفر
Route::get('/services/passports', function () {
    return view('public.services.passports');
})->name('services.passports');

// قسم خدمات رخص القيادة
Route::get('/services/driving-licenses', function () {
    return view('public.services.driving-licenses');
})->name('services.driving-licenses');

// صفحة حجز المواعيد
Route::get('/appointments/book', function () {
    return view('public.appointments');
})->name('appointments.book');


// تفاصيل خدمات البطاقة الشخصية
Route::get('/services/identity/{type}', function ($type) {
    $services = [
        'first-issue' => [
            'name'                => 'إصدار بطاقة شخصية لأول مرة',
            'short_description'   => 'خدمة إصدار بطاقة الهوية الشخصية لأول مرة للمواطنين الذين بلغوا السن القانونية.',
            'description'         => 'تتيح هذه الخدمة للمواطنين اليمنيين الذين بلغوا سن الخامسة عشرة الحصول على بطاقة الهوية الشخصية لأول مرة. تُعدّ البطاقة الشخصية وثيقة رسمية إلزامية لجميع المواطنين، وتُستخدم في مختلف المعاملات الرسمية والحكومية.',
            'eligibility'         => ['أن يكون مقدم الطلب يمني الجنسية', 'أن يكون قد بلغ 15 سنة أو أكثر', 'أن لا يكون قد حصل على بطاقة شخصية مسبقاً'],
            'documents'           => ['شهادة الميلاد أو مستخرج رسمي', 'صورة شخصية حديثة (4×6) بخلفية بيضاء', 'إفادة من مكتب السجل المدني بعدم وجود بطاقة سابقة'],
            'steps'               => ['تسجيل الدخول أو إنشاء حساب جديد على المنصة', 'تعبئة نموذج طلب الإصدار الأول', 'رفع المستندات المطلوبة إلكترونياً', 'دفع الرسوم المقررة', 'حجز موعد لاستكمال الإجراءات بالحضور الشخصي', 'استلام البطاقة بعد مراجعة الطلب'],
            'fees'                => '1,500 ريال يمني',
            'duration'            => '7-10 أيام عمل',
            'requires_appointment'=> true,
            'category_name'       => 'خدمات البطاقة الشخصية',
            'category_url'        => url('/services/identity'),
            'apply_url'           => url('/citizen/applications/new?service=identity-first-issue'),
        ],
        'renewal' => [
            'name'                => 'تجديد البطاقة الشخصية',
            'short_description'   => 'تجديد بطاقة الهوية الشخصية قبل أو بعد انتهاء صلاحيتها مع الاحتفاظ بنفس الرقم الوطني.',
            'description'         => 'تُتيح هذه الخدمة للمواطنين تجديد بطاقة الهوية الشخصية منتهية الصلاحية أو التي تقترب من انتهاء صلاحيتها. يمكن تقديم طلب التجديد قبل ثلاثة أشهر من تاريخ الانتهاء.',
            'eligibility'         => ['امتلاك بطاقة شخصية سارية أو منتهية الصلاحية', 'أن يكون الرقم الوطني مطابقاً للسجلات الرسمية'],
            'documents'           => ['البطاقة الشخصية الحالية (سارية أو منتهية)', 'صورة شخصية حديثة (4×6) بخلفية بيضاء'],
            'steps'               => ['تسجيل الدخول إلى المنصة', 'تعبئة نموذج طلب التجديد', 'رفع صورة البطاقة الحالية والصورة الشخصية', 'دفع رسوم التجديد', 'حجز موعد لاستلام البطاقة الجديدة'],
            'fees'                => '1,000 ريال يمني',
            'duration'            => '5-7 أيام عمل',
            'requires_appointment'=> true,
            'category_name'       => 'خدمات البطاقة الشخصية',
            'category_url'        => url('/services/identity'),
            'apply_url'           => url('/citizen/applications/new?service=identity-renewal'),
        ],
        'lost-replacement' => [
            'name'                => 'إصدار بدل فاقد للبطاقة الشخصية',
            'short_description'   => 'إصدار بدل فاقد عند فقدان البطاقة، مع تسجيل بلاغ الفقدان ومتابعة الطلب.',
            'description'         => 'في حال فقدان بطاقة الهوية الشخصية، تتيح هذه الخدمة للمواطن تقديم بلاغ الفقدان واستخراج بدل فاقد عبر المنصة الإلكترونية، مما يُوفّر الوقت والجهد.',
            'eligibility'         => ['إثبات فقدان البطاقة الشخصية', 'تقديم بلاغ فقدان من الجهة المختصة'],
            'documents'           => ['نسخة من بلاغ الفقدان الرسمي', 'صورة شخصية حديثة (4×6) بخلفية بيضاء', 'إثبات هوية بديل (جواز سفر أو أي وثيقة رسمية)'],
            'steps'               => ['تقديم بلاغ الفقدان في أقرب مركز شرطة', 'تسجيل الدخول إلى المنصة', 'تعبئة نموذج بدل الفاقد', 'رفع نسخة من بلاغ الفقدان والمستندات', 'دفع رسوم بدل الفاقد', 'حجز موعد واستلام البطاقة الجديدة'],
            'fees'                => '2,000 ريال يمني',
            'duration'            => '7-14 يوم عمل',
            'requires_appointment'=> true,
            'category_name'       => 'خدمات البطاقة الشخصية',
            'category_url'        => url('/services/identity'),
            'apply_url'           => url('/citizen/applications/new?service=identity-lost'),
        ],
    ];

    if (!array_key_exists($type, $services)) {
        abort(404);
    }

    return view('public.services.show', ['service' => (object) $services[$type]]);
})->name('services.identity.show');

// تفاصيل خدمات جواز السفر
Route::get('/services/passports/{type}', function ($type) {
    $services = [
        'first-issue' => [
            'name'                => 'إصدار جواز سفر لأول مرة',
            'short_description'   => 'استخراج جواز سفر يمني لأول مرة لجميع الفئات العمرية.',
            'description'         => 'تُتيح هذه الخدمة للمواطنين اليمنيين استخراج جواز سفر لأول مرة بطريقة إلكترونية مبسّطة. جواز السفر اليمني وثيقة سفر دولية تُعرّف حاملها خارج الأراضي اليمنية.',
            'eligibility'         => ['أن يكون مقدم الطلب يمني الجنسية', 'امتلاك بطاقة هوية شخصية سارية', 'عدم وجود حكم قضائي بمنع السفر'],
            'documents'           => ['بطاقة الهوية الشخصية', 'شهادة الميلاد أو مستخرج رسمي', 'صورة شخصية بيضاء الخلفية بمواصفات جوازات السفر'],
            'steps'               => ['تسجيل الدخول وتعبئة النموذج الإلكتروني', 'رفع المستندات المطلوبة', 'دفع الرسوم', 'حجز موعد للتحقق البيومتري', 'استلام جواز السفر'],
            'fees'                => '15,000 ريال يمني',
            'duration'            => '14-21 يوم عمل',
            'requires_appointment'=> true,
            'category_name'       => 'خدمات جواز السفر',
            'category_url'        => url('/services/passports'),
            'apply_url'           => url('/citizen/applications/new?service=passport-first-issue'),
        ],
        'renewal' => [
            'name'                => 'تجديد جواز السفر',
            'short_description'   => 'تجديد جواز السفر المنتهية صلاحيته أو القريب من الانتهاء.',
            'description'         => 'تُتيح هذه الخدمة تجديد جواز السفر اليمني المنتهية صلاحيته. يُنصح بتجديد الجواز قبل ستة أشهر على الأقل من تاريخ الانتهاء.',
            'eligibility'         => ['امتلاك جواز سفر يمني سارٍ أو منتهٍ', 'عدم وجود حكم قضائي بمنع السفر'],
            'documents'           => ['جواز السفر الحالي', 'بطاقة الهوية الشخصية', 'صورة شخصية بمواصفات جوازات السفر'],
            'steps'               => ['تسجيل الدخول وتعبئة نموذج التجديد', 'رفع صورة الجواز الحالي', 'دفع الرسوم', 'حجز موعد للتحقق', 'استلام الجواز الجديد'],
            'fees'                => '12,000 ريال يمني',
            'duration'            => '10-14 يوم عمل',
            'requires_appointment'=> true,
            'category_name'       => 'خدمات جواز السفر',
            'category_url'        => url('/services/passports'),
            'apply_url'           => url('/citizen/applications/new?service=passport-renewal'),
        ],
    ];

    if (!array_key_exists($type, $services)) {
        abort(404);
    }

    return view('public.services.show', ['service' => (object) $services[$type]]);
})->name('services.passports.show');

// تفاصيل خدمات رخصة القيادة
Route::get('/services/driving-licenses/{type}', function ($type) {
    $services = [
        'first-issue' => [
            'name'                => 'إصدار رخصة قيادة لأول مرة',
            'short_description'   => 'الحصول على رخصة قيادة يمنية لأول مرة بعد اجتياز الاختبارات المطلوبة.',
            'description'         => 'تُتيح هذه الخدمة للمواطنين الراغبين في الحصول على رخصة قيادة لأول مرة تقديم طلباتهم إلكترونياً وحجز موعد لاختبارات القيادة النظرية والعملية.',
            'eligibility'         => ['أن يكون عمر المتقدم 18 سنة فأكثر', 'اجتياز الفحص الطبي المطلوب', 'عدم وجود حكم قضائي بسحب الرخصة'],
            'documents'           => ['بطاقة الهوية الشخصية', 'شهادة اللياقة الطبية من مستشفى معتمد', 'صورتان شخصيتان حديثتان'],
            'steps'               => ['التسجيل وتعبئة نموذج الطلب', 'رفع المستندات', 'دفع رسوم التسجيل', 'حجز موعد الاختبار النظري', 'اجتياز الاختبار العملي', 'استلام الرخصة'],
            'fees'                => '5,000 ريال يمني',
            'duration'            => '21-30 يوم عمل',
            'requires_appointment'=> true,
            'category_name'       => 'خدمات رخصة القيادة',
            'category_url'        => url('/services/driving-licenses'),
            'apply_url'           => url('/citizen/applications/new?service=license-first-issue'),
        ],
        'renewal' => [
            'name'                => 'تجديد رخصة القيادة',
            'short_description'   => 'تجديد رخصة القيادة المنتهية الصلاحية أو التي تقترب من انتهائها.',
            'description'         => 'تُتيح هذه الخدمة لحاملي رخص القيادة تجديد رخصهم منتهية الصلاحية أو التي تقترب من الانتهاء. يُنصح بتقديم طلب التجديد قبل شهر من تاريخ الانتهاء.',
            'eligibility'         => ['امتلاك رخصة قيادة يمنية سارية أو منتهية منذ أقل من سنة', 'اجتياز الفحص الطبي المجدد'],
            'documents'           => ['رخصة القيادة الحالية', 'بطاقة الهوية الشخصية', 'شهادة اللياقة الطبية المجددة'],
            'steps'               => ['تسجيل الدخول وتعبئة نموذج التجديد', 'رفع المستندات', 'دفع رسوم التجديد', 'حجز موعد لمراجعة الطلب', 'استلام الرخصة المجددة'],
            'fees'                => '3,000 ريال يمني',
            'duration'            => '7-10 أيام عمل',
            'requires_appointment'=> false,
            'category_name'       => 'خدمات رخصة القيادة',
            'category_url'        => url('/services/driving-licenses'),
            'apply_url'           => url('/citizen/applications/new?service=license-renewal'),
        ],
    ];

    if (!array_key_exists($type, $services)) {
        abort(404);
    }

    return view('public.services.show', ['service' => (object) $services[$type]]);
})->name('services.driving-licenses.show');

/*
|--------------------------------------------------------------------------
| Authentication Routes - مسارات المصادقة
|--------------------------------------------------------------------------
*/

// عرض صفحة تسجيل الدخول
Route::get('/login', function () {
    return view('auth.login');
})->middleware('guest')->name('login');

// معالجة تسجيل الدخول
Route::post('/login', function (Request $request) {
    $credentials = $request->only('identifier', 'password');
    $remember    = $request->boolean('remember');

    // محاولة تسجيل الدخول بالبريد أو اسم المستخدم
    $loginField = filter_var($credentials['identifier'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

    if (Auth::attempt([$loginField => $credentials['identifier'], 'password' => $credentials['password']], $remember)) {
        $request->session()->regenerate();
        $user = Auth::user();

        // التوجيه حسب الدور
        $redirect = $request->input('redirect');
        if ($redirect) {
            return redirect($redirect);
        }

        return match($user->role) {
            'admin'    => redirect()->route('admin.dashboard'),
            'employee' => redirect()->route('employee.dashboard'),
            default    => redirect()->route('citizen.dashboard'),
        };
    }

    return back()->withErrors(['identifier' => 'بيانات الدخول غير صحيحة.'])->withInput($request->only('identifier', 'remember'));
})->middleware('guest')->name('login.post');

// عرض صفحة التسجيل
Route::get('/register', function () {
    return view('auth.register');
})->middleware('guest')->name('register');

// معالجة التسجيل
Route::post('/register', function (Request $request) {
    $validated = $request->validate([
        'name'                  => 'required|string|max:100',
        'national_id'           => 'required|string|max:20|unique:users,username',
        'email'                 => 'required|email|unique:users,email',
        'password'              => 'required|min:8|confirmed',
    ], [
        'name.required'         => 'الاسم الكامل مطلوب.',
        'national_id.required'  => 'الرقم الوطني مطلوب.',
        'national_id.unique'    => 'هذا الرقم الوطني مسجّل مسبقاً.',
        'email.required'        => 'البريد الإلكتروني مطلوب.',
        'email.unique'          => 'هذا البريد الإلكتروني مسجّل مسبقاً.',
        'password.required'     => 'كلمة المرور مطلوبة.',
        'password.min'          => 'يجب أن تكون كلمة المرور 8 أحرف على الأقل.',
        'password.confirmed'    => 'كلمتا المرور غير متطابقتين.',
    ]);

    $user = \App\Models\User::create([
        'name'     => $validated['name'],
        'username' => $validated['national_id'],
        'email'    => $validated['email'],
        'password' => bcrypt($validated['password']),
        'role'     => 'citizen',
    ]);

    Auth::login($user);
    $request->session()->regenerate();

    return redirect()->route('citizen.dashboard');
})->middleware('guest')->name('register.post');

// عرض صفحة استعادة كلمة المرور
Route::get('/forgot-password', function () {
    return view('auth.forgot-password');
})->middleware('guest')->name('password.request');

// معالجة طلب استعادة كلمة المرور
Route::post('/forgot-password', function (Request $request) {
    $request->validate(['email' => 'required|email'], ['email.required' => 'البريد الإلكتروني مطلوب.', 'email.email' => 'البريد الإلكتروني غير صحيح.']);
    // في النموذج الأكاديمي نعرض رسالة ناجحة دون إرسال فعلي
    return back()->with('status', 'إذا كان بريدك الإلكتروني مسجلاً، ستصلك رسالة باستعادة كلمة المرور قريباً.');
})->middleware('guest')->name('password.email');

// تسجيل الخروج
Route::post('/logout', function (Request $request) {
    Auth::logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect()->route('home');
})->name('logout');

/*
|--------------------------------------------------------------------------
| Citizen Routes - مسارات المواطن
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->prefix('citizen')->name('citizen.')->group(function () {

    // لوحة تحكم المواطن
    Route::get('/dashboard', function () {
        return view('citizen.dashboard');
    })->name('dashboard');

    // قائمة طلبات المواطن
    Route::get('/applications', function () {
        return view('citizen.applications.index');
    })->name('applications.index');

    // تقديم طلب جديد
    Route::get('/applications/new', function () {
        return view('citizen.applications.create');
    })->name('applications.create');

    // عرض طلب محدد
    Route::get('/applications/{id}', function ($id) {
        return view('citizen.applications.show', ['applicationId' => $id]);
    })->name('applications.show');

    // قائمة المواعيد
    Route::get('/appointments', function () {
        return view('citizen.appointments.index');
    })->name('appointments.index');

    // وثائق المواطن
    Route::get('/documents', function () {
        return view('citizen.documents.index');
    })->name('documents.index');

    // الإشعارات
    Route::get('/notifications', function () {
        return view('citizen.notifications.index');
    })->name('notifications.index');

    // المدفوعات
    Route::get('/payments', function () {
        return view('citizen.payments.index');
    })->name('payments.index');

});

/*
|--------------------------------------------------------------------------
| Employee Routes - مسارات الموظف
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->prefix('employee')->name('employee.')->group(function () {

    // لوحة تحكم الموظف
    Route::get('/dashboard', function () {
        return view('employee.dashboard');
    })->name('dashboard');

    // قائمة الطلبات للمراجعة
    Route::get('/applications', function () {
        return view('employee.applications.index');
    })->name('applications.index');

    // مراجعة طلب
    Route::get('/applications/{id}/review', function ($id) {
        return view('employee.reviews.show', ['applicationId' => $id]);
    })->name('applications.review');

    // قائمة المواطنين
    Route::get('/citizens', function () {
        return view('employee.citizens.index');
    })->name('citizens.index');

    // الإصدار
    Route::get('/issuance', function () {
        return view('employee.issuance.index');
    })->name('issuance.index');

    // التسليم
    Route::get('/deliveries', function () {
        return view('employee.deliveries.index');
    })->name('deliveries.index');

});

/*
|--------------------------------------------------------------------------
| Admin Routes - مسارات المدير
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])->prefix('admin')->name('admin.')->group(function () {

    // لوحة تحكم المدير
    Route::get('/dashboard', function () {
        return view('admin.dashboard');
    })->name('dashboard');

    // إدارة المستخدمين
    Route::get('/users', function () {
        return view('admin.users.index');
    })->name('users.index');

    // إدارة الموظفين
    Route::get('/employees', function () {
        return view('admin.employees.index');
    })->name('employees.index');

    // إدارة الخدمات
    Route::get('/services', function () {
        return view('admin.services.index');
    })->name('services.index');

    // التقارير
    Route::get('/reports', function () {
        return view('admin.reports.index');
    })->name('reports.index');

    // إدارة الأدوار
    Route::get('/roles', function () {
        return view('admin.roles.index');
    })->name('roles.index');

    // سجلات النظام
    Route::get('/audit-logs', function () {
        return view('admin.audit-logs.index');
    })->name('audit-logs.index');

    // الإعدادات
    Route::get('/settings', function () {
        return view('admin.settings.index');
    })->name('settings.index');

});
