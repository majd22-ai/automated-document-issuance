<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\CitizenDocument;
use App\Models\CitizenServiceRequest;
use App\Models\Department;
use App\Models\GovernmentService;
use App\Models\ManualPayment;
use App\Models\RequestCharge;
use App\Models\ReviewDecision;
use App\Models\User;
use App\Services\Oversight\AuditTrail;
use App\Services\Oversight\OperationalReports;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Tests\TestCase;

class EmployeeReportingTest extends TestCase
{
    public function test_description_only_department_change_is_audited_without_its_text(): void
    {
        $a = $this->admin();
        $d = $this->department();
        $before = AuditEvent::where('event', 'department.updated')->count();
        $this->actingAs($a)->patch('/admin/departments/'.$d->id, ['name' => $d->name, 'description' => 'Private department explanation', 'is_active' => 1])->assertRedirect();
        $this->assertSame($before + 1, AuditEvent::where('event', 'department.updated')->count());
        $event = AuditEvent::where('event', 'department.updated')->latest('id')->firstOrFail();
        $this->assertContains('description', $event->changes['changed_fields']);
        $this->assertStringNotContainsString('Private department explanation', json_encode($event));
    }

    public function test_scoped_charge_period_uses_charge_date_not_request_creation_date(): void
    {
        $a = $this->admin();
        $d = $this->department();
        $e = User::factory()->create(['role' => 'employee', 'department_id' => $d->id]);
        $c = User::factory()->create();
        $r = $this->requestFor($c, $e, 'submitted', '2026-09-01');
        (new RequestCharge)->forceFill(['user_id' => $c->id, 'government_service_id' => $r->government_service_id, 'citizen_service_request_id' => $r->id, 'amount' => '50.00', 'currency' => 'YER', 'status' => 'due', 'created_at' => '2026-10-20'])->save();
        $report = app(OperationalReports::class)->build($a, $this->period() + ['department_id' => $d->id]);
        $this->assertSame(1, collect($report['charge_statuses'])->firstWhere('key', 'due')['total'] ?? 0);
    }

    public function test_legacy_sessions_without_credential_version_require_reauthentication(): void
    {
        $e = User::factory()->create(['role' => 'employee']);
        $this->withSession([Auth::guard()->getName() => $e->id])->get('/employee/dashboard')->assertRedirect('/login');
    }

    public function test_staff_completion_metrics_respect_service_filter(): void
    {
        $a = $this->admin();
        $e = User::factory()->create(['role' => 'employee']);
        $c = User::factory()->create();
        $r = $this->requestFor($c, $e, 'completed');
        DB::table('citizen_service_requests')->where('id', $r->id)->update(['completed_by' => $e->id, 'completed_at' => '2026-10-21 12:00:00']);
        $other = GovernmentService::where('id', '!=', $r->government_service_id)->firstOrFail();
        $report = app(OperationalReports::class)->build($a, $this->period() + ['service_id' => $other->id]);
        $this->assertSame([], $report['employees']);
    }

    public function test_sensitive_audit_csv_is_admin_only_filtered_and_redacted(): void
    {
        $a = $this->admin();
        app(AuditTrail::class)->record('export.allowed', 'user', $a->id, $a, 'success', ['password' => 'never-show']);
        app(AuditTrail::class)->record('export.excluded', 'user', $a->id, $a);
        $response = $this->actingAs($a)->get('/admin/audit-logs/export?event=export.allowed');
        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString('export.allowed', $csv);
        $this->assertStringNotContainsString('export.excluded', $csv);
        $this->assertStringNotContainsString('never-show', $csv);
        $this->actingAs(User::factory()->create(['role' => 'employee', 'can_export_reports' => true]))->get('/admin/audit-logs/export')->assertForbidden();
    }

    public function test_paginated_activity_exports_every_matching_employee(): void
    {
        $a = $this->admin();
        $c = User::factory()->create();
        $last = null;
        for ($i = 0; $i < 23; $i++) {
            $last = User::factory()->create(['role' => 'employee']);
            $this->requestFor($c, $last);
        }
        $report = app(OperationalReports::class)->build($a, $this->period());
        $this->assertCount(20, $report['employees']);
        $response = $this->actingAs($a)->get('/admin/reports/export?'.http_build_query($this->period() + ['report' => 'employees']));
        $response->assertOk();
        $this->assertStringContainsString("\n".$last->id.',0,0,0,1,0,', $response->streamedContent());
    }

    public function test_document_and_verification_reports_separate_legacy_and_simulation(): void
    {
        $a = $this->admin();
        $e = User::factory()->create(['role' => 'employee']);
        $c = User::factory()->create();
        $r = $this->requestFor($c, $e);
        (new CitizenDocument)->forceFill(['user_id' => $c->id, 'government_service_id' => $r->government_service_id, 'citizen_service_request_id' => $r->id, 'document_type' => 'هوية', 'document_number' => 'RPT-DOC-1', 'status' => 'active', 'issued_by' => $e->id, 'issued_recorded_at' => '2026-10-20 12:00:00'])->save();
        (new CitizenDocument)->forceFill(['user_id' => $c->id, 'document_type' => 'وثيقة قديمة', 'document_number' => 'RPT-LEGACY', 'status' => 'active'])->save();
        foreach (['simulation', 'configured_provider', 'unknown'] as $origin) {
            DB::table('integration_attempts')->insert(['id' => (string) Str::uuid(), 'citizen_service_request_id' => $r->id, 'user_id' => $c->id, 'actor_id' => $e->id, 'kind' => 'identity', 'provider' => 'fixture-only', 'status' => 'verified', 'origin' => $origin, 'idempotency_hash' => hash('sha256', $origin), 'input_fingerprint' => str_repeat('a', 64), 'started_at' => '2026-10-20 12:00:00', 'finished_at' => '2026-10-20 12:00:01', 'attempts' => 1, 'created_at' => '2026-10-20 12:00:00', 'updated_at' => '2026-10-20 12:00:01']);
        }
        $report = app(OperationalReports::class)->build($a, $this->period());
        $this->assertSame(1, $report['documents']);
        $this->assertSame(3, $report['verification_total']);
        $this->assertSame(33.33, $report['verification_rate']);
        $this->assertCount(3, $report['verification_origins']);
    }

    public function test_date_presets_and_zero_denominators_are_defined(): void
    {
        $a = $this->admin();
        $reports = app(OperationalReports::class);
        $this->travelTo(Carbon::parse('2026-10-25 12:00:00', config('app.timezone')));
        foreach (['today' => '2026-10-25', '7days' => '2026-10-19', '30days' => '2026-09-26', 'month' => '2026-10-01'] as $period => $start) {
            $r = $reports->build($a, ['period' => $period]);
            $this->assertSame($start, $r['filters']['from_at']->toDateString());
            $this->assertNull($r['completion_rate']);
            $this->assertNull($r['verification_rate']);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $db = DB::connection();
        if ($db->getDatabaseName() !== ':memory:' && ! str_ends_with($db->getDatabaseName(), '_testing')) {
            throw new \RuntimeException('Isolated test DB required');
        }
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $db->beginTransaction();
        $this->beforeApplicationDestroyed(function () use ($db) {
            $db->rollBack();
            $db->disconnect();
        });
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function payload(): array
    {
        return ['name' => 'موظف جديد', 'username' => 'employee-100', 'email' => 'employee100@example.test', 'password' => 'Strong-password-1122', 'password_confirmation' => 'Strong-password-1122', 'role' => 'employee', 'is_active' => 1, 'report_scope' => 'none', 'can_export_reports' => 0];
    }

    public function test_admin_creates_employee_with_hashed_password_and_safe_audit(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/admin/employees', $this->payload())->assertRedirect();
        $employee = User::where('username', 'employee-100')->firstOrFail();
        $this->assertSame('employee', $employee->role);
        $this->assertTrue(Hash::check('Strong-password-1122', $employee->password));
        $event = DB::table('audit_events')->where('subject_type', 'user')->where('subject_id', (string) $employee->id)->first();
        $this->assertSame($admin->id, $event->actor_id);
        $this->assertStringNotContainsString('Strong-password', json_encode($event));
        $this->assertStringNotContainsString('employee100@example', json_encode($event));
    }

    public function test_duplicate_accounts_and_self_privilege_escalation_are_rejected(): void
    {
        $employee = User::factory()->create(['role' => 'employee', 'username' => 'employee-100']);
        $this->actingAs($employee)->post('/admin/employees', $this->payload())->assertForbidden();
        $this->patch('/admin/employees/'.$employee->id, ['report_scope' => 'department'])->assertForbidden();
        $this->actingAs($this->admin())->post('/admin/employees', $this->payload())->assertSessionHasErrors('username');
    }

    public function test_reports_and_audit_are_not_available_to_ordinary_users(): void
    {
        $this->get('/admin/reports')->assertRedirect('/login');
        foreach (['citizen', 'employee'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get('/admin/reports')->assertForbidden();
            $this->get('/admin/audit-logs')->assertForbidden();
            $this->get('/employee/reports')->assertForbidden();
        }
    }

    public function test_empty_real_reports_and_csv_are_available_to_admin(): void
    {
        $this->actingAs($this->admin())->get('/admin/reports')->assertOk()->assertSee('تعريف المؤشرات');
        $this->get('/admin/reports/export?report=requests')->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    private function department(string $name = 'قسم الاختبار'): Department
    {
        $d = (new Department)->forceFill(['name' => $name, 'is_active' => true]);
        $d->save();

        return $d;
    }

    private function requestFor(User $citizen, User $employee, string $status = 'submitted', string $date = '2026-10-20 12:00:00'): CitizenServiceRequest
    {
        $service = GovernmentService::firstOrFail();
        $r = (new CitizenServiceRequest)->forceFill(['user_id' => $citizen->id, 'government_service_id' => $service->id, 'service_code' => $service->code, 'reference' => 'REPORT-'.Str::uuid(), 'assigned_to' => $employee->id, 'status' => $status, 'created_at' => $date, 'updated_at' => $date]);
        $r->save();

        return $r;
    }

    private function period(): array
    {
        return ['period' => 'custom', 'from' => '2026-10-01', 'to' => '2026-10-31'];
    }

    public function test_employee_update_disable_role_and_department_are_audited_without_passwords(): void
    {
        $admin = $this->admin();
        $d = $this->department();
        $e = User::factory()->create(['role' => 'employee']);
        $data = $this->payload();
        unset($data['password'],$data['password_confirmation']);
        $data['department_id'] = $d->id;
        $data['is_active'] = 0;
        $data['report_scope'] = 'department';
        $this->actingAs($admin)->patch('/admin/employees/'.$e->id, $data)->assertRedirect();
        $this->assertFalse($e->fresh()->is_active);
        $this->assertSame($d->id, $e->fresh()->department_id);
        $this->assertDatabaseHas('audit_events', ['subject_type' => 'user', 'subject_id' => (string) $e->id, 'event' => 'user.updated', 'actor_id' => $admin->id]);
        $this->assertSame(1, $e->fresh()->auth_version);
        $data['is_active'] = 1;
        $this->patch('/admin/employees/'.$e->id, $data)->assertRedirect();
        $this->assertTrue($e->fresh()->is_active);
        $this->delete('/admin/employees/'.$e->id)->assertStatus(405);
    }

    public function test_admin_cannot_disable_or_demote_their_own_account(): void
    {
        $admin = $this->admin();
        $data = $this->payload();
        unset($data['password'],$data['password_confirmation']);
        $data['role'] = 'employee';
        $this->actingAs($admin)->patch('/admin/employees/'.$admin->id, $data)->assertSessionHasErrors('role');
        $this->assertSame('admin', $admin->fresh()->role);
    }

    public function test_department_reports_cannot_escape_scope_even_through_filters_or_export(): void
    {
        $d = $this->department();
        $other = $this->department('قسم آخر');
        $e = User::factory()->create(['role' => 'employee', 'department_id' => $d->id, 'report_scope' => 'department']);
        $outsider = User::factory()->create(['role' => 'employee', 'department_id' => $other->id]);
        $c = User::factory()->create();
        $this->requestFor($c, $e);
        $this->requestFor($c, $outsider);
        $reports = app(OperationalReports::class);
        $this->assertSame(1, $reports->build($e, $this->period())['total_requests']);
        $this->actingAs($e)->get('/employee/reports?'.http_build_query($this->period()))->assertOk();
        $this->get('/employee/reports?department_id='.$other->id)->assertForbidden();
        $this->get('/employee/reports?employee_id='.$outsider->id)->assertForbidden();
        $this->get('/employee/reports/export')->assertForbidden();
        $e->forceFill(['can_export_reports' => true])->save();
        $this->get('/employee/reports/export?department_id='.$other->id)->assertForbidden();
        $d->forceFill(['is_active' => false])->save();
        $this->get('/employee/reports')->assertForbidden();
    }

    public function test_dates_cohorts_status_and_closure_metrics_do_not_double_count(): void
    {
        $a = $this->admin();
        $e = User::factory()->create(['role' => 'employee']);
        $c = User::factory()->create();
        $r = $this->requestFor($c, $e);
        $this->requestFor($c, $e, 'submitted', '2026-09-15 10:00:00');
        $done = $this->requestFor($c, $e, 'completed', '2026-10-20 10:00:00');
        DB::table('citizen_service_requests')->where('id', $done->id)->update(['completed_at' => '2026-10-20 11:00:00', 'completed_by' => $e->id]);
        $old = $this->requestFor($c, $e, 'completed', '2026-09-20 10:00:00');
        DB::table('citizen_service_requests')->where('id', $old->id)->update(['completed_at' => '2026-10-20 10:00:00', 'completed_by' => $e->id]);
        foreach (['accept', 'approve'] as $action) {
            (new ReviewDecision)->forceFill(['citizen_service_request_id' => $r->id, 'actor_id' => $e->id, 'action' => $action, 'decision_token' => (string) Str::uuid(), 'command_hash' => str_repeat('a', 64), 'review_version' => 1, 'decided_at' => '2026-10-20 13:00:00'])->save();
        }
        $report = app(OperationalReports::class)->build($a, $this->period());
        $this->assertSame(2, $report['total_requests']);
        $this->assertSame(1, $report['cohort_completed']);
        $this->assertSame(2, $report['closed_in_period']);
        $this->assertSame(50.0, $report['completion_rate']);
        $this->assertSame(2, $report['employees'][0]['actions']);
        $this->assertSame(1, $report['employees'][0]['requests']);
        $this->assertNull($report['overdue']);
        $this->actingAs($a)->get('/admin/reports?period=custom&from=2026-10-31&to=2026-10-01')->assertSessionHasErrors('to');
        $this->get('/admin/reports?period=custom&from=2020-01-01&to=2026-10-01')->assertSessionHasErrors('to');
    }

    public function test_payment_totals_are_matching_receipts_per_currency_not_fee_or_attempt_sums(): void
    {
        $a = $this->admin();
        $e = User::factory()->create(['role' => 'employee']);
        $c = User::factory()->create();
        foreach ([['YER', '100.10', 'paid', '100.10'], ['YER', '20.20', 'paid', '20.20'], ['USD', '3.00', 'paid', '3.00'], ['YER', '900.00', 'due', null], ['YER', '5.00', 'paid', '4.00']] as [$currency,$amount,$status,$receipt]) {
            $r = $this->requestFor($c, $e);
            $charge = (new RequestCharge)->forceFill(['user_id' => $c->id, 'government_service_id' => $r->government_service_id, 'citizen_service_request_id' => $r->id, 'amount' => $amount, 'currency' => $currency, 'status' => $status, 'created_at' => '2026-10-20']);
            $charge->save();
            if ($receipt) {
                (new ManualPayment)->forceFill(['request_charge_id' => $charge->id, 'amount' => $receipt, 'currency' => $currency, 'receipt_reference' => (string) Str::uuid(), 'method' => 'cash', 'paid_at' => '2026-10-20', 'recorded_by' => $e->id, 'recorded_at' => '2026-10-20 12:00:00'])->save();
            }
        }
        $report = app(OperationalReports::class)->build($a, $this->period());
        $this->assertSame(3, $report['payment_count']);
        $this->assertSame(1, $report['payment_reconciliation']);
        $this->assertSame('120.30', collect($report['payments'])->firstWhere('currency', 'YER')['amount']);
        $this->assertSame('3.00', collect($report['payments'])->firstWhere('currency', 'USD')['amount']);
    }

    public function test_audit_is_append_only_redacted_filterable_and_transactional(): void
    {
        $admin = $this->admin();
        $before = DB::table('audit_events')->count();
        try {
            DB::transaction(function () {
                User::factory()->create(['role' => 'employee']);
                throw new \RuntimeException('rollback');
            });
        } catch (\RuntimeException) {
        }
        $this->assertSame($before, DB::table('audit_events')->count());
        $event = app(AuditTrail::class)->record('test.safe', 'user', $admin->id, $admin, 'success', ['password' => 'secret', 'token' => 'secret', 'private_notes' => 'diagnosis', 'status' => 'active']);
        $this->assertSame(['status' => 'active'], $event->changes);
        $this->actingAs($admin)->get('/admin/audit-logs?event=test.safe')->assertOk()->assertSee('test.safe')->assertDontSee('diagnosis');
        $this->patch('/admin/audit-logs/'.$event->id, ['result' => 'failed'])->assertNotFound();
        $this->expectException(\LogicException::class);
        $event->delete();
    }

    public function test_csv_export_respects_period_and_scope_and_prevents_formulas(): void
    {
        $a = $this->admin();
        $e = User::factory()->create(['role' => 'employee']);
        $c = User::factory()->create();
        $this->requestFor($c, $e);
        $this->requestFor($c, $e, 'submitted', '2026-09-01');
        $r = $this->actingAs($a)->get('/admin/reports/export?'.http_build_query($this->period() + ['report' => 'requests']));
        $r->assertOk();
        $csv = $r->streamedContent();
        $this->assertStringContainsString('2026-10-01', $csv);
        $this->assertStringContainsString('submitted,1', $csv);
        $this->assertStringNotContainsString($c->email, $csv);
        foreach (['=HYPERLINK(1)', ' +SUM(1)', '@evil', "\t-1", '-10'] as $v) {
            $this->assertStringStartsWith("'", OperationalReports::csvCell($v));
        }
        $this->assertDatabaseHas('audit_events', ['event' => 'report.export', 'actor_id' => $a->id]);
    }

    public function test_password_reset_uses_real_broker_token_and_invalidates_auth_version(): void
    {
        $e = User::factory()->create(['role' => 'employee']);
        $token = Password::createToken($e);
        $this->post('/reset-password', ['email' => $e->email, 'token' => $token, 'password' => 'New-strong-password-11', 'password_confirmation' => 'New-strong-password-11'])->assertRedirect('/login');
        $this->assertTrue(Hash::check('New-strong-password-11', $e->fresh()->password));
        $this->assertSame(1, $e->fresh()->auth_version);
        $this->post('/reset-password', ['email' => $e->email, 'token' => $token, 'password' => 'Another-password-1122', 'password_confirmation' => 'Another-password-1122'])->assertSessionHasErrors('email');
        $this->actingAs($this->admin())->post('/admin/employees/'.$e->id.'/reset-password')->assertSessionHasErrors('reset');
    }

    public function test_department_creation_deactivation_and_search_work_and_are_protected(): void
    {
        $this->actingAs($this->admin())->post('/admin/departments', ['name' => 'قسم الوثائق', 'is_active' => 1])->assertRedirect();
        $d = Department::where('name', 'قسم الوثائق')->firstOrFail();
        $e = User::factory()->create(['role' => 'employee', 'department_id' => $d->id, 'employee_number' => 'STAFF-999']);
        $this->get('/admin/employees?search=STAFF-999&department_id='.$d->id)->assertOk()->assertSee($e->name);
        $this->patch('/admin/departments/'.$d->id, ['name' => $d->name, 'is_active' => 0])->assertRedirect();
        $this->assertFalse($d->fresh()->is_active);
        $this->delete('/admin/departments/'.$d->id)->assertStatus(405);
        $this->actingAs($e)->post('/admin/departments', ['name' => 'forged', 'is_active' => 1])->assertForbidden();
    }

    public function test_auth_events_record_no_credentials_and_last_login_is_recorded(): void
    {
        $e = User::factory()->create(['role' => 'employee', 'password' => 'Strong-password-1122']);
        $this->post('/login', ['identifier' => $e->email, 'password' => 'Strong-password-1122'])->assertRedirect();
        $this->assertNotNull($e->fresh()->last_login_at);
        $this->post('/logout')->assertRedirect();
        $this->post('/login', ['identifier' => 'notfound@example.test', 'password' => 'SecretWrongPassword'])->assertSessionHasErrors();
        $this->assertDatabaseHas('audit_events', ['event' => 'auth.login', 'actor_id' => $e->id]);
        $this->assertDatabaseHas('audit_events', ['event' => 'auth.logout', 'actor_id' => $e->id]);
        $this->assertDatabaseHas('audit_events', ['event' => 'auth.failed', 'result' => 'failed']);
        $this->assertStringNotContainsString('SecretWrongPassword', json_encode(DB::table('audit_events')->get()));
    }

    public function test_department_breakdown_and_activity_include_approvals_and_workload(): void
    {
        $a = $this->admin();
        $d = $this->department();
        $e = User::factory()->create(['role' => 'employee', 'department_id' => $d->id]);
        $c = User::factory()->create();
        $r = $this->requestFor($c, $e);
        (new ReviewDecision)->forceFill(['citizen_service_request_id' => $r->id, 'actor_id' => $e->id, 'action' => 'approve', 'decision_token' => (string) Str::uuid(), 'command_hash' => str_repeat('a', 64), 'review_version' => 1, 'decided_at' => '2026-10-20 13:00:00'])->save();
        $report = app(OperationalReports::class)->build($a, $this->period());
        $row = collect($report['employees'])->firstWhere('employee_id', $e->id);
        $this->assertSame(1, $row['approvals']);
        $this->assertSame(1, $row['assigned_open']);
        $dept = collect($report['department_activity'])->firstWhere('department_id', $d->id);
        $this->assertSame(1, $dept['requests']);
        $this->assertSame(1, $dept['review_actions']);
    }

    public function test_permission_updates_capture_previous_and_new_flags_and_actor_department(): void
    {
        $a = $this->admin();
        $d = $this->department();
        $a->forceFill(['department_id' => $d->id])->save();
        $e = User::factory()->create(['role' => 'employee']);
        $service = GovernmentService::firstOrFail();
        $payload = ['government_service_id' => $service->id, 'can_review' => 1, 'can_approve' => 0, 'can_assign' => 0];
        $this->actingAs($a)->post('/admin/users/'.$e->id.'/review-permissions', $payload)->assertRedirect();
        $payload['can_approve'] = 1;
        $this->post('/admin/users/'.$e->id.'/review-permissions', $payload)->assertRedirect();
        $entry = AuditEvent::where('event', 'permission.updated')->latest('id')->firstOrFail();
        $this->assertSame($a->id, $entry->actor_id);
        $this->assertSame($d->id, $entry->department_id);
        $this->assertEquals(0, $entry->changes['can_approve']['before']);
        $this->assertTrue($entry->changes['can_approve']['after']);
    }

    public function test_changed_credentials_revoke_existing_session_and_profile_cannot_grant_reports(): void
    {
        $e = User::factory()->create(['role' => 'employee', 'password' => 'Strong-password-1122']);
        $this->post('/login', ['identifier' => $e->email, 'password' => 'Strong-password-1122']);
        $e->forceFill(['password' => 'Changed-password-1122'])->save();
        $this->get('/employee/dashboard')->assertRedirect('/login');
        $c = User::factory()->create();
        $this->actingAs($c)->patch('/profile', ['name' => $c->name, 'email' => $c->email, 'report_scope' => 'department', 'can_export_reports' => 1, 'department_id' => 1]);
        $this->assertSame('none', $c->fresh()->report_scope);
        $this->assertFalse($c->fresh()->can_export_reports);
    }
}
