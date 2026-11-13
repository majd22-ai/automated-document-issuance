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
            ->assertOk()->assertSee('مركز أنظمة المحاكاة')->assertSee('محاكاة تعليمية');
    }

    public function test_disabled_systems_have_explanation_without_access_links(): void
    {
        config(['demo_civil_registry.enabled' => false, 'citizen_mock.enabled' => false,
            'postal_network.enabled' => false, 'identity_card.fingerprint.simulation_enabled' => false,
            'civil_registry_demo.enabled' => false]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/admin/simulations')
            ->assertOk()->assertSee('غير مفعّل')
            ->assertDontSee('href="'.route('admin.postal-network.index').'"', false)
            ->assertDontSee('href="'.route('admin.mock-systems.index').'"', false);
    }

    public function test_enabled_systems_link_to_existing_pages_and_sidebar_has_one_center_entry(): void
    {
        config(['demo_civil_registry.enabled' => true, 'citizen_mock.enabled' => true, 'postal_network.enabled' => true]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/admin/simulations')
            ->assertOk()->assertSee('href="'.route('admin.postal-network.index').'"', false)
            ->assertSee('href="'.route('admin.mock-systems.index').'"', false)
            ->assertSee('href="'.route('admin.demo-civil-registry.index').'"', false)
            ->assertSee('التحصيل غير متاح');
        $this->get('/admin/dashboard')->assertOk()
            ->assertSee('href="'.route('admin.simulations.index').'"', false)
            ->assertDontSee('href="'.route('admin.mock-systems.index').'"', false);
    }

    public function test_missing_registry_tables_do_not_produce_broken_links(): void
    {
        config(['demo_civil_registry.enabled' => true]);
        DB::statement('DROP TABLE demo_civil_record_parties');
        // A partially prepared system must not be advertised as ready.
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/admin/simulations')
            ->assertOk()->assertSee('تحتاج تهيئة')
            ->assertDontSee('href="'.route('admin.demo-civil-registry.index').'"', false);
    }

    public function test_local_fingerprint_and_verification_experiments_remain_accessible_from_center(): void
    {
        $this->app['env'] = 'local';
        config(['identity_card.fingerprint.simulation_enabled' => true, 'civil_registry_demo.enabled' => true]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/admin/simulations')
            ->assertOk()->assertSee('href="'.route('identity-workflow.reception').'"', false)
            ->assertSee('href="'.route('identity-workflow.administration.index').'"', false)
            ->assertSee('href="'.route('integrations.civil-registry-demo').'"', false);
    }

    public function test_integration_monitoring_links_to_center(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/admin/integrations')
            ->assertOk()->assertSee('href="'.route('admin.simulations.index').'"', false)
            ->assertSee('سجل العمليات المنقح');
    }
}
