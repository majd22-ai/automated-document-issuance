<?php

namespace Tests\Feature;

use App\Enums\ServiceRequestStatus;
use App\Jobs\SendCitizenNotificationMail;
use App\Mail\CitizenUpdateMail;
use App\Models\GovernmentService;
use App\Models\ServiceReviewPermission;
use App\Models\User;
use App\Notifications\CitizenActivity;
use App\Services\Issuance\IssueDocuments;
use App\Services\Issuance\ManageDocuments;
use App\Services\Notifications\CitizenUpdates;
use App\Services\Notifications\NotificationMail;
use App\Services\ServiceRequestSubmission;
use App\Services\Tracking\ApplicationTimeline;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class TrackingNotificationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $db = DB::connection();
        if ($db->getDatabaseName() !== ':memory:' && ! str_ends_with($db->getDatabaseName(), '_testing')) {
            throw new \RuntimeException('Isolated testing DB required');
        }
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $db->beginTransaction();
        $this->beforeApplicationDestroyed(function () use ($db) {
            $db->rollBack();
            $db->disconnect();
            $this->travelBack();
        });
        config(['citizen_notifications.mail_enabled' => false, 'citizen_notifications.expiry_offsets' => []]);
        Mail::fake();
    }

    private function user(string $role = 'citizen'): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role, 'username' => 'TRACK-'.$user->id])->save();

        return $user;
    }

    private function fixtures(): array
    {
        $citizen = $this->user();
        $employee = $this->user('employee');
        $admin = $this->user('admin');
        $service = GovernmentService::where('code', 'identity-first-issue')->firstOrFail();
        $service->update(['is_active' => true, 'rules_approved' => true, 'source_reference' => 'TEST ONLY', 'issuance_rules' => ['enabled' => true, 'source_reference' => 'TEST ONLY', 'expiry' => ['required' => true, 'validity_days' => 60], 'include_year' => true, 'electronic_copy' => 'none', 'citizen_download' => false, 'delivery' => ['methods' => ['pickup'], 'centers' => ['TEST center']], 'completion_stage' => 'delivery']]);
        $service->assessments()->create(['user_id' => $citizen->id, 'rules_version' => $service->rules_version, 'identity_verified' => true, 'conditions_met' => true, 'verification_reference' => 'TEST ONLY', 'reviewed_by' => $admin->id, 'reviewed_at' => now()]);
        (new ServiceReviewPermission)->forceFill(['user_id' => $employee->id, 'government_service_id' => $service->id, 'can_review' => true, 'can_approve' => true, 'can_issue' => true, 'can_deliver' => true, 'granted_by' => $admin->id])->save();
        $submit = app(ServiceRequestSubmission::class);
        $preview = $submit->preview($citizen, $service, []);
        $request = $submit->submit($citizen, $service, (string) Str::uuid(), $preview);

        return [$citizen, $employee, $service, $request];
    }

    private function document(User $citizen, int $days = 30, string $status = 'active')
    {
        return $citizen->documents()->create(['document_type' => 'TEST identity', 'document_type_code' => 'identity', 'document_number' => 'TRACK-DOC-'.Str::uuid(), 'status' => $status, 'issued_at' => today()->subYear(), 'expires_at' => today()->addDays($days)]);
    }

    public function test_real_submission_creates_one_native_notification_and_replay_does_not_duplicate(): void
    {
        [$citizen,$employee,$service,$request] = $this->fixtures();
        $this->assertSame(1, $citizen->notifications()->count());
        $note = $citizen->notifications()->first();
        $this->assertSame('submitted', $note->data['kind']);
        app(ServiceRequestSubmission::class)->submit($citizen, $service, $request->submission_token, null);
        $this->assertSame(1, $citizen->notifications()->count());
        $this->actingAs($citizen)->get('/citizen/notifications')->assertOk()->assertSee('تم تقديم طلبك');
    }

    public function test_public_return_and_reject_notifications_never_include_internal_notes(): void
    {
        [$citizen,$employee,$service,$request] = $this->fixtures();
        $request = $request->transitionTo(ServiceRequestStatus::UnderReview, $employee);
        $request = $request->transitionTo(ServiceRequestStatus::RequiresCorrection, $employee, 'TEST public correction', ['internal_notes' => 'SECRET INTERNAL NOTE', 'deficiencies' => [['target_type' => 'other', 'target_key' => 'test', 'description' => 'TEST public deficiency']]]);
        $this->actingAs($citizen)->get('/citizen/notifications')->assertSee('TEST public correction')->assertDontSee('SECRET INTERNAL NOTE');
        $this->assertStringNotContainsString('SECRET INTERNAL NOTE', json_encode($citizen->notifications()->get()->toArray()));
        // Independent request to exercise real rejection path after terminal state of this TEST fixture.
        DB::table('citizen_service_requests')->where('id', $request->id)->update(['status' => 'under_review']);
        $request->fresh()->transitionTo(ServiceRequestStatus::Rejected, $employee, 'TEST public refusal', ['internal_notes' => 'PRIVATE refusal']);
        $this->get('/citizen/notifications')->assertSee('TEST public refusal')->assertDontSee('PRIVATE refusal');
    }

    public function test_read_and_open_endpoints_are_owner_scoped_and_do_not_trust_urls(): void
    {
        [$citizen,$employee,$service,$request] = $this->fixtures();
        $note = $citizen->notifications()->first();
        $this->actingAs($this->user())->post('/citizen/notifications/'.$note->id.'/read')->assertNotFound();
        $this->post('/citizen/notifications/'.$note->id.'/open')->assertNotFound();
        $this->actingAs($citizen)->post('/citizen/notifications/'.$note->id.'/open', ['url' => 'https://evil.invalid'])->assertRedirect('/citizen/applications/'.$request->id);
        $this->assertNotNull($note->fresh()->read_at);
    }

    public function test_mark_all_read_does_not_touch_another_citizen(): void
    {
        [$citizen,$employee,$service,$request] = $this->fixtures();
        $other = $this->user();
        $doc = $this->document($other);
        config(['citizen_notifications.expiry_offsets' => [30]]);
        $this->artisan('documents:notify-expiry')->assertSuccessful();
        $this->actingAs($citizen)->post('/citizen/notifications/read-all')->assertRedirect();
        $this->assertSame(0, $citizen->unreadNotifications()->count());
        $this->assertSame(1, $other->unreadNotifications()->count());
    }

    public function test_timeline_is_chronological_private_and_employee_tracking_is_scoped(): void
    {
        [$citizen,$employee,$service,$request] = $this->fixtures();
        $request = $request->transitionTo(ServiceRequestStatus::UnderReview, $employee);
        $request = $request->transitionTo(ServiceRequestStatus::Accepted, $employee, null, ['internal_notes' => 'SECRET TIMELINE']);
        $this->actingAs($citizen)->get('/citizen/applications/'.$request->id)->assertOk()->assertSee('الخط الزمني')->assertDontSee('SECRET TIMELINE');
        $rows = app(ApplicationTimeline::class)->rows($request);
        $this->assertSame(['submitted', 'start', 'accept'], collect($rows->items())->pluck('action')->all());
        $this->actingAs($employee)->get('/employee/tracking/'.$request->id)->assertOk();
        $this->actingAs($this->user('employee'))->get('/employee/tracking/'.$request->id)->assertForbidden();
        $this->get('/employee/tracking')->assertOk()->assertDontSee($request->reference);
    }

    public function test_expiry_thresholds_are_configured_deduplicated_and_expired_policy_is_explicit(): void
    {
        $citizen = $this->user();
        $doc = $this->document($citizen, 30);
        config(['citizen_notifications.expiry_offsets' => [30, 7]]);
        $this->artisan('documents:notify-expiry')->assertSuccessful();
        $this->artisan('documents:notify-expiry')->assertSuccessful();
        $this->assertSame(1, $citizen->notifications()->count());
        $this->travel(23)->days();
        $this->artisan('documents:notify-expiry')->assertSuccessful();
        $this->assertSame(2, $citizen->notifications()->count());
        $this->travel(8)->days();
        $this->artisan('documents:notify-expiry')->assertSuccessful();
        $this->assertSame(2, $citizen->notifications()->count());
        config(['citizen_notifications.expired_policy' => 'once']);
        $this->artisan('documents:notify-expiry')->assertSuccessful();
        $this->artisan('documents:notify-expiry')->assertSuccessful();
        $this->assertSame(3, $citizen->notifications()->count());
    }

    public function test_expiry_ignores_superseded_cancelled_missing_date_and_inactive_owners(): void
    {
        $citizen = $this->user();
        $this->document($citizen, 7, 'superseded');
        $this->document($citizen, 7, 'cancelled');
        $this->document($citizen, 7)->update(['expires_at' => null]);
        $inactive = $this->user();
        $inactive->forceFill(['is_active' => false])->save();
        $this->document($inactive, 7);
        config(['citizen_notifications.expiry_offsets' => [30, 7], 'citizen_notifications.expiry_statuses' => ['active', 'superseded', 'cancelled']]);
        $this->artisan('documents:notify-expiry')->assertSuccessful();
        $this->assertSame(0, DB::table('notifications')->count());
    }

    public function test_mail_failure_preserves_inbox_and_duplicate_job_never_sends_again(): void
    {
        config(['citizen_notifications.mail_enabled' => true, 'mail.default' => 'smtp']);
        [$citizen,$employee,$service,$request] = $this->fixtures();
        $note = $citizen->notifications()->first();
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('SECRET SMTP MESSAGE'));
        app(NotificationMail::class)->send($note->id);
        $this->assertSame(1, $citizen->notifications()->count());
        $this->assertSame('failed', $note->fresh()->mail_status);
        app(NotificationMail::class)->send($note->id);
        $this->assertSame(1, $note->fresh()->mail_attempts);
    }

    public function test_document_issuance_ready_and_delivery_events_notify_and_replays_do_not(): void
    {
        [$citizen,$employee,$service,$request] = $this->fixtures();
        foreach ([ServiceRequestStatus::UnderReview, ServiceRequestStatus::Accepted, ServiceRequestStatus::Approved] as $state) {
            $request = $request->transitionTo($state, $employee);
        }
        $token = (string) Str::uuid();
        $issue = app(IssueDocuments::class);
        $document = $issue->issue($employee, $request, ['operation_token' => $token]);
        $issue->issue($employee, $request, ['operation_token' => $token]);
        $manager = app(ManageDocuments::class);
        $choice = ['operation_token' => (string) Str::uuid(), 'expected_version' => 0, 'method' => 'pickup', 'center' => 'TEST center'];
        $manager->choose($citizen, $document, $choice);
        $manager->choose($citizen, $document, $choice);
        $manager->status($employee, $document, ['operation_token' => (string) Str::uuid(), 'expected_version' => 1, 'status' => 'delivered', 'proof_reference' => 'PRIVATE HANDOVER PROOF']);
        $kinds = $citizen->notifications()->get()->pluck('data.kind')->all();
        foreach (['issued', 'delivery_ready', 'delivery_delivered', 'completed'] as $kind) {
            $this->assertSame(1, count(array_filter($kinds, fn ($value) => $value === $kind)));
        }
        $this->assertStringNotContainsString('PRIVATE HANDOVER PROOF', json_encode($citizen->notifications()->get()->toArray()));
        $rows = app(ApplicationTimeline::class)->rows($request->fresh());
        $this->assertContains('issued', collect($rows->items())->pluck('action')->all());
    }

    public function test_event_reprocessing_deduplicates_but_new_decisions_are_not_suppressed(): void
    {
        [$citizen,$employee,$service,$request] = $this->fixtures();
        $request = $request->transitionTo(ServiceRequestStatus::UnderReview, $employee);
        $event = $request->reviewDecisions()->latest('id')->first();
        $count = $citizen->notifications()->count();
        app(CitizenUpdates::class)->review($event);
        $this->assertSame($count, $citizen->notifications()->count());
        $request->transitionTo(ServiceRequestStatus::Accepted, $employee);
        $this->assertSame($count + 1, $citizen->notifications()->count());
    }

    public function test_mail_is_generic_verified_and_sent_once_through_fake_transport(): void
    {
        config(['citizen_notifications.mail_enabled' => true, 'mail.default' => 'smtp']);
        [$citizen,$employee,$service,$request] = $this->fixtures();
        $note = $citizen->notifications()->first();
        $delivery = app(NotificationMail::class);
        $delivery->send($note->id);
        $delivery->send($note->id);
        Mail::assertSent(CitizenUpdateMail::class, 1);
        Mail::assertSent(CitizenUpdateMail::class, function ($mail) use ($citizen) {
            $html = $mail->render();
            $this->assertStringNotContainsString($citizen->name, $html);

            return $mail->hasTo($citizen->email);
        });
        $this->assertSame('sent', $note->fresh()->mail_status);
        $this->assertSame(1, $note->fresh()->mail_attempts);
    }

    public function test_changed_or_unverified_email_and_log_driver_never_send(): void
    {
        config(['citizen_notifications.mail_enabled' => true, 'mail.default' => 'smtp']);
        [$citizen,$employee,$service,$request] = $this->fixtures();
        $note = $citizen->notifications()->first();
        $citizen->forceFill(['email' => 'changed@example.test', 'email_verified_at' => null])->save();
        app(NotificationMail::class)->send($note->id);
        $this->assertSame('skipped', $note->fresh()->mail_status);
        Mail::assertNothingSent();
        $request = $request->transitionTo(ServiceRequestStatus::UnderReview, $employee);
        $request->transitionTo(ServiceRequestStatus::Rejected, $employee, 'TEST refusal');
        $this->assertSame('disabled', $citizen->notifications()->where('data->kind', 'reject')->first()->mail_status);
        config(['mail.default' => 'log']);
        $this->assertFalse(app(NotificationMail::class)->configured());
    }

    public function test_async_dispatch_is_idempotent_and_failed_mail_requires_explicit_bounded_retry(): void
    {
        config(['citizen_notifications.mail_enabled' => true, 'mail.default' => 'smtp', 'queue.default' => 'database']);
        Bus::fake();
        [$citizen,$employee,$service,$request] = $this->fixtures();
        $note = $citizen->notifications()->first();
        $delivery = app(NotificationMail::class);
        $delivery->enqueue($note->id);
        $delivery->enqueue($note->id);
        Bus::assertDispatched(SendCitizenNotificationMail::class, 1);
        $this->assertSame('queued', $note->fresh()->mail_status);
        DB::table('notifications')->where('id', $note->id)->update(['mail_status' => 'failed', 'mail_attempts' => 1]);
        $this->assertTrue($delivery->retry($note->id));
        $this->assertFalse($delivery->retry($note->id));
        DB::table('notifications')->where('id', $note->id)->update(['mail_status' => 'failed', 'mail_attempts' => 3]);
        $this->assertFalse($delivery->retry($note->id));
    }

    public function test_inbox_is_ordered_by_actual_date_even_with_reverse_uuid_order(): void
    {
        [$citizen,$employee,$service,$request] = $this->fixtures();
        $old = $citizen->notifications()->first();
        DB::table('notifications')->where('id', $old->id)->update(['created_at' => now()->subDay()]);
        $new = $citizen->notifications()->create(['id' => '00000000-0000-4000-8000-000000000001', 'type' => CitizenActivity::class, 'data' => ['title' => 'NEWEST TEST', 'body' => 'TEST newest'], 'user_id' => $citizen->id]);
        $this->actingAs($citizen)->get('/citizen/notifications')->assertViewHas('notifications', fn ($rows) => $rows->first()->id === $new->id);
    }

    public function test_tracking_filters_dates_services_and_pagination_use_only_owned_rows(): void
    {
        [$citizen,$employee,$service,$request] = $this->fixtures();
        $url = '/citizen/applications?service='.$service->id.'&submitted_from='.today()->toDateString().'&submitted_to='.today()->toDateString().'&updated_from='.today()->toDateString();
        $this->actingAs($citizen)->get($url)->assertOk()->assertSee($request->reference);
        $this->get('/citizen/applications?submitted_to='.today()->subDay()->toDateString())->assertOk()->assertDontSee($request->reference);
        for ($i = 0; $i < 16; $i++) {
            $citizen->serviceRequests()->create(['reference' => 'TRACK-PAGE-'.Str::uuid(), 'service_code' => $service->code, 'status' => 'submitted']);
        }
        $this->get('/citizen/applications')->assertViewHas('applications', fn ($rows) => $rows->count() === 15 && $rows->total() === 17);
        $this->actingAs($this->user())->get('/citizen/applications/'.$request->id)->assertNotFound();
    }

    public function test_legacy_request_does_not_acquire_an_invented_submission_date_or_writable_history(): void
    {
        [$citizen,$employee,$service,$request] = $this->fixtures();
        $legacy = $citizen->serviceRequests()->create(['reference' => 'LEGACY-'.Str::uuid(), 'service_code' => $service->code, 'status' => 'submitted']);
        $this->assertSame(0, app(ApplicationTimeline::class)->rows($legacy)->total());
        $this->actingAs($citizen)->get('/citizen/applications/'.$legacy->id)->assertSee('لا توجد مراحل مؤرخة');
        $this->patch('/citizen/applications/'.$request->id, ['status' => 'completed'])->assertStatus(405);
        $this->post('/citizen/notifications', ['kind' => 'approved', 'body' => 'FORGED'])->assertStatus(405);
    }

    public function test_expiry_uses_closest_threshold_no_backfill_and_new_date_has_distinct_key(): void
    {
        $citizen = $this->user();
        $doc = $this->document($citizen, 6);
        config(['citizen_notifications.expiry_offsets' => [30, 7, 1]]);
        $this->artisan('documents:notify-expiry')->assertSuccessful();
        $this->assertSame(1, $citizen->notifications()->count());
        $doc->update(['expires_at' => today()->addDays(5)]);
        $this->artisan('documents:notify-expiry')->assertSuccessful();
        $this->assertSame(2, $citizen->notifications()->count());
        $doc->update(['status' => 'superseded']);
        $this->travel(4)->days();
        $this->artisan('documents:notify-expiry')->assertSuccessful();
        $this->assertSame(2, $citizen->notifications()->count());
    }

    public function test_expiry_with_no_periods_and_invalid_configuration_is_explicit(): void
    {
        $citizen = $this->user();
        $this->document($citizen, 10);
        $this->artisan('documents:notify-expiry')->assertSuccessful();
        $this->assertSame(0, $citizen->notifications()->count());
        config(['citizen_notifications.expiry_offsets' => ['nonsense']]);
        $this->artisan('documents:notify-expiry')->assertFailed();
    }

    public function test_notification_foreign_references_are_reauthorized_before_opening(): void
    {
        [$citizen,$employee,$service,$request] = $this->fixtures();
        $note = $citizen->notifications()->first();
        $other = $this->user();
        $doc = $this->document($other);
        DB::table('notifications')->where('id', $note->id)->update(['document_id' => $doc->id]);
        $this->actingAs($citizen)->post('/citizen/notifications/'.$note->id.'/open')->assertNotFound();
        $this->assertNull($note->fresh()->read_at);
    }

    public function test_business_rollback_also_rolls_back_inbox_and_prior_history_remains(): void
    {
        [$citizen,$employee,$service,$request] = $this->fixtures();
        $count = $citizen->notifications()->count();
        try {
            DB::transaction(function () use ($employee, $request) {
                $request->transitionTo(ServiceRequestStatus::UnderReview, $employee);
                throw new \RuntimeException('TEST rollback');
            });
        } catch (\RuntimeException) {
        }
        $this->assertSame('submitted', $request->fresh()->status);
        $this->assertSame(0, $request->reviewDecisions()->count());
        $this->assertSame($count, $citizen->notifications()->count());
    }
}
