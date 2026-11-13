<?php

namespace Tests\Feature;

use App\Models\PostalCollector;
use App\Models\PostalOffice;
use App\Models\User;
use Database\Seeders\PostalNetworkSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class PostalNetworkTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app['env'] = 'testing';
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'postal_network.enabled' => true, 'postal_network.allow_seed' => true]);
        DB::purge();
        $connection = DB::connection();
        if ($connection->getDriverName() !== 'sqlite' || $connection->getDatabaseName() !== ':memory:') {
            throw new \RuntimeException('Postal tests require in-memory SQLite.');
        }
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $connection->beginTransaction();
        $this->beforeApplicationDestroyed(function () use ($connection) {
            while ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }
            $connection->disconnect();
        });
        foreach (['SANA-001', 'SANA-002', 'SANA-003'] as $code) {
            config(['postal_network.passwords.'.$code => Str::random(32).'aA9!']);
        }
    }

    private function seedNetwork(): void
    {
        app(PostalNetworkSeeder::class)->run();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'is_active' => true, 'username' => 'admin_'.Str::lower(Str::random(12))]);
    }

    public function test_disabled_routes_are_unavailable(): void
    {
        config(['postal_network.enabled' => false]);
        DB::statement('DROP TABLE postal_collectors');
        DB::statement('DROP TABLE postal_offices');
        $this->get('/postal/login')->assertNotFound();
        $this->get('/postal')->assertNotFound();
        $this->actingAs($this->admin())->get('/admin/postal-network')->assertNotFound();
    }

    public function test_seeder_is_idempotent_and_leaves_existing_government_users_and_grants_untouched(): void
    {
        $this->admin();
        $users = DB::table('users')->get()->toJson();
        $grants = DB::table('service_review_permissions')->get()->toJson();
        $this->seedNetwork();
        $collector = PostalCollector::firstOrFail();
        $password = config('postal_network.passwords.'.$collector->office->code);
        $hash = $collector->password;
        $collector->update(['is_active' => false, 'can_collect' => false]);
        config(['postal_network.passwords' => []]);
        $this->seedNetwork();
        $this->assertSame(3, PostalOffice::count());
        $this->assertSame(3, PostalCollector::count());
        $this->assertSame(3, PostalOffice::where('is_demo', true)->count());
        $this->assertSame($hash, $collector->fresh()->password);
        $this->assertFalse($collector->fresh()->is_active);
        $this->assertFalse($collector->fresh()->can_collect);
        $this->assertSame($collector->postal_office_id, $collector->office->id);
        $this->assertTrue($collector->office->collectors->contains($collector));
        $this->assertSame($users, DB::table('users')->get()->toJson());
        $this->assertSame($grants, DB::table('service_review_permissions')->get()->toJson());
        $this->assertTrue(Hash::check($password, $hash));
    }

    public function test_seeder_refuses_missing_passwords_without_partial_data(): void
    {
        config(['postal_network.passwords.SANA-003' => null]);
        try {
            $this->seedNetwork();
            $this->fail('Missing credentials must refuse seeding.');
        } catch (\RuntimeException $e) {
            $this->assertSame(0, PostalOffice::count());
            $this->assertSame(0, PostalCollector::count());
        }
    }

    public function test_seeder_requires_explicit_opt_in_and_nonproduction_environment(): void
    {
        config(['postal_network.allow_seed' => false]);
        try {
            $this->seedNetwork();
            $this->fail('Must opt in.');
        } catch (\RuntimeException $e) {
            $this->assertSame(0, PostalOffice::count());
        }
        config(['postal_network.allow_seed' => true]);
        $this->app['env'] = 'production';
        try {
            $this->seedNetwork();
            $this->fail('Must reject production.');
        } catch (\RuntimeException $e) {
            $this->assertSame(0, PostalOffice::count());
        }
        $this->get('/postal/login')->assertNotFound();
    }

    public function test_only_administrators_manage_the_network(): void
    {
        $this->get('/admin/postal-network')->assertRedirect('/login');
        foreach (['citizen', 'employee'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => true]))
                ->get('/admin/postal-network')->assertForbidden();
        }
        $this->actingAs($this->admin())->get('/admin/postal-network')->assertOk()->assertSee('بيانات تجريبية');
    }

    public function test_admin_creates_offices_and_isolated_collectors_and_rejects_duplicates(): void
    {
        $this->actingAs($this->admin());
        $office = ['name' => 'مكتب تجريبي', 'code' => 'SANA-004', 'area' => 'صنعاء', 'address' => 'عنوان تعليمي', 'status' => 'active'];
        $this->post('/admin/postal-network/offices', $office)->assertRedirect('/admin/postal-network');
        $this->post('/admin/postal-network/offices', $office)->assertSessionHasErrors('code');
        $officeId = PostalOffice::firstOrFail()->id;
        $password = Str::random(32).'aA9!';
        $account = ['name' => 'محصل تجريبي', 'username' => 'postal_test', 'postal_office_id' => $officeId,
            'password' => $password, 'password_confirmation' => $password, 'is_active' => '1', 'can_collect' => '1', 'role' => 'admin'];
        $this->post('/admin/postal-network/collectors', $account)->assertRedirect('/admin/postal-network');
        $this->post('/admin/postal-network/collectors', $account)->assertSessionHasErrors('username');
        $this->assertSame(1, User::count());
        $collector = PostalCollector::firstOrFail();
        $this->assertTrue(Hash::check($password, $collector->password));
        $this->assertArrayNotHasKey('role', $collector->getAttributes());
        $this->assertTrue(PostalOffice::first()->is_demo);
        $account['username'] = 'postal_other';
        $account['postal_office_id'] = 999999;
        $this->post('/admin/postal-network/collectors', $account)->assertSessionHasErrors('postal_office_id');
    }

    public function test_collector_login_is_separate_and_cannot_enter_government_routes(): void
    {
        $this->seedNetwork();
        $collector = PostalCollector::firstOrFail();
        $this->post('/postal/login', ['username' => $collector->username,
            'password' => config('postal_network.passwords.'.$collector->office->code)])
            ->assertRedirect('/postal');
        $this->assertAuthenticatedAs($collector, 'postal');
        $this->assertGuest('web');
        $this->get('/postal')->assertOk()->assertSee('محاكاة تعليمية');
        foreach (['/admin/dashboard', '/employee/dashboard', '/admin/postal-network', '/profile'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
        $this->assertSame(0, DB::table('audit_events')->where('event', 'auth.login')->count());
        $this->post('/postal/logout')->assertRedirect('/postal/login');
        $this->assertGuest('postal');
    }

    public function test_government_credentials_cannot_authenticate_postal_guard(): void
    {
        $admin = $this->admin();
        $password = Str::random(32).'aA9!';
        $admin->forceFill(['password' => $password])->save();
        $this->post('/postal/login', ['username' => $admin->username, 'password' => $password])->assertSessionHasErrors('username');
        $this->assertGuest('postal');
    }

    public function test_disabled_collector_office_or_permission_cannot_log_in(): void
    {
        $this->seedNetwork();
        $collector = PostalCollector::firstOrFail();
        $credentials = ['username' => $collector->username, 'password' => config('postal_network.passwords.'.$collector->office->code)];
        foreach (['is_active', 'can_collect'] as $field) {
            $collector->update([$field => false]);
            $this->post('/postal/login', $credentials)->assertSessionHasErrors('username');
            $this->assertGuest('postal');
            $collector->update([$field => true]);
        }
        $collector->office->update(['status' => 'inactive']);
        $this->post('/postal/login', $credentials)->assertSessionHasErrors('username');
        $this->assertGuest('postal');
    }

    public function test_existing_sessions_are_revoked_after_account_or_office_changes(): void
    {
        $this->seedNetwork();
        $collector = PostalCollector::firstOrFail();
        $this->post('/postal/login', ['username' => $collector->username, 'password' => config('postal_network.passwords.'.$collector->office->code)])->assertRedirect('/postal');
        $collector->update(['is_active' => false]);
        $this->get('/postal')->assertRedirect('/postal/login');
        $this->assertGuest('postal');
    }

    public function test_admin_edit_revokes_sessions_and_can_disable_an_office(): void
    {
        $this->seedNetwork();
        $collector = PostalCollector::firstOrFail();
        $this->actingAs($this->admin());
        $version = $collector->auth_version;
        $this->patch('/admin/postal-network/collectors/'.$collector->id, ['name' => $collector->name,
            'username' => $collector->username, 'postal_office_id' => PostalOffice::where('id', '!=', $collector->postal_office_id)->first()->id,
            'is_active' => '1', 'can_collect' => '0'])->assertRedirect('/admin/postal-network');
        $this->assertGreaterThan($version, $collector->fresh()->auth_version);
        $office = PostalOffice::firstOrFail();
        $this->patch('/admin/postal-network/offices/'.$office->id, ['name' => $office->name, 'code' => $office->code,
            'area' => $office->area, 'address' => $office->address, 'status' => 'inactive'])->assertRedirect('/admin/postal-network');
        $this->assertSame('inactive', $office->fresh()->status);
    }

    public function test_postal_credentials_cannot_log_in_as_government_users(): void
    {
        $this->seedNetwork();
        $collector = PostalCollector::firstOrFail();
        $this->post('/login', ['identifier' => $collector->username,
            'password' => config('postal_network.passwords.'.$collector->office->code)])
            ->assertSessionHasErrors('identifier');
        $this->assertGuest('web');
    }

    public function test_office_disable_and_reenable_does_not_restore_an_old_session(): void
    {
        $this->seedNetwork();
        $collector = PostalCollector::firstOrFail();
        $this->post('/postal/login', ['username' => $collector->username,
            'password' => config('postal_network.passwords.'.$collector->office->code)])->assertRedirect('/postal');
        $office = $collector->office;
        $office->update(['status' => 'inactive']);
        $office->update(['status' => 'active']);
        $this->get('/postal')->assertRedirect('/postal/login');
        $this->assertGuest('postal');
    }

    public function test_password_rotation_office_transfer_and_permission_revocation_expire_sessions(): void
    {
        $this->seedNetwork();
        $collector = PostalCollector::firstOrFail();
        $password = config('postal_network.passwords.'.$collector->office->code);
        foreach (['password', 'postal_office_id', 'can_collect'] as $field) {
            $this->post('/postal/login', ['username' => $collector->username, 'password' => $password])->assertRedirect('/postal');
            $value = match ($field) {
                'password' => Str::random(32).'aA9!',
                'postal_office_id' => PostalOffice::where('id', '!=', $collector->postal_office_id)->first()->id,
                'can_collect' => false,
            };
            $collector->update([$field => $value]);
            if ($field === 'password') {
                $password = $value;
            }
            $this->get('/postal')->assertRedirect('/postal/login');
            $this->assertGuest('postal');
        }
    }

    public function test_seed_conflict_rolls_back_all_fixtures(): void
    {
        $office = PostalOffice::create(['name' => 'مكتب للاختبار', 'code' => 'SANA-999', 'area' => 'صنعاء', 'address' => 'تجريبي', 'status' => 'active']);
        PostalCollector::create(['name' => 'حساب سابق', 'username' => 'postal_hadda_demo', 'postal_office_id' => $office->id,
            'password' => Str::random(32).'aA9!', 'is_active' => true, 'can_collect' => false]);
        try {
            $this->seedNetwork();
            $this->fail('Must not adopt an existing account.');
        } catch (\RuntimeException $e) {
            $this->assertSame(1, PostalOffice::count());
            $this->assertSame(1, PostalCollector::count());
            $this->assertSame(0, PostalCollector::whereNotNull('fixture_key')->count());
        }
    }

    public function test_database_unique_constraints_and_foreign_key_restrict_duplicates_and_office_deletion(): void
    {
        $this->seedNetwork();
        $office = PostalOffice::firstOrFail();
        foreach (['office', 'collector', 'delete'] as $operation) {
            try {
                DB::transaction(function () use ($operation, $office) {
                    if ($operation === 'office') {
                        DB::table('postal_offices')->insert(['name' => 'duplicate', 'code' => $office->code, 'area' => 'test', 'address' => 'test']);
                    } elseif ($operation === 'collector') {
                        DB::table('postal_collectors')->insert(['name' => 'duplicate', 'username' => PostalCollector::first()->username,
                            'password' => Hash::make(Str::random(32)), 'postal_office_id' => $office->id]);
                    } else {
                        $office->delete();
                    }
                });
                $this->fail('Expected database integrity constraint.');
            } catch (QueryException $e) {
                $this->assertSame(3, PostalOffice::count());
                $this->assertSame(3, PostalCollector::count());
            }
        }
    }

    public function test_nonadmin_cannot_create_offices_or_collectors(): void
    {
        foreach (['citizen', 'employee'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => true]));
            $this->post('/admin/postal-network/offices', [])->assertForbidden();
            $this->post('/admin/postal-network/collectors', [])->assertForbidden();
        }
        $this->assertSame(0, PostalOffice::count());
        $this->assertSame(0, PostalCollector::count());
    }

    public function test_postal_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->post('/postal/login', ['username' => 'missing', 'password' => Str::random(32)])->assertSessionHasErrors('username');
        }
        $this->post('/postal/login', ['username' => 'missing', 'password' => Str::random(32)])->assertStatus(429);
    }

    public function test_government_login_audit_and_session_survive_postal_login_and_logout(): void
    {
        $admin = $this->admin();
        $password = Str::random(32).'aA9!';
        $admin->forceFill(['password' => $password])->save();
        $this->post('/login', ['identifier' => $admin->username, 'password' => $password])->assertRedirect('/admin/dashboard');
        $this->assertDatabaseHas('audit_events', ['event' => 'auth.login', 'actor_id' => $admin->id]);
        $this->seedNetwork();
        $collector = PostalCollector::firstOrFail();
        $this->post('/postal/login', ['username' => $collector->username,
            'password' => config('postal_network.passwords.'.$collector->office->code)])->assertRedirect('/postal');
        $this->assertAuthenticatedAs($admin, 'web');
        $this->post('/postal/logout')->assertRedirect('/postal/login');
        $this->assertAuthenticatedAs($admin, 'web');
        $this->get('/admin/postal-network')->assertOk();
    }

    public function test_weak_or_bcrypt_truncating_passwords_are_rejected_before_account_creation(): void
    {
        $this->seedNetwork();
        $office = PostalOffice::firstOrFail();
        $this->actingAs($this->admin());
        foreach ([Str::lower(Str::random(20)), Str::random(75).'aA9!', str_repeat('أ', 40).'aA9!'] as $password) {
            $this->post('/admin/postal-network/collectors', ['name' => 'تجريبي', 'username' => 'postal_password_test',
                'postal_office_id' => $office->id, 'is_active' => '1', 'can_collect' => '1',
                'password' => $password, 'password_confirmation' => $password])->assertSessionHasErrors('password');
        }
        $this->assertSame(3, PostalCollector::count());
    }

    public function test_postal_pages_are_not_cached(): void
    {
        $this->get('/postal/login')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    }
}
