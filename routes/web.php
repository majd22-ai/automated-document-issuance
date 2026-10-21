<?php

use App\Http\Controllers\AdminGovernmentServiceController;
use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CitizenAttachmentController;
use App\Http\Controllers\CitizenDataController;
use App\Http\Controllers\GovernmentServiceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ServiceRequestController;
use App\Http\Middleware\EnsureCitizenDataIsComplete;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

Route::get('/services/identity/{type}', [GovernmentServiceController::class, 'legacyDetail'])->defaults('documentType', 'identity')->name('services.identity.show');
Route::get('/services/passports/{type}', [GovernmentServiceController::class, 'legacyDetail'])->defaults('documentType', 'passport')->name('services.passports.show');
Route::get('/services/driving-licenses/{type}', [GovernmentServiceController::class, 'legacyDetail'])->defaults('documentType', 'driving_license')->name('services.driving-licenses.show');

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
        Route::get('/services/{service}/attachments', [CitizenAttachmentController::class, 'index'])->name('services.attachments.index');
        Route::post('/services/{service}/attachments', [CitizenAttachmentController::class, 'store'])->middleware('throttle:20,1')->name('services.attachments.store');
        Route::get('/applications/{id}/attachments', [CitizenAttachmentController::class, 'application'])->name('applications.attachments.index');
        Route::post('/applications/{id}/attachments', [CitizenAttachmentController::class, 'storeForApplication'])->middleware('throttle:20,1')->name('applications.attachments.store');
        Route::delete('/attachments/{attachment}', [CitizenAttachmentController::class, 'destroy'])->name('attachments.destroy');
        Route::get('/applications', [CitizenDataController::class, 'applications'])->name('applications.index');

        Route::get('/applications/new', [GovernmentServiceController::class, 'entry'])->middleware(EnsureCitizenDataIsComplete::class)->name('applications.create');
        Route::post('/services/{service}/start', [GovernmentServiceController::class, 'start'])->middleware(EnsureCitizenDataIsComplete::class)->name('services.start');
        Route::post('/services/{service}/preview', [ServiceRequestController::class, 'preview'])->middleware(EnsureCitizenDataIsComplete::class)->name('services.preview');
        Route::get('/applications/preview/{token}', [ServiceRequestController::class, 'review'])->whereUuid('token')->name('applications.preview');
        Route::post('/services/{service}/submit', [ServiceRequestController::class, 'submit'])->middleware(EnsureCitizenDataIsComplete::class)->name('services.submit');
        Route::get('/applications/{id}/success', [ServiceRequestController::class, 'success'])->name('applications.success');

        Route::get('/applications/{id}', [CitizenDataController::class, 'application'])->name('applications.show');
        Route::post('/applications/{id}/requirements/check', [CitizenDataController::class, 'recheckRequirements'])->name('applications.requirements.check');

        Route::get('/appointments', function () {
            return view('citizen.appointments.index');
        })->name('appointments.index');

        Route::get('/documents', [CitizenDataController::class, 'documents'])->name('documents.index');

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

        Route::get('/applications', [CitizenDataController::class, 'employeeApplications'])->name('applications.index');
        Route::get('/attachments', [CitizenAttachmentController::class, 'employeeIndex'])->name('attachments.index');
        Route::patch('/attachments/{attachment}/review', [CitizenAttachmentController::class, 'review'])->name('attachments.review');

        Route::get('/applications/{id}/review', [CitizenDataController::class, 'employeeApplication'])->name('applications.review');
        Route::post('/applications/{id}/requirements/check', [CitizenDataController::class, 'recheckRequirements'])->name('applications.requirements.check');

        Route::get('/citizens', [CitizenDataController::class, 'search'])->name('citizens.index');
        Route::get('/citizens/{citizen}', [CitizenDataController::class, 'employeeShow'])->name('citizens.show');

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

        Route::get('/services', [AdminGovernmentServiceController::class, 'index'])->name('services.index');
        Route::get('/services/create', [AdminGovernmentServiceController::class, 'create'])->name('services.create');
        Route::post('/services', [AdminGovernmentServiceController::class, 'store'])->name('services.store');
        Route::get('/services/{service}/edit', [AdminGovernmentServiceController::class, 'edit'])->name('services.edit');
        Route::patch('/services/{service}', [AdminGovernmentServiceController::class, 'update'])->name('services.update');
        Route::post('/services/{service}/assessments', [AdminGovernmentServiceController::class, 'assessment'])->name('services.assessments.store');

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
