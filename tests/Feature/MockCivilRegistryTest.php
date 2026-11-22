<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\MockCivilRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MockCivilRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! app()->environment('testing') || DB::connection()->getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
            throw new \RuntimeException('Isolated SQLite test database required.');
        }

        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        config(['citizen_mock.enabled' => true]);
    }

    public function test_list_includes_unlinked_people_and_preserves_validated_filters_across_pages(): void
    {
        $family = DB::table('mock_civil_families')->insertGetId(['reference' => 'FAMILY-01']);
        $linked = $this->person('Linked Record', ['family_id' => $family, 'national_id' => '123456']);
        User::factory()->create(['mock_person_id' => $linked]);
        $this->person('Independent Record', ['complete' => false]);
        for ($index = 1; $index <= 21; $index++) {
            $this->person('Paged Person '.$index);
        }

        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $url = route('admin.mock-civil-registry.index');
        $this->get($url)->assertOk()->assertSee('Linked Record')->assertSee('Independent Record');
        $this->get($url.'?q=123456&account=linked')->assertOk()->assertSee('Linked Record')->assertDontSee('Independent Record');
        $this->get($url.'?q=FAMILY-01')->assertOk()->assertSee('Linked Record')->assertDontSee('Independent Record');
        $this->get($url.'?account=unlinked&record=incomplete')->assertOk()->assertSee('Independent Record')->assertDontSee('Linked Record');
        $this->get($url.'?q=Paged&account=unlinked')->assertOk()->assertSee('q=Paged&amp;account=unlinked&amp;page=2', false);
        $this->get($url.'?q=Paged&account=unlinked&page=2')->assertOk()->assertSee('Paged Person 21')->assertDontSee('Paged Person 20');
        $this->get($url.'?q=%25')->assertOk()->assertDontSee('Linked Record');
        $this->getJson($url.'?q[]=invalid&account=unknown&page=0')->assertUnprocessable()
            ->assertJsonValidationErrors(['q', 'account', 'page']);
    }

    public function test_profiles_read_civil_records_without_assuming_an_account_and_audit_access(): void
    {
        $family = DB::table('mock_civil_families')->insertGetId(['reference' => 'FAMILY-02']);
        $person = $this->person('Unlinked Profile', ['family_id' => $family, 'reference_photo' => 'private-reference.png']);
        $this->person('Family Member', ['family_id' => $family]);
        $this->person('Other Family');
        DB::table('mock_identity_cards')->insert(['person_id' => $person, 'number' => 'TEST-CARD']);
        DB::table('mock_birth_records')->insert(['person_id' => $person, 'number' => 'TEST-BIRTH']);
        DB::table('mock_civil_documents')->insert(['person_id' => $person, 'document_type' => 'family_record', 'document_number' => 'TEST-CIVIL', 'issued_at' => '2025-01-01', 'issued_place' => 'مكان تجريبي']);

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.mock-civil-registry.show', $person))
            ->assertOk()->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertSee('Family Member')->assertSee('TEST-CARD')->assertSee('TEST-BIRTH')->assertSee('TEST-CIVIL')
            ->assertSee('لا يوجد حساب مرتبط')->assertDontSee('<h2>طلبات الخدمات</h2>', false)
            ->assertDontSee('<h2>الوثائق الرسمية الصادرة عبر التطبيق</h2>', false)
            ->assertDontSee('Other Family')->assertDontSee('private-reference.png');
        $this->assertDatabaseHas('audit_events', ['event' => 'mock_civil_registry.view', 'actor_id' => $admin->id, 'subject_id' => (string) $person]);
        $this->get(route('admin.mock-civil-registry.show', 99999))->assertNotFound();
    }

    public function test_account_sections_honor_existing_policies_and_select_only_display_fields(): void
    {
        $person = $this->person('Linked Profile');
        $citizen = User::factory()->create(['mock_person_id' => $person]);
        $other = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['role' => 'employee']);
        $service = DB::table('government_services')->where('code', 'identity-first-issue')->value('id');
        DB::table('service_review_permissions')->insert([
            'user_id' => $employee->id, 'government_service_id' => $service, 'granted_by' => $admin->id,
            'can_review' => true, 'can_issue' => true, 'can_activate' => true,
        ]);
        foreach ([$citizen, $other] as $owner) {
            DB::table('citizen_service_requests')->insert([
                'user_id' => $owner->id, 'government_service_id' => $service, 'service_code' => 'identity-first-issue',
                'reference' => 'REQ-'.$owner->id, 'status' => 'submitted', 'request_data' => '{"private":"secret-request"}',
            ]);
            DB::table('citizen_documents')->insert([
                'user_id' => $owner->id, 'government_service_id' => $service, 'document_type' => 'identity',
                'document_number' => 'DOC-'.$owner->id, 'holder_snapshot' => 'secret-holder',
            ]);
        }
        DB::table('mock_face_attempts')->insert([
            'user_id' => $citizen->id, 'operation_token' => (string) Str::uuid(),
            'image_path' => 'secret-image', 'result' => 'MATCH', 'confidence' => 98.5, 'liveness_pass' => true,
        ]);

        $this->actingAs($admin)->get(route('admin.mock-civil-registry.show', $person))
            ->assertOk()->assertSee('طلبات الخدمات')->assertSee('الوثائق الرسمية الصادرة')
            ->assertDontSee('REQ-'.$citizen->id)->assertDontSee('DOC-'.$citizen->id)
            ->assertDontSee('تمت المطابقة تجريبيًا')->assertDontSee('secret-');

        $data = app(MockCivilRegistry::class)->profile($person, $employee);
        $this->assertSame(['REQ-'.$citizen->id], $data['requests']->pluck('reference')->all());
        $this->assertSame(['DOC-'.$citizen->id], $data['officialDocuments']->pluck('document_number')->all());
        $this->assertSame('تمت المطابقة تجريبيًا', $data['faceStatus']);
        $this->assertArrayNotHasKey('request_data', $data['requests']->first()->getAttributes());
        $this->assertArrayNotHasKey('holder_snapshot', $data['officialDocuments']->first()->getAttributes());
        $this->assertStringNotContainsString('secret-', view('admin.mock-civil-registry.show', $data)->render());
    }

    public function test_routes_keep_the_simulation_and_active_admin_boundaries(): void
    {
        $person = $this->person('Restricted Profile');
        foreach ([route('admin.mock-civil-registry.index'), route('admin.mock-civil-registry.show', $person)] as $url) {
            auth()->forgetGuards();
            $this->get($url)->assertRedirect('/login');
            foreach (['citizen', 'employee'] as $role) {
                $this->actingAs(User::factory()->create(['role' => $role]))->get($url)->assertForbidden();
            }
            $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => false]))->get($url)->assertRedirect('/login');
            $admin = User::factory()->create(['role' => 'admin']);
            config(['citizen_mock.enabled' => false]);
            $this->actingAs($admin)->get($url)->assertForbidden();
            config(['citizen_mock.enabled' => true]);
            $this->app['env'] = 'production';
            $this->get($url)->assertForbidden();
            $this->app['env'] = 'testing';
        }
    }

    private function person(string $name, array $attributes = []): int
    {
        return DB::table('mock_civil_persons')->insertGetId($attributes + [
            'fixture_key' => (string) Str::uuid(), 'name' => $name, 'birth_date' => '2000-01-01',
            'birth_place' => 'مكان تجريبي', 'family_role' => 'child',
        ]);
    }
}
