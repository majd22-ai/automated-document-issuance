<?php

namespace Tests\Feature;

use App\Enums\VerificationStatus;
use App\Models\User;
use App\Services\Integrations\LocalCivilRegistryProvider;
use App\Services\Integrations\VerificationContext;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CivilRegistrySimulationTest extends TestCase
{
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
        $this->app['env'] = 'local';
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config(['civil_registry_demo.enabled' => true]);
    }

    private function staff(string $role = 'admin'): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role, 'is_active' => true])->save();
        if ($role === 'employee') {
            (new \App\Models\ServiceReviewPermission)->forceFill(['user_id'=>$user->id,'government_service_id'=>\App\Models\GovernmentService::where('code','identity-first-issue')->value('id'),'can_verify'=>true,'granted_by'=>$user->id])->save();
        }

        return $user;
    }

    public function test_synthetic_scenarios_and_no_real_network_or_database_changes(): void
    {
        $provider = app(LocalCivilRegistryProvider::class);
        $record = config('civil_registry_demo.records.99000000001');
        $this->assertSame('match', $provider->simulate('99000000001', $record['name']));
        $this->assertSame('mismatch', $provider->simulate('99000000001', 'Different Name'));
        $this->assertSame('not_found', $provider->simulate('99000000009', 'Different Name'));
        $this->assertSame('unavailable', $provider->simulate('99000000002', 'Different Name'));
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('integration_attempts', 0);
    }

    public function test_simulation_cannot_be_official_verification(): void
    {
        $result = app(LocalCivilRegistryProvider::class)->verify(new VerificationContext('demo', 'identity', ['national_id' => '99000000001'], 'demo'), []);
        $this->assertSame(VerificationStatus::Unknown, $result->status);
        $this->assertFalse($result->bound);
    }

    public function test_staff_can_preview_and_result_is_private(): void
    {
        $url = '/integrations/civil-registry-demo';
        $this->actingAs($this->staff('employee'))->get($url)
            ->assertRedirect(route('employee.dashboard', ['tools' => 'civil-check']))->assertHeader('Cache-Control', 'no-store, private');
        $this->get(route('employee.dashboard', ['tools' => 'civil-check']))->assertOk()
            ->assertSee('data-ops-view="civil-check"', false)->assertDontSee('id="sim-civil-modal"', false);
        $this->postJson($url, ['national_id' => '99000000001', 'name' => config('civil_registry_demo.records.99000000001.name')])
            ->assertOk()->assertExactJson(['outcome' => 'match'])->assertHeader('Cache-Control', 'no-store, private');
        $this->assertDatabaseCount('integration_attempts', 0);
    }

    public function test_citizen_and_guest_cannot_access_preview(): void
    {
        $this->get('/integrations/civil-registry-demo')->assertRedirect('/login');
        $this->actingAs($this->staff('citizen'))->get('/integrations/civil-registry-demo')->assertForbidden();
        $this->post('/integrations/civil-registry-demo', ['national_id' => '99000000001', 'name' => 'test'])->assertForbidden();
    }

    public function test_single_field_returns_civil_records_by_number_or_name_and_suggests_names(): void
    {
        config(['citizen_mock.enabled' => true]);
        $person = DB::table('mock_civil_persons')->insertGetId([
            'fixture_key' => 'lookup-fixture', 'name' => 'أحمد علي سالم العمري', 'national_id' => '99010000901',
            'birth_date' => '2000-01-02', 'birth_place' => 'منطقة اختبار', 'family_role' => 'son',
            'reference_photo' => 'private-not-for-lookup.jpg',
        ]);
        DB::table('mock_birth_records')->insert(['person_id' => $person, 'number' => 'MOCK-BIRTH-TEST']);
        Storage::fake('attachments');
        $photo = UploadedFile::fake()->image('lookup.jpg', 600, 600);
        Storage::disk('attachments')->put('mock-registry/portraits/lookup-fixture.jpg', file_get_contents($photo->getPathname()));
        $portraitUrl = route('integrations.civil-registry-demo.portrait', $person, false);
        $employee = $this->staff('employee');
        User::factory()->create(['mock_person_id' => $person]);
        $url = '/integrations/civil-registry-demo';
        $this->actingAs($employee)->get('/employee/dashboard')->assertOk()
            ->assertSee('name="query"', false)->assertSee('data-civil-suggestions', false);
        $this->postJson($url, ['query' => 'احمد', 'suggest' => true])
            ->assertOk()->assertExactJson(['suggestions' => ['أحمد علي سالم العمري']]);
        foreach (['احمد علي سالم العمري', '٩٩٠١٠٠٠٠٩٠١'] as $query) {
            $this->postJson($url, ['query' => $query])->assertOk()
                ->assertJsonPath('outcome', 'match')
                ->assertJsonPath('civil_records.0.birth.number', 'MOCK-BIRTH-TEST')
                ->assertJsonPath('civil_records.0.person.national_id', '99010000901')
                ->assertJsonPath('civil_records.0.portrait_url', $portraitUrl)
                ->assertJsonMissingPath('civil_records.0.person.linked')
                ->assertJsonMissingPath('civil_records.0.requests')
                ->assertDontSee('private-not-for-lookup.jpg');
        }
        $this->get($portraitUrl)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        Storage::disk('attachments')->delete('mock-registry/portraits/lookup-fixture.jpg');
        $this->get($portraitUrl)->assertNotFound();
        $this->actingAs(User::factory()->create(['role' => 'employee']))
            ->postJson($url, ['query' => '99010000901'])->assertForbidden();
        $this->get($portraitUrl)->assertForbidden();
    }

    public function test_production_refuses_even_when_flag_enabled(): void
    {
        $this->app['env'] = 'production';
        $this->actingAs($this->staff())->get('/integrations/civil-registry-demo')->assertNotFound();
        $this->post('/integrations/civil-registry-demo', ['national_id' => '99000000001', 'name' => 'test'])->assertNotFound();
        $this->assertFalse(LocalCivilRegistryProvider::enabled());
    }

    public function test_disabled_local_mode_and_invalid_inputs_are_rejected(): void
    {
        $this->actingAs($this->staff());
        $this->post('/integrations/civil-registry-demo', ['national_id' => '<script>', 'name' => 'test'])->assertSessionHasErrors('national_id');
        config(['civil_registry_demo.enabled' => false]);
        $this->get('/integrations/civil-registry-demo')->assertNotFound();
    }
}
