<?php

namespace Tests\Feature;

use App\Models\GovernmentService;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GovernmentServicesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $connection = DB::connection();
        if ($connection->getDatabaseName() !== ':memory:' && ! str_ends_with($connection->getDatabaseName(), '_testing')) {
            throw new \RuntimeException('Isolated testing database required.');
        }
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $connection->beginTransaction();
        $this->beforeApplicationDestroyed(function () use ($connection) {
            $connection->rollBack();
            $connection->disconnect();
        });
    }

    private function user(string $role = 'citizen'): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role, 'username' => 'ID-'.$user->id])->save();

        return $user;
    }

    private function service(string $code = 'identity-first-issue'): GovernmentService
    {
        $service = GovernmentService::where('code', $code)->firstOrFail();
        $service->fill(['is_active' => true, 'rules_approved' => true, 'source_reference' => 'TEST-ONLY approval', 'description' => 'Database service description'])->save();

        return $service;
    }

    private function approve(User $user, GovernmentService $service, array $extra = []): void
    {
        $service->assessments()->updateOrCreate(['user_id' => $user->id], array_merge([
            'rules_version' => $service->rules_version, 'identity_verified' => true,
            'conditions_met' => true, 'age_requirement_met' => true, 'driving_requirements_met' => true,
            'is_blocked' => false, 'verification_reference' => 'TEST-ONLY citizen review',
            'reviewed_by' => $this->user('admin')->id, 'reviewed_at' => now(),
        ], $extra));
    }

    public function test_catalog_uses_database_and_inactive_services_are_not_offered(): void
    {
        $this->assertSame(7, GovernmentService::count());
        $this->get('/services')->assertOk()->assertSee('لا توجد خدمات مفعّلة');
        $service = $this->service();
        $this->get('/services')->assertOk()->assertSee($service->name);
        $this->get('/services/identity/first-issue')->assertOk()->assertSee('Database service description')->assertSee('لم تُحدّد')->assertDontSee('1,500');
        $this->get('/services/passports/renewal')->assertOk()->assertSee('غير مفعّلة');
        $this->get('/services/identity/unknown')->assertNotFound();
        $this->get('/services/catalog/not-found')->assertNotFound();
    }

    public function test_start_requires_login_citizen_role_and_complete_data(): void
    {
        $service = $this->service();
        $path = '/citizen/services/'.$service->id.'/start';
        $this->post($path)->assertRedirect('/login');
        foreach (['employee', 'admin'] as $role) {
            $this->actingAs($this->user($role))->post($path)->assertForbidden();
        }
        $citizen = $this->user();
        $citizen->forceFill(['username' => null])->save();
        $this->actingAs($citizen)->get('/citizen/applications/new?service='.$service->code)->assertRedirect('/citizen/data');
        $this->post($path, ['user_id' => $this->user()->id])->assertRedirect('/citizen/data');
    }

    public function test_unapproved_disabled_or_unassessed_service_cannot_start(): void
    {
        $citizen = $this->user();
        $service = $this->service();
        $this->actingAs($citizen)->get('/citizen/applications/new?service='.$service->code)->assertStatus(422)->assertSee('تقييم');
        $this->approve($citizen, $service);
        $service->update(['is_active' => false]);
        $this->post('/citizen/services/'.$service->id.'/start')->assertStatus(422)->assertSee('غير مفعّلة');
        $this->post('/citizen/services/999999/start')->assertNotFound();
    }

    public function test_eligible_handoff_rechecks_server_and_never_creates_request(): void
    {
        $citizen = $this->user();
        $service = $this->service();
        $this->approve($citizen, $service);
        $this->actingAs($citizen)->get('/citizen/applications/new?service='.$service->code)->assertOk()->assertSee($service->name);
        $response = $this->post('/citizen/services/'.$service->id.'/start', ['service' => 'passport-renewal', 'eligible' => true]);
        $response->assertOk()->assertViewHas('service', fn ($item) => $item->is($service))->assertSee('لم يتم إنشاء طلب');
        $this->assertSame(0, $citizen->serviceRequests()->count());
        $service->assessments()->update(['is_blocked' => true, 'block_reason' => 'TEST restriction']);
        $this->post('/citizen/services/'.$service->id.'/start')->assertStatus(422)->assertSee('يوجد مانع معتمد')->assertDontSee('TEST restriction');
    }

    public function test_renewal_requires_own_document_with_accepted_state_and_coherent_dates(): void
    {
        $citizen = $this->user();
        $other = $this->user();
        $service = $this->service('identity-renewal');
        $service->update(['renewal_statuses' => ['سارية'], 'renewal_window_days' => 30]);
        $this->approve($citizen, $service);
        $other->documents()->create(['document_type' => 'بطاقة', 'document_type_code' => 'identity', 'document_number' => 'OTHER', 'status' => 'سارية', 'issued_at' => now()->subYear(), 'expires_at' => now()->addDay()]);
        $url = '/citizen/services/'.$service->id.'/start';
        $this->actingAs($citizen)->post($url)->assertStatus(422);
        $document = $citizen->documents()->create(['document_type' => 'بطاقة', 'document_type_code' => 'identity', 'document_number' => 'OWN', 'status' => 'سارية', 'issued_at' => now()->subYear(), 'expires_at' => now()->addYear()]);
        $this->post($url)->assertStatus(422);
        $document->update(['expires_at' => now()->addDay()]);
        $this->post($url)->assertOk();
        $document->update(['status' => 'ملغاة']);
        $this->post($url)->assertStatus(422);
        $document->update(['status' => 'سارية', 'issued_at' => null]);
        $this->post($url)->assertStatus(422);
        $document->update(['issued_at' => today()->addDay(), 'expires_at' => today()->addDays(30)]);
        $this->post($url)->assertStatus(422);
        $document->update(['issued_at' => today()->subDay(), 'expires_at' => today()->subDays(2)]);
        $this->post($url)->assertStatus(422);
        $document->update(['issued_at' => today()->subYear(), 'expires_at' => today()->addDays(30)]);
        $this->post($url)->assertOk();
    }

    public function test_driving_age_and_changed_rules_require_current_authorized_assessment(): void
    {
        $citizen = $this->user();
        $service = $this->service('license-first-issue');
        $service->update(['minimum_age' => 18, 'requires_driving_clearance' => true]);
        $this->approve($citizen, $service, ['age_requirement_met' => false, 'driving_requirements_met' => false]);
        $url = '/citizen/services/'.$service->id.'/start';
        $this->actingAs($citizen)->post($url)->assertStatus(422)->assertSee('العمر')->assertSee('القيادة');
        $this->approve($citizen, $service);
        $this->post($url)->assertOk();
        $service->update(['minimum_age' => 21]);
        $this->post($url)->assertStatus(422)->assertSee('تحديث');
    }

    public function test_existing_alias_requests_prevent_duplicates_unless_status_is_configured_terminal(): void
    {
        $citizen = $this->user();
        $service = $this->service('passport-first-issue');
        $this->approve($citizen, $service);
        $citizen->serviceRequests()->create(['reference' => 'KEEP-OLD', 'service_code' => 'passports-first-issue', 'status' => 'pending']);
        $url = '/citizen/services/'.$service->id.'/start';
        $this->actingAs($citizen)->post($url)->assertStatus(422)->assertSee('طلب قائم');
        $service->update(['terminal_request_statuses' => ['pending']]);
        $this->approve($citizen, $service);
        $this->post($url)->assertOk();
        $this->assertSame('passports-first-issue', $citizen->serviceRequests()->first()->service_code);
    }

    public function test_only_admin_can_manage_catalog_and_assessments(): void
    {
        $service = $this->service();
        $citizen = $this->user();
        foreach ([$citizen, $this->user('employee')] as $user) {
            $this->actingAs($user)->get('/admin/services')->assertForbidden();
            $this->patch('/admin/services/'.$service->id, ['fee' => 1])->assertForbidden();
            $this->post('/admin/services/'.$service->id.'/assessments', ['username' => $citizen->username])->assertForbidden();
        }
        $admin = $this->user('admin');
        $this->actingAs($admin)->get('/admin/services')->assertOk();
        $this->get('/admin/services/'.$service->id.'/edit')->assertOk();
        $this->patch('/admin/services/'.$service->id, ['name' => 'Updated name', 'is_active' => false, 'rules_approved' => true, 'source_reference' => 'TEST source'])
            ->assertRedirect('/admin/services');
        $this->assertSame('Updated name', $service->fresh()->name);
        $this->post('/admin/services/'.$service->id.'/assessments', [
            'username' => $citizen->username, 'identity_verified' => true, 'conditions_met' => true,
            'is_blocked' => false, 'verification_reference' => 'TEST approval record',
            'expected_rules_version' => $service->fresh()->rules_version,
        ])->assertSessionHasNoErrors();
        $this->assertSame($admin->id, $service->assessments()->first()->reviewed_by);
    }

    public function test_admin_validation_and_identifier_immutability_preserve_historical_links(): void
    {
        $admin = $this->user('admin');
        $service = $this->service();
        $this->actingAs($admin)->patch('/admin/services/'.$service->id, ['name' => 'Name', 'code' => 'evil', 'fee' => -1])->assertSessionHasErrors(['code', 'fee']);
        $this->patch('/admin/services/'.$service->id, ['name' => 'Name', 'is_active' => true, 'rules_approved' => false])->assertSessionHasErrors();
        $this->post('/admin/services', ['name' => 'Additional service', 'code' => 'additional-test', 'document_type' => 'passport', 'operation' => 'issue', 'is_active' => false, 'rules_approved' => false])->assertRedirect('/admin/services');
        $this->assertDatabaseHas('government_services', ['code' => 'additional-test', 'is_active' => false]);
        $this->assertSame('identity-first-issue', $service->fresh()->code);
    }

    public function test_stale_assessment_form_and_missing_source_cannot_grant_eligibility(): void
    {
        $admin = $this->user('admin');
        $citizen = $this->user();
        $service = $this->service();
        $oldVersion = $service->rules_version;
        $service->update(['conditions' => ['New authoritative requirement']]);
        $payload = ['username' => $citizen->username, 'identity_verified' => true, 'conditions_met' => true, 'is_blocked' => false, 'expected_rules_version' => $oldVersion, 'verification_reference' => 'TEST reference'];
        $this->actingAs($admin)->post('/admin/services/'.$service->id.'/assessments', $payload)->assertSessionHasErrors('expected_rules_version');
        $payload['expected_rules_version'] = $service->rules_version;
        $payload['verification_reference'] = '';
        $this->post('/admin/services/'.$service->id.'/assessments', $payload)->assertSessionHasErrors('verification_reference');
        $this->assertSame(0, $service->assessments()->count());
    }

    public function test_request_snapshots_and_legacy_links_survive_catalog_updates_and_migration_reapplication(): void
    {
        $citizen = $this->user();
        $service = $this->service('passport-first-issue');
        $service->update(['fee' => 12.50, 'currency' => 'TEST']);
        $request = $citizen->serviceRequests()->create(['reference' => 'HISTORY', 'service_code' => 'passports-first-issue']);
        $this->assertTrue($request->service->is($service));
        $oldName = $request->service_name;
        $service->update(['name' => 'Changed service label', 'fee' => 25]);
        DB::table('citizen_service_requests')->where('id', $request->id)->update(['government_service_id' => null]);
        $migration = require database_path('migrations/2026_10_23_000001_create_government_service_catalog.php');
        $migration->down();
        $migration->up();
        $request->refresh();
        $this->assertTrue($request->service->is($service));
        $this->assertSame('passports-first-issue', $request->service_code);
        $this->assertSame('12.50', $request->service_fee);
        $this->assertSame($oldName, $request->service_name);
        $this->assertSame($oldName, $request->service_snapshot['name']);
        $this->assertSame('12.50', $request->service_snapshot['fee']);
        $this->assertSame(1, $citizen->serviceRequests()->count());
    }
}
