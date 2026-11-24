<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SimulationCenterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge();
        if (DB::connection()->getDatabaseName() !== ':memory:') {
            throw new \RuntimeException('Isolated database required.');
        }
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
    }

    public function test_center_is_admin_only(): void
    {
        $this->get('/admin/simulations')->assertRedirect('/login');
        foreach (['citizen', 'employee'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get('/admin/simulations')->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/admin/simulations')
            ->assertRedirect(route('admin.dashboard', ['tools' => 'home']));
    }

    public function test_disabled_systems_have_explanation_without_access_links(): void
    {
        config(['demo_civil_registry.enabled' => false, 'citizen_mock.enabled' => false,
            'postal_network.enabled' => false, 'identity_card.fingerprint.simulation_enabled' => false,
            'civil_registry_demo.enabled' => false]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/admin/dashboard?tools=home')
            ->assertOk()->assertDontSee('data-ops-view-button="civil-check"', false)
            ->assertDontSee('href="'.route('admin.postal-network.index').'"', false)
            ->assertDontSee('href="'.route('admin.mock-civil-registry.index').'"', false);
    }

    public function test_enabled_systems_link_to_existing_pages_and_admin_tools_use_the_left_panel(): void
    {
        config(['demo_civil_registry.enabled' => true, 'citizen_mock.enabled' => true, 'postal_network.enabled' => true]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/admin/dashboard')->assertOk()
            ->assertSee('href="'.route('admin.postal-network.index').'"', false)
            ->assertSee('href="'.route('admin.demo-civil-registry.index').'"', false)
            ->assertSee('id="operations-tools"', false)
            ->assertDontSee('id="sim-drawer-toggle"', false)
            ->assertSee('data-ops-registry="'.route('admin.mock-civil-registry.index', [], false).'"', false)
            ->assertSee('data-ops-section="documents"', false)
            ->assertSee('href="'.route('admin.integrations.index').'"', false)
            ->assertDontSee('href="'.route('admin.simulations.index').'"', false)
            ->assertDontSee('href="'.route('admin.mock-civil-registry.index').'"', false)
            ->assertDontSee('href="'.route('admin.mock-systems.index').'"', false);
    }

    public function test_employee_panel_respects_registry_and_integration_permissions(): void
    {
        config(['demo_civil_registry.enabled' => true, 'citizen_mock.enabled' => true]);
        $employee = User::factory()->create(['role' => 'employee']);
        $this->actingAs($employee)->get('/employee/dashboard')->assertOk()
            ->assertSee('id="operations-tools"', false)
            ->assertDontSee('data-ops-registry=', false)
            ->assertDontSee('data-ops-view="demo"', false)
            ->assertDontSee('href="'.route('admin.integrations.index').'"', false);

        $employee->forceFill(['can_access_demo_civil_registry' => true])->save();
        $this->get('/employee/dashboard')->assertOk()
            ->assertSee('data-ops-view="demo"', false)
            ->assertSee(route('employee.demo-civil-registry.search'))
            ->assertDontSee(route('admin.demo-civil-registry.search'));
    }

    public function test_missing_registry_tables_do_not_produce_broken_links(): void
    {
        config(['demo_civil_registry.enabled' => true]);
        DB::statement('DROP TABLE demo_civil_record_parties');
        // A partially prepared system must not be advertised as ready.
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/admin/dashboard')
            ->assertOk()->assertDontSee('data-ops-view="demo"', false)
            ->assertDontSee('href="'.route('admin.demo-civil-registry.index').'"', false);
    }

    public function test_local_fingerprint_and_verification_experiments_remain_accessible_from_center(): void
    {
        $this->app['env'] = 'local';
        config(['identity_card.fingerprint.simulation_enabled' => true, 'civil_registry_demo.enabled' => true]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/admin/dashboard')
            ->assertOk()->assertSee('data-ops-view-button="fingerprint"', false)
            ->assertSee('href="'.route('identity-workflow.administration.index').'"', false)
            ->assertSee('data-ops-view="civil-check"', false)
            ->assertDontSee('id="sim-civil-modal"', false);
    }

    public function test_integration_monitoring_opens_the_panel(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/admin/integrations')
            ->assertOk()->assertSee('فتح لوحة المحاكاة')
            ->assertSee('سجل العمليات المنقح');
    }
}
