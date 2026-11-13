<?php

namespace Tests\Feature;

use App\Models\DemoCivilDocument;
use App\Models\DemoCivilPerson;
use App\Models\DemoCivilRecord;
use App\Models\GovernmentService;
use App\Models\ServiceReviewPermission;
use App\Models\User;
use Database\Seeders\DemoCivilRegistrySeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class DemoCivilRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app['env'] = 'testing';
        config([
            'app.env' => 'testing',
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'demo_civil_registry.enabled' => true,
            'demo_civil_registry.allow_seed' => true,
        ]);
        DB::purge();
        $connection = DB::connection();
        if ($connection->getDriverName() !== 'sqlite' || $connection->getDatabaseName() !== ':memory:') {
            throw new \RuntimeException('Demo registry tests require an in-memory SQLite database.');
        }
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $connection->beginTransaction();
        $this->beforeApplicationDestroyed(function () use ($connection) {
            while ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }
            $connection->disconnect();
        });
        Storage::fake('demo-civil-registry');
        app(DemoCivilRegistrySeeder::class)->run();
    }

    private function actor(string $role): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role, 'is_active' => true])->save();

        return $user;
    }

    private function authorizedEmployee(): User
    {
        $employee = $this->actor('employee');
        $employee->forceFill(['can_access_demo_civil_registry' => true])->save();

        return $employee;
    }

    public function test_seed_is_synthetic_linked_idempotent_and_does_not_create_users(): void
    {
        $this->assertSame(100, DemoCivilPerson::count());
        $this->assertSame(20, DB::table('demo_civil_families')->count());
        $this->assertSame(0, User::count());
        $this->assertSame(0, DB::table('citizen_service_requests')->count());
        $this->assertSame(0, DB::table('demo_civil_people')->where('is_synthetic', false)->count());
        foreach (DemoCivilRecord::TYPES as $type) {
            $this->assertGreaterThan(0, DemoCivilRecord::where('record_type', $type)->count(), "No fixture records for {$type}");
        }

        app(DemoCivilRegistrySeeder::class)->run();

        $this->assertSame(100, DemoCivilPerson::count());
        $this->assertSame(20, DB::table('demo_civil_families')->count());
        $this->assertSame(0, User::count());
    }

    public function test_guests_citizens_and_ungranted_employees_cannot_search(): void
    {
        $this->getJson('/employee/demo-civil-registry/search?q=test&mode=name')->assertUnauthorized();
        $this->actingAs($this->actor('citizen'))->getJson('/employee/demo-civil-registry/search?q=test&mode=name')->assertForbidden();
        $this->actingAs($this->actor('employee'))->getJson('/employee/demo-civil-registry/search?q=test&mode=name')->assertForbidden();
        $this->assertDatabaseHas('demo_civil_registry_audits', ['actor_role' => 'guest', 'outcome' => 'denied']);
    }

    public function test_activation_or_verification_grants_do_not_imply_registry_access(): void
    {
        $admin = $this->actor('admin');
        $employee = $this->actor('employee');
        $service = GovernmentService::where('code', 'identity-first-issue')->firstOrFail();
        (new ServiceReviewPermission)->forceFill([
            'user_id' => $employee->id,
            'government_service_id' => $service->id,
            'granted_by' => $admin->id,
            'can_activate' => true,
            'can_verify' => true,
        ])->save();

        $this->actingAs($employee)->getJson('/employee/demo-civil-registry/search?q=DEMO-CIV-000001&mode=identifier')->assertForbidden();
    }

    public function test_granted_employee_can_search_all_identifier_types_without_mutating_real_tables(): void
    {
        $employee = $this->authorizedEmployee();
        $person = DemoCivilPerson::where('demo_identifier', 'DEMO-CIV-000001')->firstOrFail();
        $beforeUsers = User::count();
        $beforeRequests = DB::table('citizen_service_requests')->count();

        $this->actingAs($employee)->getJson('/employee/demo-civil-registry/search?q='.urlencode($person->full_name).'&mode=name')
            ->assertOk()->assertJsonPath('results.0.demo_identifier', $person->demo_identifier)
            ->assertJsonMissingPath('results.0.national_id');
        $this->getJson('/employee/demo-civil-registry/search?q='.$person->demo_identifier.'&mode=identifier')
            ->assertOk()->assertJsonPath('results.0.id', $person->id);
        $document = DemoCivilDocument::where('demo_identifier', 'DEMO-DOC-000001')->firstOrFail();
        $this->getJson('/employee/demo-civil-registry/search?q='.$document->demo_identifier.'&mode=document')
            ->assertOk()->assertJsonPath('results.0.id', $person->id);
        $this->getJson('/employee/demo-civil-registry/search?q='.urlencode('مواطن تجريبي رقم').'&mode=name&page=1')
            ->assertOk()->assertJsonPath('has_more', true)->assertJsonCount(20, 'results');
        $this->getJson('/employee/demo-civil-registry/search?q='.urlencode('مواطن تجريبي رقم').'&mode=name&page=2')
            ->assertOk()->assertJsonPath('page', 2)->assertJsonCount(20, 'results');

        $this->assertSame($beforeUsers, User::count());
        $this->assertSame($beforeRequests, DB::table('citizen_service_requests')->count());
        $this->assertDatabaseHas('demo_civil_registry_audits', ['action' => 'registry.search', 'user_id' => $employee->id]);
        $audit = DB::table('demo_civil_registry_audits')->where('action', 'registry.search')->latest('id')->first();
        $this->assertStringNotContainsString($person->full_name, json_encode($audit->metadata));
    }

    public function test_employee_is_read_only_and_admin_can_manage_demo_people(): void
    {
        $employee = $this->authorizedEmployee();
        $this->actingAs($employee)->post('/admin/demo-civil-registry/people', [])->assertForbidden();
        $this->delete('/admin/demo-civil-registry/people/1')->assertForbidden();

        $admin = $this->actor('admin');
        $this->actingAs($admin)->post('/admin/demo-civil-registry/people', [
            'demo_identifier' => 'DEMO-CIV-000101',
            'full_name' => 'ملف إداري اصطناعي',
            'gender' => 'unspecified',
            'birth_date' => '2000-01-01',
            'birth_place' => 'مكان اختبار',
            'marital_status' => 'single',
            'synthetic_address' => 'عنوان اختبار',
        ])->assertRedirect();
        $this->assertDatabaseHas('demo_civil_people', ['demo_identifier' => 'DEMO-CIV-000101', 'is_synthetic' => true]);
        $this->assertDatabaseHas('demo_civil_registry_audits', ['action' => 'registry.person.create', 'user_id' => $admin->id]);
        $this->assertSame(2, User::count());
    }

    public function test_all_eight_sections_and_private_fixture_documents_are_available_to_granted_employee(): void
    {
        $employee = $this->authorizedEmployee();
        $this->actingAs($employee);
        foreach (DemoCivilRecord::TYPES as $type) {
            $record = DemoCivilRecord::where('record_type', $type)->firstOrFail();
            $this->getJson("/employee/demo-civil-registry/people/{$record->person_id}/sections/{$type}")
                ->assertOk()->assertJsonPath('section', $type);
        }

        $person = DemoCivilPerson::where('demo_identifier', 'DEMO-CIV-000001')->firstOrFail();
        $this->get('/employee/demo-civil-registry/people/'.$person->id.'/photo')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $document = DemoCivilDocument::where('demo_identifier', 'DEMO-DOC-000001')->firstOrFail();
        $this->get('/employee/demo-civil-registry/documents/'.$document->id)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertDatabaseHas('demo_civil_registry_audits', ['action' => 'registry.photo.view', 'person_id' => $person->id]);
        $this->assertDatabaseHas('demo_civil_registry_audits', ['action' => 'registry.document.view', 'document_id' => $document->id]);
    }

    public function test_related_party_sees_shared_marriage_record_and_document(): void
    {
        $employee = $this->authorizedEmployee();
        $wife = DemoCivilPerson::where('demo_identifier', 'DEMO-CIV-000002')->firstOrFail();
        $marriage = DemoCivilRecord::where('fixture_key', 'marriage-1')->firstOrFail();

        $this->actingAs($employee)->getJson("/employee/demo-civil-registry/people/{$wife->id}/sections/marriage")
            ->assertOk()->assertJsonPath('records.0.id', $marriage->id)
            ->assertJsonPath('records.0.documents.0.demo_identifier', 'DEMO-DOC-000301');
        $this->getJson('/employee/demo-civil-registry/search?q=DEMO-DOC-000301&mode=document')
            ->assertOk()->assertJsonFragment(['demo_identifier' => $wife->demo_identifier]);
    }

    public function test_admin_management_is_unavailable_when_feature_flag_is_disabled(): void
    {
        config(['demo_civil_registry.enabled' => false]);
        $this->actingAs($this->actor('admin'))->get('/admin/demo-civil-registry')->assertNotFound();
        $this->post('/admin/demo-civil-registry/people', [])->assertForbidden();
    }

    public function test_admin_pages_and_employee_window_component_render(): void
    {
        $admin = $this->actor('admin');
        $employee = $this->actor('employee');
        $this->actingAs($admin)->get('/admin/demo-civil-registry')->assertOk()->assertSee('DEMO-CIV-000001');
        $this->get('/admin/demo-civil-registry/audits')->assertOk()->assertSee('سجل تدقيق السجل المدني التجريبي');
        $this->get('/admin/review-permissions')->assertOk()->assertSee('صلاحية السجل المدني التجريبي');
        $this->post('/admin/users/'.$employee->id.'/demo-civil-registry-access', ['can_access_demo_civil_registry' => 1])->assertRedirect();
        $this->assertTrue($employee->fresh()->can_access_demo_civil_registry);
        $this->getJson('/admin/demo-civil-registry/search?q=DEMO-CIV-000001&mode=identifier')->assertOk()->assertJsonPath('results.0.demo_identifier', 'DEMO-CIV-000001');
        $this->assertDatabaseHas('demo_civil_registry_audits', ['action' => 'registry.access.grant', 'user_id' => $admin->id]);

        $this->view('components.demo-civil-registry-window', [
            'application' => (object) ['id' => 12],
            'suggestedSection' => 'birth',
        ])->assertSee('السجل المدني التجريبي')->assertSee('data-suggested-section="birth"', false);
    }

    public function test_admin_upload_and_replacement_use_only_private_demo_storage(): void
    {
        $admin = $this->actor('admin');
        $person = DemoCivilPerson::where('demo_identifier', 'DEMO-CIV-000001')->firstOrFail();
        $document = DemoCivilDocument::where('demo_identifier', 'DEMO-DOC-000001')->firstOrFail();
        $originalSharedFixture = $document->storage_path;

        $this->actingAs($admin)->post('/admin/demo-civil-registry/people/'.$person->id.'/photo', [
            'photo' => UploadedFile::fake()->image('synthetic.png', 32, 32),
        ])->assertRedirect();
        $person->refresh();
        Storage::disk('demo-civil-registry')->assertExists($person->photo_path);
        $this->assertSame(Storage::disk('demo-civil-registry')->size($person->photo_path), $person->photo_byte_size);

        $this->put('/admin/demo-civil-registry/documents/'.$document->id, [
            'label' => 'ملف بديل اصطناعي',
            'file' => UploadedFile::fake()->image('synthetic-document.png', 32, 32),
        ])->assertRedirect();
        $document->refresh();
        Storage::disk('demo-civil-registry')->assertExists($document->storage_path);
        Storage::disk('demo-civil-registry')->assertExists($originalSharedFixture);
        $this->get('/admin/demo-civil-registry/documents/'.$document->id)->assertOk();
    }

    public function test_seed_dates_and_parent_relationships_are_consistent(): void
    {
        foreach (DemoCivilRecord::with(['person', 'parties.person'])->get() as $record) {
            if ($record->occurred_on) {
                $this->assertTrue($record->occurred_on->lessThanOrEqualTo(today()), $record->fixture_key.' is in the future');
                $this->assertTrue($record->occurred_on->greaterThanOrEqualTo($record->person->birth_date), $record->fixture_key.' predates birth');
            }
            if ($record->record_type === 'birth') {
                foreach ($record->parties->whereIn('party_role', ['father', 'mother']) as $parent) {
                    $this->assertNotSame($record->person_id, $parent->person_id, 'A person cannot be their own parent');
                    $this->assertTrue($parent->person->birth_date->copy()->addYears(18)->lessThanOrEqualTo($record->occurred_on));
                }
            }
        }
        $death = DemoCivilRecord::where('record_type', 'death')->firstOrFail();
        $this->assertFalse(DemoCivilRecord::where('person_id', $death->person_id)->where('occurred_on', '>', $death->occurred_on)->exists());
    }

    public function test_existing_divorce_can_be_updated_without_reopening_its_marriage(): void
    {
        $record = DemoCivilRecord::with('parties')->where('record_type', 'divorce')->firstOrFail();
        $this->actingAs($this->actor('admin'))->patch('/admin/demo-civil-registry/records/'.$record->id, [
            'record_type' => 'divorce', 'demo_identifier' => $record->demo_identifier,
            'person_id' => $record->person_id, 'family_id' => $record->family_id,
            'related_record_id' => $record->related_record_id,
            'occurred_on' => $record->occurred_on->format('Y-m-d'), 'status' => $record->status,
            'details_json' => json_encode($record->details),
            'parties_json' => $record->parties->map(fn ($party) => ['person_id' => $party->person_id, 'role' => $party->party_role])->toJson(),
        ])->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_membership_endpoint_cannot_silently_replace_or_demote_family_head(): void
    {
        $family = \App\Models\DemoCivilFamily::firstOrFail();
        $this->actingAs($this->actor('admin'))->postJson('/admin/demo-civil-registry/families/'.$family->id.'/members', [
            'person_id' => $family->head_person_id, 'relationship' => 'child', 'status' => 'former',
        ])->assertUnprocessable();
    }

    public function test_unattached_documents_are_visible_in_person_profile(): void
    {
        $document = DemoCivilDocument::firstOrFail();
        $document->forceFill(['record_id' => null])->save();
        $this->actingAs($this->authorizedEmployee())->getJson('/employee/demo-civil-registry/people/'.$document->person_id)
            ->assertOk()->assertJsonPath('person.documents.0.id', $document->id);
    }

    public function test_admin_can_edit_fixture_records_of_every_type(): void
    {
        $this->actingAs($this->actor('admin'));
        foreach (DemoCivilRecord::TYPES as $type) {
            $record = DemoCivilRecord::with('parties')->where('record_type', $type)->firstOrFail();
            $this->patch('/admin/demo-civil-registry/records/'.$record->id, [
                'record_type' => $type, 'demo_identifier' => $record->demo_identifier,
                'person_id' => $record->person_id, 'family_id' => $record->family_id,
                'related_record_id' => $record->related_record_id,
                'occurred_on' => $record->occurred_on?->format('Y-m-d'), 'status' => $record->status,
                'details_json' => json_encode($record->details),
                'parties_json' => $record->parties->map(fn ($party) => ['person_id' => $party->person_id, 'role' => $party->party_role])->toJson(),
            ])->assertRedirect()->assertSessionHasNoErrors();
        }
    }

    public function test_search_validation_empty_sections_archives_and_file_integrity(): void
    {
        $this->actingAs($this->authorizedEmployee());
        foreach (['q=&mode=name', 'q=x&mode=name', 'q=valid&mode=invalid', 'q[]=x&mode=name', 'q=valid&mode=name&page=101'] as $query) {
            $this->getJson('/employee/demo-civil-registry/search?'.$query)->assertUnprocessable();
        }
        $this->getJson('/employee/demo-civil-registry/search?q=NO-SUCH-PERSON&mode=identifier')->assertOk()->assertJsonCount(0, 'results');
        $this->getJson('/employee/demo-civil-registry/people/100/sections/divorce')->assertOk()->assertJsonCount(0, 'records');
        $this->getJson('/employee/demo-civil-registry/people/100/sections/invalid')->assertNotFound();
        $document = DemoCivilDocument::firstOrFail();
        Storage::disk('demo-civil-registry')->put($document->storage_path, 'tampered');
        $this->get('/employee/demo-civil-registry/documents/'.$document->id)->assertNotFound();
        $document->forceFill(['storage_path' => '../private/real-document.pdf'])->save();
        $this->get('/employee/demo-civil-registry/documents/'.$document->id)->assertNotFound();
        $this->actingAs($this->actor('admin'))->delete('/admin/demo-civil-registry/people/100')->assertRedirect();
        $this->getJson('/admin/demo-civil-registry/people/100')->assertForbidden();
        $this->getJson('/admin/demo-civil-registry/search?q=DEMO-CIV-000100&mode=identifier')->assertOk()->assertJsonCount(0, 'results');
    }

    public function test_production_and_seed_safety_guards(): void
    {
        $this->actingAs($this->actor('admin'));
        $this->app['env'] = 'production';
        $this->getJson('/admin/demo-civil-registry/search?q=DEMO-CIV-000001&mode=identifier')->assertForbidden();
        try {
            app(DemoCivilRegistrySeeder::class)->run();
            $this->fail('Production seeding must be refused');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_review_window_uses_request_context_and_never_mutates_the_application(): void
    {
        $employee = $this->authorizedEmployee();
        $citizen = $this->actor('citizen');
        $service = GovernmentService::where('code', 'identity-first-issue')->firstOrFail();
        $application = $citizen->serviceRequests()->create(['reference' => 'DEMO-CONTEXT-TEST', 'service_code' => $service->code, 'status' => 'submitted']);
        $application->forceFill(['request_data' => ['address' => 'unchanged'], 'request_type' => 'issue', 'submitted_at' => now()])->save();
        $before = DB::table('citizen_service_requests')->where('id', $application->id)->first();
        $this->actingAs($employee)->getJson('/employee/demo-civil-registry/people/1?request_id='.$application->id)->assertForbidden();
        (new ServiceReviewPermission)->forceFill(['user_id' => $employee->id, 'government_service_id' => $service->id, 'granted_by' => $this->actor('admin')->id, 'can_review' => true])->save();
        $this->get('/employee/applications/'.$application->id.'/review')->assertOk()
            ->assertSee('data-demo-registry-open', false)->assertSee('data-suggested-section="identity_card"', false);
        $this->getJson('/employee/demo-civil-registry/people/1?request_id='.$application->id)->assertOk();
        $this->assertEquals($before, DB::table('citizen_service_requests')->where('id', $application->id)->first());
        $this->assertDatabaseHas('demo_civil_registry_audits', ['action' => 'registry.person.open', 'citizen_service_request_id' => $application->id]);
        $this->patchJson('/admin/demo-civil-registry/people/1', [])->assertForbidden();
        $employee->forceFill(['can_access_demo_civil_registry' => false])->save();
        $this->get('/employee/demo-civil-registry/documents/1')->assertForbidden();
    }

    public function test_suggested_section_maps_all_eight_types_without_granting_access(): void
    {
        $controller = new \App\Http\Controllers\ApplicationReviewController;
        $method = new \ReflectionMethod($controller, 'suggestedRegistrySection');
        foreach (['birth_certificate' => 'birth', 'identity' => 'identity_card', 'marriage' => 'marriage', 'divorce' => 'divorce', 'death' => 'death', 'family' => 'family', 'correction' => 'correction', 'civil_restriction' => 'restriction'] as $type => $expected) {
            $application = new \App\Models\CitizenServiceRequest;
            $application->forceFill(['service_snapshot' => ['document_type' => $type, 'code' => 'unknown'], 'request_type' => 'issue']);
            $this->assertSame($expected, $method->invoke($controller, $application));
        }
    }

    public function test_disabled_module_does_not_require_its_column_on_the_existing_permissions_page(): void
    {
        $admin = $this->actor('admin');
        $employee = $this->actor('employee');
        config(['demo_civil_registry.enabled' => false]);
        $queries = [];
        DB::listen(function ($query) use (&$queries) { $queries[] = $query->sql; });
        $this->actingAs($admin)->get('/admin/review-permissions')->assertOk()->assertDontSee('name="can_access_demo_civil_registry"', false);
        $this->assertStringNotContainsString('can_access_demo_civil_registry', implode('\n', $queries));
        $this->post('/admin/users/'.$employee->id.'/demo-civil-registry-access', ['can_access_demo_civil_registry' => 1])->assertForbidden();
    }

    public function test_admin_can_create_and_archive_records_families_and_documents(): void
    {
        $this->actingAs($this->actor('admin'));
        $this->post('/admin/demo-civil-registry/families', ['demo_identifier' => 'DEMO-FAM-000021', 'head_person_id' => 1])->assertRedirect()->assertSessionHasNoErrors();
        $family = \App\Models\DemoCivilFamily::where('demo_identifier', 'DEMO-FAM-000021')->firstOrFail();
        $this->patch('/admin/demo-civil-registry/families/'.$family->id, ['demo_identifier' => $family->demo_identifier, 'head_person_id' => 2])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(2, $family->fresh()->head_person_id);
        $this->post('/admin/demo-civil-registry/records', [
            'record_type' => 'restriction', 'demo_identifier' => 'DEMO-RESTRICTION-MANUAL', 'person_id' => 1,
            'occurred_on' => '2022-01-01', 'status' => 'released',
            'details_json' => json_encode(['restriction_type' => 'اختبار', 'description' => 'قيد اصطناعي', 'authority' => 'جهة تجريبية']),
            'parties_json' => '[{"person_id":1,"role":"subject"}]',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $record = DemoCivilRecord::where('demo_identifier', 'DEMO-RESTRICTION-MANUAL')->firstOrFail();
        $this->delete('/admin/demo-civil-registry/records/'.$record->id)->assertRedirect();
        $this->assertNotNull($record->fresh()->archived_at);
        $this->delete('/admin/demo-civil-registry/families/'.$family->id)->assertRedirect();
        $this->assertNotNull($family->fresh()->archived_at);
        $document = DemoCivilDocument::firstOrFail();
        $this->delete('/admin/demo-civil-registry/documents/'.$document->id)->assertRedirect();
        $this->get('/admin/demo-civil-registry/documents/'.$document->id)->assertNotFound();
        Storage::disk('demo-civil-registry')->assertExists($document->storage_path);
    }
}
