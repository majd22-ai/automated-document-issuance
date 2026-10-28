<?php

namespace App\Providers;

use App\Contracts\FingerprintConnector;
use App\Contracts\RegistrationSms;
use App\Contracts\ServiceEvidenceSource;
use App\Models\CitizenAppointment;
use App\Models\CitizenAttachment;
use App\Models\CitizenDocument;
use App\Models\CitizenServiceRequest;
use App\Models\Department;
use App\Models\DocumentEvent;
use App\Models\GovernmentService;
use App\Models\IntegrationAttempt;
use App\Models\ManualPayment;
use App\Models\MedicalExamination;
use App\Models\RequestCharge;
use App\Models\ReviewDecision;
use App\Models\ServiceReviewPermission;
use App\Models\User;
use App\Observers\IdentityAttestationObserver;
use App\Observers\OperationalAuditObserver;
use App\Observers\OperationalNotificationObserver;
use App\Services\Fingerprint\LocalFingerprintSimulator;
use App\Services\Fingerprint\UnavailableFingerprintConnector;
use App\Services\FingerprintService;
use App\Services\Notifications\OperationalNotifications;
use App\Services\Oversight\AuditTrail;
use App\Services\Registration\LocalSms;
use App\Services\Registration\UnavailableSms;
use App\Services\Requirements\LocalAttachmentEvidenceSource;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(FingerprintConnector::class, fn () => FingerprintService::enabled() ? new LocalFingerprintSimulator : new UnavailableFingerprintConnector);
        $this->app->scoped(OperationalNotifications::class);
        $this->app->bind(ServiceEvidenceSource::class, LocalAttachmentEvidenceSource::class);
        $this->app->bind(RegistrationSms::class, fn () => LocalSms::enabled() ? new LocalSms : new UnavailableSms);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        CitizenServiceRequest::observe(IdentityAttestationObserver::class);
        foreach ([User::class, Department::class, CitizenServiceRequest::class, ReviewDecision::class, DocumentEvent::class, CitizenDocument::class, ManualPayment::class, RequestCharge::class, IntegrationAttempt::class, ServiceReviewPermission::class, GovernmentService::class, MedicalExamination::class, CitizenAppointment::class, CitizenAttachment::class] as $model) {
            $model::observe(OperationalAuditObserver::class);
            $model::observe(OperationalNotificationObserver::class);
        }
        Event::listen(Login::class, function ($event) {
            if (Schema::hasColumn('users', 'last_login_at')) {
                $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
                if (request()->hasSession()) {
                    request()->session()->put('auth_version', (int) $event->user->auth_version);
                }
            }
            app(AuditTrail::class)->record('auth.login', 'user', $event->user->id, $event->user);
        });
        Event::listen(Logout::class, fn ($event) => app(AuditTrail::class)->record('auth.logout', 'user', $event->user?->id, $event->user));
        Event::listen(Failed::class, fn ($event) => app(AuditTrail::class)->record('auth.failed', 'authentication', null, null, 'failed'));
    }
}
