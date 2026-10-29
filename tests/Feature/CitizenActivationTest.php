<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\CitizenAccountConfirmation;
use App\Models\CitizenDocument;
use App\Models\GovernmentService;
use App\Models\ServiceReviewPermission;
use App\Models\User;
use App\Notifications\CitizenActivity;
use App\Services\Oversight\AuditTrail;
use App\Services\Registration\CitizenAccountActivation;
use App\Services\Tracking\ApplicationTimeline;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\PreparesActivationProof;
use Tests\TestCase;

class CitizenActivationTest extends TestCase
{
    use PreparesActivationProof;

    protected function setUp(): void
    {
        parent::setUp();
        $connection = DB::connection();
        if ($connection->getDatabaseName() !== ':memory:' && ! str_ends_with($connection->getDatabaseName(), '_testing')) {
            throw new \RuntimeException('Isolated testing database required');
        }
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $connection->beginTransaction();
        $this->beforeApplicationDestroyed(function () use ($connection) {
            $connection->rollBack();
            $connection->disconnect();
        });
    }

    private function citizen(string $state = 'identity_verification_pending'): User
    {
        $user = User::factory()->create();
        $user->forceFill(['username' => 'citizen_'.$user->id, 'national_id' => $state === 'initial_account' ? null : (string) (10000000000 + $user->id), 'registration_status' => $state, 'identity_path' => $state === 'initial_account' ? 'no_identity' : 'has_identity', 'phone' => '+967777123456', 'phone_verified_at' => now()])->save();

        return $user;
    }

    private function employee(bool $grant = true): User
    {
        $user = User::factory()->create(['role' => 'employee']);
        if ($grant) {
            (new ServiceReviewPermission)->forceFill(['user_id' => $user->id, 'government_service_id' => GovernmentService::where('code', 'identity-first-issue')->value('id'), 'can_activate' => true, 'granted_by' => $user->id])->save();
        }

        return $user;
    }

    private function proof(User $user, array $overrides = []): array
    {
        $actor = User::where('role', 'employee')->whereHas('reviewPermissions', fn ($q) => $q->where('can_activate', true))->latest('id')->first();
        $visit = $actor ? $this->prepareActivationProof($user, $actor) : ['visit_token' => (string) Str::uuid(), 'otp' => '123456'];

        return array_replace(['action' => 'activate', 'national_id' => $user->national_id, 'identity_confirmed' => true, 'official_issuance_confirmed' => false, 'evidence_reference' => 'TEST-ONLY official in-person reference', 'operation_token' => (string) Str::uuid()] + $visit, $overrides);
    }

    private function completedRequest(User $user, User $employee)
    {
        // Test-only fixture for a workflow completed by the existing issuance/delivery subsystem.
        $request = $user->serviceRequests()->create(['reference' => 'TEST-ONLY-'.Str::uuid(), 'service_code' => 'identity-first-issue', 'status' => 'submitted']);
        DB::table('citizen_service_requests')->where('id', $request->id)->update(['request_type' => 'issue', 'status' => 'completed', 'completed_at' => now(), 'completed_by' => $employee->id]);
        (new CitizenDocument)->forceFill(['user_id' => $user->id, 'government_service_id' => $request->government_service_id, 'citizen_service_request_id' => $request->id, 'document_type' => 'بطاقة شخصية', 'document_type_code' => 'identity', 'document_number' => 'TEST-INTERNAL-'.Str::uuid(), 'status' => 'active', 'issued_at' => today(), 'issued_by' => $employee->id, 'issued_recorded_at' => now()])->save();

        return $request->fresh();
    }

    public function test_employee_dropdown_contains_actions_but_notifications_and_profile_stay_outside(): void
    {
        $staff = $this->employee();
        $html = $this->actingAs($staff)->get('/')->assertOk()->getContent();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($dom);
        $this->assertSame(1, $xpath->query('//*[@id="gov-account-menu"]')->length);
        foreach (['employee.accounts.index', 'employee.citizens.index', 'employee.tracking.index', 'employee.verifications.index'] as $route) {
            $this->assertSame(1, $xpath->query('//*[@id="gov-account-menu"]//a[@href="'.route($route).'"]')->length);
        }
        foreach (['employee.notifications.index', 'profile.edit'] as $route) {
            $this->assertSame(0, $xpath->query('//*[@id="gov-account-menu"]//a[@href="'.route($route).'"]')->length);
            $this->assertSame(1, $xpath->query('//*[@id="gov-navigation"]//a[@href="'.route($route).'"]')->length);
        }
        $staff->reviewPermissions()->update(['can_activate' => false]);
        $this->get('/')->assertDontSee('href="'.route('employee.accounts.index').'"', false);
    }

    public function test_citizen_dropdown_only_shows_own_actions(): void
    {
        $html = $this->actingAs($this->citizen())->get('/')->assertOk()->getContent();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($dom);
        foreach (['citizen.account.status', 'citizen.data', 'citizen.applications.index'] as $route) {
            $this->assertSame(1, $xpath->query('//*[@id="gov-account-menu"]//a[@href="'.route($route).'"]')->length);
        }
        $this->assertSame(0, $xpath->query('//*[@id="gov-account-menu"]//a[@href="'.route('citizen.notifications.index').'"]')->length);
        $this->assertSame(0, $xpath->query('//*[@id="gov-account-menu"]//a[@href="'.route('employee.citizens.index').'"]')->length);
    }

    public function test_staff_notification_page_orders_by_date_and_filters_unread(): void
    {
        $staff = $this->employee();
        $staff->notifyNow(new CitizenActivity('older', ['kind' => 'activation_permission', 'title' => 'Older message', 'body' => 'Old']));
        $older = $staff->notifications()->first();
        $older->forceFill(['created_at' => now()->subDays(2), 'read_at' => now()])->save();
        $staff->notifyNow(new CitizenActivity('newer', ['kind' => 'activation_permission', 'title' => 'Newer message', 'body' => 'New']));
        $this->actingAs($staff)->get('/employee/notifications')->assertOk()->assertSeeInOrder(['Newer message', 'Older message'])->assertSee(now()->subDays(2)->format('Y-m-d'));
        $this->get('/employee/notifications?read=unread')->assertOk()->assertSee('Newer message')->assertDontSee('Older message');
    }

    public function test_citizen_submits_activation_request_once_and_notifies_only_granted_staff(): void
    {
        $citizen = $this->citizen();
        $staff = $this->employee();
        $other = $this->employee(false);
        $this->actingAs($citizen)->post('/citizen/account/activation-request', ['acknowledged' => '1', 'user_id' => $other->id])->assertRedirect(route('citizen.account.status'));
        $this->post('/citizen/account/activation-request', ['acknowledged' => '1'])->assertRedirect();
        $this->assertDatabaseCount('citizen_activation_requests', 1);
        $this->assertDatabaseHas('citizen_activation_requests', ['user_id' => $citizen->id, 'status' => 'pending']);
        $this->assertSame(1, $staff->notifications()->count());
        $this->assertSame(0, $other->notifications()->count());
        $this->assertNull($citizen->fresh()->account_activated_at);
    }

    public function test_activation_request_requires_acknowledgment_and_citizen_role(): void
    {
        $this->actingAs($this->citizen())->post('/citizen/account/activation-request', [])->assertSessionHasErrors('acknowledged');
        $this->actingAs($this->employee(false))->post('/citizen/account/activation-request', ['acknowledged' => '1'])->assertForbidden();
        $this->assertDatabaseCount('citizen_activation_requests', 0);
    }

    public function test_request_approval_updates_queue_and_notifies_citizen_without_new_account(): void
    {
        $citizen = $this->citizen();
        $staff = $this->employee();
        $this->actingAs($citizen)->post('/citizen/account/activation-request', ['acknowledged' => '1'])->assertRedirect();
        $this->actingAs($staff)->post(route('employee.accounts.confirm', $citizen), $this->proof($citizen))->assertRedirect();
        $this->assertDatabaseHas('citizen_activation_requests', ['user_id' => $citizen->id, 'status' => 'approved', 'reviewed_by' => $staff->id]);
        $this->assertTrue($citizen->fresh()->hasActivatedIdentity());
        $this->assertSame(1, $citizen->notifications()->count());
    }

    public function test_employee_inbox_is_owner_scoped_and_open_rechecks_permission(): void
    {
        $citizen = $this->citizen();
        $staff = $this->employee();
        $other = $this->employee(false);
        $this->actingAs($citizen)->post('/citizen/account/activation-request', ['acknowledged' => '1']);
        $notification = $staff->notifications()->firstOrFail();
        $this->actingAs($other)->post('/employee/notifications/'.$notification->id.'/open')->assertNotFound();
        $this->actingAs($staff)->get('/employee/notifications')->assertOk();
        $this->post('/employee/notifications/'.$notification->id.'/open')->assertRedirect(route('employee.accounts.show', $citizen));
        $staff->reviewPermissions()->update(['can_activate' => false]);
        $this->post('/employee/notifications/'.$notification->id.'/open')->assertForbidden();
    }

    public function test_new_activation_grant_notifies_employee_only_on_change(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = $this->employee(false);
        $data = ['government_service_id' => GovernmentService::where('code', 'identity-first-issue')->value('id'), 'can_review' => 0, 'can_approve' => 0, 'can_assign' => 0, 'can_activate' => 1];
        $this->actingAs($admin)->post(route('admin.review-permissions.save', $staff), $data)->assertRedirect();
        $this->post(route('admin.review-permissions.save', $staff), $data)->assertRedirect();
        $this->assertSame(1, $staff->notifications()->count());
        $this->assertSame('activation_permission', $staff->notifications()->first()->data['kind']);
    }

    public function test_partial_activation_keeps_submitted_request_pending(): void
    {
        $citizen = $this->citizen();
        $staff = $this->employee();
        $this->actingAs($citizen)->post('/citizen/account/activation-request', ['acknowledged' => 1]);
        $this->actingAs($staff)->post(route('employee.accounts.confirm', $citizen), $this->proof($citizen, ['identity_confirmed' => false]))->assertRedirect();
        $this->assertDatabaseHas('citizen_activation_requests', ['user_id' => $citizen->id, 'status' => 'pending', 'reviewed_by' => null]);
        $this->assertFalse($citizen->fresh()->hasActivatedIdentity());
    }

    public function test_initial_account_without_identity_and_fully_activated_account_cannot_submit(): void
    {
        $initial = $this->citizen('initial_account');
        $this->actingAs($initial)->post('/citizen/account/activation-request', ['acknowledged' => 1])->assertSessionHasErrors('activation');
        $active = $this->citizen();
        $active->forceFill(['registration_status' => 'active', 'account_activated_at' => now(), 'identity_verified_at' => now()])->save();
        $this->actingAs($active)->post('/citizen/account/activation-request', ['acknowledged' => 1])->assertSessionHasErrors('activation');
        $this->assertDatabaseCount('citizen_activation_requests', 0);
    }

    public function test_submitted_queue_only_contains_requested_accounts_and_status_is_private(): void
    {
        $citizen = $this->citizen();
        $notSubmitted = $this->citizen();
        $staff = $this->employee();
        $this->actingAs($citizen)->post('/citizen/account/activation-request', ['acknowledged' => 1]);
        $this->actingAs($notSubmitted)->get(route('citizen.account.status'))->assertViewHas('activationRequest', null);
        $this->actingAs($staff)->get(route('employee.accounts.index', ['submitted' => 1]))->assertOk()->assertViewHas('citizens', fn ($citizens) => $citizens->count() === 1 && $citizens->first()->id === $citizen->id);
    }

    public function test_national_id_and_username_authenticate_same_pending_account_without_activating_it(): void
    {
        $user = $this->citizen();
        foreach ([$user->national_id, $user->username] as $identifier) {
            $this->post(route('login.post'), ['identifier' => $identifier, 'password' => 'password'])->assertRedirect(route('citizen.account.status'));
            $this->assertAuthenticatedAs($user);
            $this->assertNull($user->fresh()->account_activated_at);
            $this->get(route('citizen.account.status'))->assertOk()->assertSee('الحضور')->assertSee('تفعيل');
            $this->post(route('logout'));
        }
        $this->assertDatabaseCount('users', 1);
    }

    public function test_initial_account_can_login_but_pending_national_id_cannot_start_services(): void
    {
        $initial = $this->citizen('initial_account');
        $this->post(route('login.post'), ['identifier' => $initial->username, 'password' => 'password'])->assertRedirect(route('citizen.dashboard'));
        $this->post(route('logout'));
        $pending = $this->citizen();
        $this->assertFalse($pending->allowsCitizenService(GovernmentService::where('code', 'identity-first-issue')->firstOrFail()));
        $this->actingAs($pending)->post(route('citizen.services.preview', GovernmentService::first()), [])->assertForbidden();
    }

    public function test_authorized_employee_can_activate_and_verify_without_new_account(): void
    {
        $citizen = $this->citizen();
        $employee = $this->employee();
        $password = $citizen->password;
        $this->actingAs($employee)->post(route('employee.accounts.confirm', $citizen), $this->proof($citizen))->assertSessionHasNoErrors()->assertRedirect();
        $current = $citizen->fresh();
        $this->assertSame($citizen->id, $current->id);
        $this->assertSame($password, $current->password);
        $this->assertNotNull($current->account_activated_at);
        $this->assertNotNull($current->identity_verified_at);
        $this->assertSame('active', $current->registration_status);
        $this->assertDatabaseCount('citizen_account_confirmations', 1);
        $this->assertDatabaseHas('audit_events', ['event' => 'citizen_account.confirmed', 'actor_id' => $employee->id]);
        $this->assertSame(1, $current->notifications()->count());
    }

    public function test_activation_without_identity_confirmation_does_not_verify_identity_or_unlock_services(): void
    {
        $citizen = $this->citizen();
        $employee = $this->employee();
        $this->actingAs($employee)->post(route('employee.accounts.confirm', $citizen), $this->proof($citizen, ['identity_confirmed' => false]))->assertSessionHasNoErrors();
        $citizen->refresh();
        $this->assertNotNull($citizen->account_activated_at);
        $this->assertNull($citizen->identity_verified_at);
        $this->assertFalse($citizen->allowsCitizenService(GovernmentService::first()));
    }

    public function test_citizen_and_ungranted_employee_cannot_activate_accounts(): void
    {
        $citizen = $this->citizen();
        $this->actingAs($citizen)->post(route('employee.accounts.confirm', $citizen), $this->proof($citizen))->assertForbidden();
        $this->actingAs($this->employee(false))->post(route('employee.accounts.confirm', $citizen), $this->proof($citizen))->assertForbidden();
        $this->assertNull($citizen->fresh()->account_activated_at);
    }

    public function test_initial_account_upgrade_keeps_credentials_requests_and_is_idempotent(): void
    {
        $citizen = $this->citizen('initial_account');
        $employee = $this->employee();
        $request = $this->completedRequest($citizen, $employee);
        $before = $citizen->only(['id', 'username', 'phone', 'password']);
        $data = $this->proof($citizen, ['action' => 'confirm_issuance', 'national_id' => '9988776655', 'request_id' => $request->id, 'official_issuance_confirmed' => true]);
        $this->actingAs($employee)->post(route('employee.accounts.confirm', $citizen), $data)->assertSessionHasNoErrors();
        $this->post(route('employee.accounts.confirm', $citizen), $data)->assertSessionHasNoErrors();
        $this->assertSame($before, $citizen->fresh()->only(array_keys($before)));
        $this->assertSame('9988776655', $citizen->fresh()->national_id);
        $this->assertSame('completed', $request->fresh()->status);
        $this->assertDatabaseCount('citizen_account_confirmations', 1);
        $this->assertSame(1, $citizen->notifications()->count());
        $this->assertDatabaseCount('users', 2);
    }

    public function test_conflicting_identity_is_not_linked_and_enters_audited_review(): void
    {
        $citizen = $this->citizen('initial_account');
        $other = $this->citizen();
        $employee = $this->employee();
        $request = $this->completedRequest($citizen, $employee);
        $data = $this->proof($citizen, ['action' => 'confirm_issuance', 'national_id' => $other->national_id, 'request_id' => $request->id, 'official_issuance_confirmed' => true]);
        $this->actingAs($employee)->post(route('employee.accounts.confirm', $citizen), $data)->assertSessionHasErrors('national_id');
        $this->assertNull($citizen->fresh()->national_id);
        $this->assertSame('review_required', $citizen->fresh()->registration_status);
        $this->assertDatabaseHas('audit_events', ['event' => 'citizen_account.identity_conflict', 'actor_id' => $employee->id, 'result' => 'conflict']);
        $this->assertSame($other->national_id, $other->fresh()->national_id);
    }

    public function test_issuance_confirmation_requires_completed_owned_request_and_official_proof(): void
    {
        $citizen = $this->citizen('initial_account');
        $employee = $this->employee();
        $this->actingAs($employee)->post(route('employee.accounts.confirm', $citizen), $this->proof($citizen, ['action' => 'confirm_issuance', 'national_id' => '9988776655']))->assertSessionHasErrors();
        $this->assertNull($citizen->fresh()->national_id);
    }

    public function test_ambiguous_login_never_selects_account_using_password(): void
    {
        $first = $this->citizen();
        $other = $this->citizen();
        DB::table('users')->where('id', $other->id)->update(['username' => $first->national_id]);
        $this->post(route('login.post'), ['identifier' => $first->national_id, 'password' => 'password'])->assertSessionHasErrors('identifier');
        $this->assertGuest();
    }

    public function test_changed_account_state_revokes_open_session_then_same_credentials_work(): void
    {
        $citizen = $this->citizen();
        $employee = $this->employee();
        $this->post(route('login.post'), ['identifier' => $citizen->username, 'password' => 'password']);
        app(CitizenAccountActivation::class)->confirm($employee, $citizen, $this->proof($citizen));
        $this->get(route('citizen.account.status'))->assertRedirect(route('login'));
        $this->assertGuest();
        foreach ([$citizen->fresh()->national_id, $citizen->username] as $identifier) {
            $this->post(route('login.post'), ['identifier' => $identifier, 'password' => 'password'])->assertRedirect(route('citizen.dashboard'));
            $this->assertAuthenticatedAs($citizen);
            $this->post(route('logout'));
        }
    }

    public function test_revoked_permission_is_checked_on_every_request(): void
    {
        $citizen = $this->citizen();
        $employee = $this->employee();
        $this->actingAs($employee)->get(route('employee.accounts.index'))->assertOk();
        $employee->reviewPermissions()->first()->forceFill(['can_activate' => false])->save();
        $this->get(route('employee.accounts.show', $citizen))->assertForbidden();
        $this->post(route('employee.accounts.confirm', $citizen), $this->proof($citizen))->assertForbidden();
        $this->assertDatabaseCount('citizen_account_confirmations', 0);
    }

    public function test_disabled_citizen_cannot_be_reenabled_through_activation(): void
    {
        $citizen = $this->citizen();
        $employee = $this->employee();
        $citizen->forceFill(['is_active' => false])->save();
        $this->actingAs($employee)->post(route('employee.accounts.confirm', $citizen), $this->proof($citizen, ['is_active' => true]))->assertForbidden();
        $this->assertFalse($citizen->fresh()->is_active);
    }

    public function test_changed_replay_and_fresh_duplicate_token_cannot_repeat_activation(): void
    {
        $citizen = $this->citizen();
        $employee = $this->employee();
        $data = $this->proof($citizen);
        $this->actingAs($employee)->post(route('employee.accounts.confirm', $citizen), $data)->assertSessionHasNoErrors();
        $this->post(route('employee.accounts.confirm', $citizen), array_replace($data, ['evidence_reference' => 'TEST-ONLY changed reference']))->assertSessionHasErrors('operation_token');
        $this->post(route('employee.accounts.confirm', $citizen), $this->proof($citizen))->assertSessionHasErrors('action');
        $this->assertDatabaseCount('citizen_account_confirmations', 1);
        $this->assertSame(1, $citizen->notifications()->count());
    }

    public function test_reference_is_encrypted_and_sensitive_values_never_enter_audit(): void
    {
        $citizen = $this->citizen();
        $employee = $this->employee();
        $data = $this->proof($citizen);
        $this->actingAs($employee)->post(route('employee.accounts.confirm', $citizen), $data)->assertSessionHasNoErrors();
        $record = CitizenAccountConfirmation::sole();
        $this->assertSame($data['evidence_reference'], $record->evidence_reference);
        $this->assertStringNotContainsString($data['evidence_reference'], DB::table('citizen_account_confirmations')->value('evidence_reference'));
        $audit = DB::table('audit_events')->get()->toJson();
        foreach ([$citizen->national_id, $citizen->phone, $data['evidence_reference'], $data['operation_token']] as $secret) {
            $this->assertStringNotContainsString($secret, $audit);
        }
        $this->assertArrayNotHasKey('evidence_reference', $record->toArray());
        $this->expectException(\LogicException::class);
        $record->forceFill(['result' => 'conflict'])->save();
    }

    public function test_no_activation_with_foreign_or_incomplete_first_issue_request(): void
    {
        $citizen = $this->citizen('initial_account');
        $other = $this->citizen('initial_account');
        $employee = $this->employee();
        $foreign = $this->completedRequest($other, $employee);
        $data = $this->proof($citizen, ['action' => 'confirm_issuance', 'national_id' => '9988776655', 'request_id' => $foreign->id, 'official_issuance_confirmed' => true]);
        $this->actingAs($employee)->post(route('employee.accounts.confirm', $citizen), $data)->assertSessionHasErrors('request_id');
        $own = $this->completedRequest($citizen, $employee);
        DB::table('citizen_service_requests')->where('id', $own->id)->update(['status' => 'processing', 'completed_at' => null]);
        $this->post(route('employee.accounts.confirm', $citizen), array_replace($data, ['request_id' => $own->id]))->assertSessionHasErrors('request_id');
        $this->assertNull($citizen->fresh()->national_id);
        $this->assertDatabaseCount('citizen_account_confirmations', 0);
    }

    public function test_activation_only_can_be_followed_by_separate_identity_verification(): void
    {
        $citizen = $this->citizen();
        $employee = $this->employee();
        $this->actingAs($employee)->post(route('employee.accounts.confirm', $citizen), $this->proof($citizen, ['identity_confirmed' => false]))->assertSessionHasNoErrors();
        $activated = $citizen->fresh()->account_activated_at->toDateTimeString();
        $this->travel(2)->minutes();
        $this->post(route('employee.accounts.confirm', $citizen), $this->proof($citizen))->assertSessionHasNoErrors();
        $this->assertSame($activated, $citizen->fresh()->account_activated_at->toDateTimeString());
        $this->assertTrue($citizen->fresh()->hasActivatedIdentity());
        $this->assertDatabaseCount('citizen_account_confirmations', 2);
    }

    public function test_new_identity_cannot_equal_another_accounts_username(): void
    {
        $citizen = $this->citizen('initial_account');
        $employee = $this->employee();
        User::factory()->create(['username' => '9988776655']);
        $request = $this->completedRequest($citizen, $employee);
        $this->actingAs($employee)->post(route('employee.accounts.confirm', $citizen), $this->proof($citizen, ['action' => 'confirm_issuance', 'national_id' => '9988776655', 'request_id' => $request->id, 'official_issuance_confirmed' => true]))->assertSessionHasErrors('national_id');
        $this->assertNull($citizen->fresh()->national_id);
    }

    public function test_citizen_profile_cannot_set_activation_or_identity_verification(): void
    {
        $citizen = $this->citizen();
        $this->actingAs($citizen)->patch('/profile', ['name' => $citizen->name, 'email' => $citizen->email, 'account_activated_at' => now()->toDateTimeString(), 'identity_verified_at' => now()->toDateTimeString()])->assertSessionHasErrors(['account_activated_at', 'identity_verified_at']);
        $this->assertNull($citizen->fresh()->account_activated_at);
    }

    public function test_admin_must_grant_explicit_activation_permission_on_identity_first_issue_only(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = $this->employee(false);
        $payload = ['government_service_id' => GovernmentService::where('code', 'passport-first-issue')->value('id'), 'can_review' => false, 'can_approve' => false, 'can_assign' => false, 'can_activate' => true];
        $this->actingAs($admin)->post(route('admin.review-permissions.save', $employee), $payload)->assertSessionHasErrors('can_activate');
        $payload['government_service_id'] = GovernmentService::where('code', 'identity-first-issue')->value('id');
        $this->post(route('admin.review-permissions.save', $employee), $payload)->assertSessionHasNoErrors();
        $this->assertTrue($employee->reviewPermissions()->sole()->can_activate);
    }

    public function test_audit_persistence_failure_rolls_back_activation_and_notification(): void
    {
        $citizen = $this->citizen();
        $employee = $this->employee();
        $this->app->instance(AuditTrail::class, new class extends AuditTrail
        {
            public function record(string $event, string $type, int|string|null $id, ?User $actor = null, string $result = 'success', array $changes = []): ?AuditEvent
            {
                if ($event === 'citizen_account.confirmed') {
                    throw new \RuntimeException('TEST-ONLY audit storage failure');
                }

                return parent::record($event, $type, $id, $actor, $result, $changes);
            }
        });
        $this->actingAs($employee)->post(route('employee.accounts.confirm', $citizen), $this->proof($citizen))->assertStatus(500);
        $this->assertNull($citizen->fresh()->account_activated_at);
        $this->assertDatabaseCount('citizen_account_confirmations', 0);
        $this->assertSame(0, $citizen->notifications()->count());
    }

    public function test_account_notification_opens_owned_account_status_page(): void
    {
        $citizen = $this->citizen();
        $employee = $this->employee();
        app(CitizenAccountActivation::class)->confirm($employee, $citizen, $this->proof($citizen));
        $notification = $citizen->notifications()->sole();
        $this->actingAs($citizen->fresh())->post(route('citizen.notifications.open', $notification->id))->assertRedirect(route('citizen.account.status'));
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_official_issuance_confirmation_appears_in_owned_request_timeline(): void
    {
        $citizen = $this->citizen('initial_account');
        $employee = $this->employee();
        $request = $this->completedRequest($citizen, $employee);
        app(CitizenAccountActivation::class)->confirm($employee, $citizen, $this->proof($citizen, ['action' => 'confirm_issuance', 'national_id' => '9988776655', 'request_id' => $request->id, 'official_issuance_confirmed' => true]));
        $timeline = app(ApplicationTimeline::class)->rows($request);
        $this->assertContains('official_identity_confirmed', $timeline->pluck('action')->all());
    }
}
