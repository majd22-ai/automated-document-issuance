<?php

namespace Tests\Feature;

use App\Enums\VerificationStatus;
use App\Models\User;
use App\Services\Integrations\LocalCivilRegistryProvider;
use App\Services\Integrations\VerificationContext;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\DB;
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
        $this->actingAs($this->staff('employee'))->get('/integrations/civil-registry-demo')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->post('/integrations/civil-registry-demo', ['national_id' => '99000000001', 'name' => config('civil_registry_demo.records.99000000001.name')])->assertOk()->assertViewHas('outcome', 'match')->assertHeader('Cache-Control', 'no-store, private');
        $this->assertDatabaseCount('integration_attempts', 0);
    }

    public function test_citizen_and_guest_cannot_access_preview(): void
    {
        $this->get('/integrations/civil-registry-demo')->assertRedirect('/login');
        $this->actingAs($this->staff('citizen'))->get('/integrations/civil-registry-demo')->assertForbidden();
        $this->post('/integrations/civil-registry-demo', ['national_id' => '99000000001', 'name' => 'test'])->assertForbidden();
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
