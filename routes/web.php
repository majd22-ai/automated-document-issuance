<?php

use App\Http\Controllers\AdminGovernmentServiceController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\ApplicationReviewController;
use App\Http\Controllers\ApplicationTrackingController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CitizenAccountController;
use App\Http\Controllers\CitizenAttachmentController;
use App\Http\Controllers\CitizenDataController;
use App\Http\Controllers\CitizenNotificationController;
use App\Http\Controllers\CivilRegistrySimulationController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\DocumentIssuanceController;
use App\Http\Controllers\ElectronicVerificationController;
use App\Http\Controllers\EmployeeManagementController;
use App\Http\Controllers\EmployeeNotificationController;
use App\Http\Controllers\EmployeePasswordController;
use App\Http\Controllers\GovernmentServiceController;
use App\Http\Controllers\LocalPasswordRecoveryController;
use App\Http\Controllers\OperationalReportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\RequestOperationsController;
use App\Http\Controllers\ReviewPermissionController;
use App\Http\Controllers\ServiceRequestController;
use App\Http\Middleware\EnsureCitizenDataIsComplete;
use App\Services\Registration\LocalPasswordRecovery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Route;

Route::post('/integrations/civil-registry-demo', [CivilRegistrySimulationController::class, 'preview'])
    ->middleware(['auth', 'role:admin,employee', 'throttle:30,1'])
    ->name('integrations.civil-registry-demo.run');
Route::get('/integrations/civil-registry-demo', [CivilRegistrySimulationController::class, 'preview'])
    ->middleware(['auth', 'role:admin,employee'])
    ->name('integrations.civil-registry-demo');

Route::get('/reset-password/{token}', [EmployeePasswordController::class, 'form'])->middleware('guest')->name('password.reset');
Route::post('/reset-password', [EmployeePasswordController::class, 'reset'])->middleware(['guest', 'throttle:6,1'])->name('password.update');

// =====================================================
// الصفحة الرئيسية وأقسام الخدمات
// =====================================================

Route::get('/', function () {
    return view('public.home');
})->name('home');

Route::get('/services', [GovernmentServiceController::class, 'index'])->name('services.index');
Route::get('/services/catalog/{service:code}', [GovernmentServiceController::class, 'show'])->name('services.catalog.show');
Route::get('/services/identity', [GovernmentServiceController::class, 'index'])->defaults('documentType', 'identity')->name('services.identity');

Route::get('/services/passports', [GovernmentServiceController::class, 'index'])->defaults('documentType', 'passport')->name('services.passports');

Route::get('/services/driving-licenses', [GovernmentServiceController::class, 'index'])->defaults('documentType', 'driving_license')->name('services.driving-licenses');

Route::get('/appointments/book', function () {
    return view('public.appointments');
})->name('appointments.book');

// =====================================================
// الاستعلام والتواصل — واجهات تجريبية
// =====================================================

Route::get('/inquiry/request', fn () => redirect()->route(auth()->user()->role === 'employee' ? 'employee.tracking.index' : (auth()->user()->role === 'admin' ? 'admin.dashboard' : 'citizen.applications.index')))->middleware('auth')->name('inquiry.reference');
Route::get('/inquiry/my-requests', fn () => redirect()->route(auth()->user()->role === 'employee' ? 'employee.tracking.index' : (auth()->user()->role === 'admin' ? 'admin.dashboard' : 'citizen.applications.index')))->middleware('auth')->name('inquiry.mine');

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

Route::get('/services/identity/{type}', [GovernmentServiceController::class, 'legacyDetail'])->defaults('documentType', 'identity')->name('services.identity.show');
Route::get('/services/passports/{type}', [GovernmentServiceController::class, 'legacyDetail'])->defaults('documentType', 'passport')->name('services.passports.show');
Route::get('/services/driving-licenses/{type}', [GovernmentServiceController::class, 'legacyDetail'])->defaults('documentType', 'driving_license')->name('services.driving-licenses.show');

// =====================================================
// تسجيل الدخول
// =====================================================

Route::get('/login', function () {
    return response()->view('auth.login')->header('Cache-Control', 'no-store, private');
})->middleware('guest')->name('login');

Route::post('/login', [AuthController::class, 'login'])->middleware(['guest', 'throttle:6,1'])->name('login.post');

// =====================================================
// إنشاء حساب
// =====================================================

Route::get('/register', [RegistrationController::class, 'show'])->middleware('guest')->name('register');
Route::post('/register', [RegistrationController::class, 'store'])->middleware(['guest', 'throttle:10,1'])->name('register.post');
Route::middleware('guest')->controller(RegistrationController::class)->group(function () {
    Route::get('/register/captcha', 'captcha')->middleware('throttle:30,1')->name('register.captcha');
    Route::post('/register/captcha/check', 'checkCaptcha')->middleware('throttle:10,1')->name('register.captcha.check');
    Route::get('/register/verify', 'verifyPage')->name('register.verify');
    Route::post('/register/verify', 'verify')->middleware('throttle:10,1')->name('register.verify.post');
    Route::post('/register/resend', 'resend')->middleware('throttle:5,1')->name('register.resend');
    Route::post('/register/edit', 'edit')->middleware('throttle:10,1')->name('register.edit');
});
Route::get('/register/complete', [RegistrationController::class, 'complete'])->name('register.complete');
Route::view('/terms', 'auth.terms')->name('terms');

// =====================================================
// استعادة كلمة المرور وتسجيل الخروج
// =====================================================

Route::get('/forgot-password', function (Request $request, LocalPasswordRecovery $recovery) {
    $state = $recovery->pending($request);

    return response()->view('auth.forgot-password', ['stage' => $state ? ($state['verified'] ? 3 : 2) : 1, 'recoveryEmail' => $state['email'] ?? null])->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'no-referrer');
})->middleware('guest')->name('password.request');

Route::post('/forgot-password', function (Request $request) {
    $request->session()->forget('local_recovery_password');
    $request->validate([
        'email' => 'required|email',
    ], [
        'email.required' => 'البريد الإلكتروني مطلوب.',
        'email.email' => 'البريد الإلكتروني غير صحيح.',
    ]);

    if (LocalPasswordRecovery::enabled()) {
        $preview = app(LocalPasswordRecovery::class)->start($request, $request->input('email'));
        $response = redirect()->route('password.request')->with('status', 'تجربة محلية: إذا كان البريد لحساب نشط، يظهر رمز الاستعادة في النافذة المنبثقة. لم يُرسل بريد فعلي.');

        return $preview === null ? $response : $response->with('local_recovery_preview', Crypt::encryptString(json_encode($preview, JSON_THROW_ON_ERROR)));
    }

    // سلوك النموذج الأكاديمي الحالي: لا يوجد إرسال بريد فعلي.
    return back()->with(
        'status',
        'إذا كان بريدك الإلكتروني مسجلاً، ستصلك رسالة باستعادة كلمة المرور قريباً.'
    );
})->middleware(['guest', 'throttle:6,1,password-recovery-email:'])->name('password.email');

Route::post('/forgot-password/local/verify', [LocalPasswordRecoveryController::class, 'verify'])->middleware(['guest', 'throttle:6,1,password-recovery-code:'])->block(10, 10)->name('password.local.verify');
Route::post('/forgot-password/local/reset', [LocalPasswordRecoveryController::class, 'complete'])->middleware(['guest', 'throttle:6,1,password-recovery-save:'])->block(10, 10)->name('password.local.complete');

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');
Route::get('/notifications/unread-count', function (Request $request) {
    return response()->json(['unread' => $request->user()->unreadNotifications()->count()])->header('Cache-Control', 'no-store, private');
})->middleware(['auth', 'throttle:60,1,notification-count:'])->name('notifications.unread-count');
Route::get('/profile', [ProfileController::class, 'edit'])->middleware('auth')->name('profile.edit');
Route::patch('/profile', [ProfileController::class, 'update'])->middleware('auth')->name('profile.update');

Route::get('/attachments/{attachment}/preview', [CitizenAttachmentController::class, 'preview'])->middleware('auth')->name('attachments.preview');
Route::get('/attachments/{attachment}/download', [CitizenAttachmentController::class, 'download'])->middleware('auth')->name('attachments.download');

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

        Route::get('/data', [CitizenDataController::class, 'show'])->name('data');
        Route::get('/account/status', [CitizenAccountController::class, 'status'])->name('account.status');
        Route::post('/account/activation-request', [CitizenAccountController::class, 'submit'])->middleware('throttle:6,1')->name('account.activation-request');
        Route::get('/services/{service}/attachments', [CitizenAttachmentController::class, 'index'])->name('services.attachments.index');
        Route::post('/services/{service}/attachments', [CitizenAttachmentController::class, 'store'])->middleware('throttle:20,1')->name('services.attachments.store');
        Route::get('/applications/{id}/attachments', [CitizenAttachmentController::class, 'application'])->name('applications.attachments.index');
        Route::post('/applications/{id}/attachments', [CitizenAttachmentController::class, 'storeForApplication'])->middleware('throttle:20,1')->name('applications.attachments.store');
        Route::delete('/attachments/{attachment}', [CitizenAttachmentController::class, 'destroy'])->name('attachments.destroy');
        Route::get('/applications', [ApplicationTrackingController::class, 'index'])->name('applications.index');

        Route::get('/applications/new', [GovernmentServiceController::class, 'entry'])->middleware(EnsureCitizenDataIsComplete::class)->name('applications.create');
        Route::post('/services/{service}/start', [GovernmentServiceController::class, 'start'])->middleware(EnsureCitizenDataIsComplete::class)->name('services.start');
        Route::post('/services/{service}/preview', [ServiceRequestController::class, 'preview'])->middleware(EnsureCitizenDataIsComplete::class)->name('services.preview');
        Route::get('/applications/preview/{token}', [ServiceRequestController::class, 'review'])->whereUuid('token')->name('applications.preview');
        Route::post('/services/{service}/submit', [ServiceRequestController::class, 'submit'])->middleware(EnsureCitizenDataIsComplete::class)->name('services.submit');
        Route::get('/applications/{id}/verifications', [ElectronicVerificationController::class, 'show'])->name('verifications.show');
        Route::get('/applications/{id}/success', [ServiceRequestController::class, 'success'])->name('applications.success');

        Route::get('/applications/{id}', [ApplicationTrackingController::class, 'show'])->name('applications.show');
        Route::get('/applications/{id}/correction', [ApplicationReviewController::class, 'correction'])->name('applications.correction');
        Route::post('/applications/{id}/resubmit', [ApplicationReviewController::class, 'resubmit'])->middleware('throttle:20,1')->name('applications.resubmit');
        Route::post('/applications/{id}/requirements/check', [CitizenDataController::class, 'recheckRequirements'])->name('applications.requirements.check');

        Route::get('/appointments', [AppointmentController::class, 'index'])->name('appointments.index');
        Route::get('/applications/{id}/operations', [RequestOperationsController::class, 'show'])->name('applications.operations');
        Route::post('/applications/{id}/appointments', [AppointmentController::class, 'book'])->middleware('throttle:20,1')->name('applications.appointments.store');
        Route::patch('/appointments/{appointment}', [AppointmentController::class, 'change'])->name('appointments.update');

        Route::get('/documents', [CitizenDataController::class, 'documents'])->name('documents.index');
        Route::get('/documents/{id}', [DocumentIssuanceController::class, 'show'])->name('documents.show');
        Route::get('/documents/{id}/download', [DocumentIssuanceController::class, 'download'])->name('documents.download');
        Route::post('/documents/{id}/delivery', [DocumentIssuanceController::class, 'choose'])->name('documents.delivery.choose');

        Route::get('/notifications', [CitizenNotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/read-all', [CitizenNotificationController::class, 'readAll'])->name('notifications.read-all');
        Route::post('/notifications/{id}/read', [CitizenNotificationController::class, 'read'])->whereUuid('id')->name('notifications.read');
        Route::post('/notifications/{id}/open', [CitizenNotificationController::class, 'open'])->whereUuid('id')->name('notifications.open');

        Route::get('/payments', [RequestOperationsController::class, 'payments'])->name('payments.index');

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

        Route::get('/applications', [ApplicationReviewController::class, 'index'])->name('applications.index');
        Route::get('/reports', [OperationalReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export', [OperationalReportController::class, 'export'])->middleware('throttle:10,1')->name('reports.export');
        Route::get('/applications/{id}/verifications', [ElectronicVerificationController::class, 'show'])->name('verifications.show');
        Route::post('/applications/{id}/verifications', [ElectronicVerificationController::class, 'start'])->middleware('throttle:10,1')->name('verifications.start');
        Route::get('/verifications', [ElectronicVerificationController::class, 'index'])->name('verifications.index');
        Route::get('/tracking', [ApplicationTrackingController::class, 'index'])->name('tracking.index');
        Route::get('/tracking/{id}', [ApplicationTrackingController::class, 'show'])->name('tracking.show');
        Route::get('/appointments', [AppointmentController::class, 'index'])->name('appointments.index');
        Route::get('/appointment-slots', [AppointmentController::class, 'slots'])->name('appointment-slots.index');
        Route::post('/appointment-slots', [AppointmentController::class, 'saveSlot'])->name('appointment-slots.store');
        Route::patch('/appointment-slots/{slot}', [AppointmentController::class, 'saveSlot'])->name('appointment-slots.update');
        Route::get('/applications/{id}/operations', [RequestOperationsController::class, 'show'])->name('applications.operations');
        Route::post('/applications/{id}/appointments', [AppointmentController::class, 'book'])->name('applications.appointments.store');
        Route::patch('/appointments/{appointment}', [AppointmentController::class, 'change'])->name('appointments.update');
        Route::post('/applications/{id}/examinations', [RequestOperationsController::class, 'examination'])->name('applications.examinations.store');
        Route::post('/applications/{id}/charges', [RequestOperationsController::class, 'charge'])->name('applications.charges.store');
        Route::post('/applications/{id}/payments', [RequestOperationsController::class, 'payment'])->name('applications.payments.store');
        Route::get('/payments', [RequestOperationsController::class, 'payments'])->name('payments.index');
        Route::get('/attachments', [CitizenAttachmentController::class, 'employeeIndex'])->name('attachments.index');
        Route::patch('/attachments/{attachment}/review', [CitizenAttachmentController::class, 'review'])->name('attachments.review');

        Route::get('/applications/{id}/review', [ApplicationReviewController::class, 'show'])->name('applications.review');
        Route::post('/applications/{id}/decisions', [ApplicationReviewController::class, 'decide'])->middleware('throttle:30,1')->name('applications.decisions');
        Route::post('/applications/{id}/assignment', [ApplicationReviewController::class, 'assign'])->name('applications.assignment');
        Route::post('/applications/{id}/deficiencies/{deficiency}/resolve', [ApplicationReviewController::class, 'resolve'])->name('applications.deficiencies.resolve');
        Route::post('/applications/{id}/requirements/check', [CitizenDataController::class, 'recheckRequirements'])->name('applications.requirements.check');

        Route::get('/accounts', [CitizenAccountController::class, 'index'])->name('accounts.index');
        Route::get('/accounts/{citizen}', [CitizenAccountController::class, 'show'])->name('accounts.show');
        Route::get('/notifications', [EmployeeNotificationController::class, 'index'])->name('notifications.index');
        Route::post('/notifications/read-all', [EmployeeNotificationController::class, 'readAll'])->name('notifications.read-all');
        Route::post('/notifications/{id}/read', [EmployeeNotificationController::class, 'read'])->whereUuid('id')->name('notifications.read');
        Route::post('/notifications/{id}/open', [EmployeeNotificationController::class, 'open'])->whereUuid('id')->name('notifications.open');
        Route::post('/accounts/{citizen}/confirm', [CitizenAccountController::class, 'confirm'])->middleware('throttle:10,1')->name('accounts.confirm');
        Route::get('/citizens', [CitizenDataController::class, 'search'])->name('citizens.index');
        Route::get('/citizens/{citizen}', [CitizenDataController::class, 'employeeShow'])->name('citizens.show');

        Route::get('/issuance', [DocumentIssuanceController::class, 'index'])->name('issuance.index');
        Route::get('/issuance/{id}', [DocumentIssuanceController::class, 'review'])->name('issuance.review');
        Route::post('/issuance/{id}', [DocumentIssuanceController::class, 'issue'])->middleware('throttle:20,1')->name('issuance.store');
        Route::get('/deliveries', [DocumentIssuanceController::class, 'deliveries'])->name('deliveries.index');
        Route::get('/documents/{id}', [DocumentIssuanceController::class, 'show'])->name('documents.show');
        Route::get('/documents/{id}/download', [DocumentIssuanceController::class, 'download'])->name('documents.download');
        Route::post('/documents/{id}/delivery', [DocumentIssuanceController::class, 'choose'])->name('documents.delivery.choose');
        Route::post('/documents/{id}/delivery-status', [DocumentIssuanceController::class, 'status'])->name('documents.delivery.status');
        Route::post('/documents/{id}/regenerate', [DocumentIssuanceController::class, 'regenerate'])->name('documents.regenerate');

    });

// =====================================================
// صفحات المدير
// =====================================================

Route::middleware(['auth', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        Route::get('/dashboard', [OperationalReportController::class, 'index'])->name('dashboard');

        Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
        Route::patch('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
        Route::get('/review-permissions', [ReviewPermissionController::class, 'index'])->name('review-permissions.index');
        Route::post('/users/{user}/review-permissions', [ReviewPermissionController::class, 'save'])->name('review-permissions.save');

        Route::get('/employees', [EmployeeManagementController::class, 'index'])->name('employees.index');
        Route::get('/employees/create', [EmployeeManagementController::class, 'create'])->name('employees.create');
        Route::post('/employees', [EmployeeManagementController::class, 'store'])->name('employees.store');
        Route::get('/employees/{employee}', [EmployeeManagementController::class, 'show'])->name('employees.show');
        Route::patch('/employees/{employee}', [EmployeeManagementController::class, 'update'])->name('employees.update');
        Route::post('/employees/{employee}/reset-password', [EmployeeManagementController::class, 'resetPassword'])->middleware('throttle:6,1')->name('employees.reset-password');
        Route::get('/departments', [DepartmentController::class, 'index'])->name('departments.index');
        Route::post('/departments', [DepartmentController::class, 'store'])->name('departments.store');
        Route::patch('/departments/{department}', [DepartmentController::class, 'update'])->name('departments.update');

        Route::get('/services', [AdminGovernmentServiceController::class, 'index'])->name('services.index');
        Route::get('/services/create', [AdminGovernmentServiceController::class, 'create'])->name('services.create');
        Route::post('/services', [AdminGovernmentServiceController::class, 'store'])->name('services.store');
        Route::get('/services/{service}/edit', [AdminGovernmentServiceController::class, 'edit'])->name('services.edit');
        Route::patch('/services/{service}', [AdminGovernmentServiceController::class, 'update'])->name('services.update');
        Route::post('/services/{service}/assessments', [AdminGovernmentServiceController::class, 'assessment'])->name('services.assessments.store');

        Route::get('/integrations', [ElectronicVerificationController::class, 'administration'])->name('integrations.index');
        Route::get('/reports', [OperationalReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export', [OperationalReportController::class, 'export'])->middleware('throttle:10,1')->name('reports.export');

        Route::get('/roles', function () {
            return redirect()->route('admin.users.index');
        })->name('roles.index');

        Route::get('/audit-logs', [OperationalReportController::class, 'audit'])->name('audit-logs.index');
        Route::get('/audit-logs/export', [OperationalReportController::class, 'exportAudit'])->middleware('throttle:6,1')->name('audit-logs.export');

        Route::get('/settings', function () {
            return view('admin.settings.index');
        })->name('settings.index');

    });

// نهاية الملف — لا تضف وسم إغلاق PHP
