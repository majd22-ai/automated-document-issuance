<?php

namespace Tests\Feature;

use App\Contracts\RegistrationSms;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserAuthenticationTest extends TestCase
{
    private object $sms;

    protected function setUp(): void
    {
        parent::setUp();
        // Never reset a database. Tests use an isolated memory/test database,
        // apply pending migrations, then roll back only their own account data.
        $connection = DB::connection();
        if ($connection->getDatabaseName() !== ':memory:' && ! str_ends_with($connection->getDatabaseName(), '_testing')) {
            throw new \RuntimeException('Authentication tests require an isolated testing database.');
        }
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $connection->beginTransaction();
        $this->beforeApplicationDestroyed(function () use ($connection) {
            $connection->rollBack();
            $connection->disconnect();
        });
        $this->sms = new class implements RegistrationSms
        {
            public string $code = '';

            public function send(string $phone, string $code): void
            {
                $this->code = $code;
            }
        };
        $this->app->instance(RegistrationSms::class, $this->sms);
        $this->withSession(['registration_captcha' => ['hash' => Hash::make('ABC234'), 'expires' => now()->addMinutes(5)->timestamp]]);
    }

    public function test_login_page_is_never_cached_with_an_old_csrf_token(): void
    {
        $this->get('/login')->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertSee('name="_token"', false);
    }

    public function test_local_recovery_requires_code_then_password_confirmation_and_returns_to_login(): void
    {
        $this->app['env'] = 'local';
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config(['auth.local_password_recovery' => true]);
        $user = $this->user();
        $version = $user->auth_version;
        $this->from('/forgot-password')->post('/forgot-password', ['email' => $user->email])->assertRedirect('/forgot-password');
        $encrypted = session('local_recovery_preview');
        $this->assertNotEmpty($encrypted);
        $preview = json_decode(Crypt::decryptString($encrypted), true);
        $this->assertSame($user->email, $preview['email']);
        $this->assertTrue(Hash::check('password', $user->fresh()->password));
        $this->get('/forgot-password')->assertOk()->assertSee($preview['code'])->assertSee('recovery-preview')->assertHeader('Cache-Control', 'no-store, private');
        $this->get('/forgot-password')->assertDontSee('id="recovery-preview"', false);
        $password = 'NewLocalPassword123!';
        $this->post('/forgot-password/local/reset', ['password' => $password, 'password_confirmation' => $password])->assertSessionHasErrors('recovery');
        $this->post('/forgot-password/local/verify', ['code' => $preview['code']])->assertRedirect('/forgot-password');
        $this->get('/forgot-password')->assertSee('name="password_confirmation"', false);
        $this->post('/forgot-password/local/reset', ['password' => $password, 'password_confirmation' => 'Different'])->assertSessionHasErrors('password');
        $this->post('/forgot-password/local/reset', ['password' => $password, 'password_confirmation' => $password])->assertRedirect('/login');
        $this->assertTrue(Hash::check($password, $user->fresh()->password));
        $this->assertFalse(Hash::check('password', $user->fresh()->password));
        $this->assertGreaterThan($version, $user->fresh()->auth_version);
        $this->assertNull(session('local_recovery'));
        $this->post('/forgot-password/local/reset', ['password' => 'OtherLocalPassword123!', 'password_confirmation' => 'OtherLocalPassword123!'])->assertSessionHasErrors('recovery');
        $this->post(route('login.post'), ['identifier' => $user->email, 'password' => 'password'])->assertSessionHasErrors('identifier');
        $this->post(route('login.post'), ['identifier' => $user->email, 'password' => $password])->assertRedirect(route('citizen.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_production_never_displays_or_resets_local_password_even_with_flag_enabled(): void
    {
        $this->app['env'] = 'production';
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config(['auth.local_password_recovery' => true]);
        $user = $this->user();
        $original = $user->password;
        $this->post('/forgot-password', ['email' => $user->email])->assertRedirect();
        $this->assertNull(session('local_recovery_password'));
        $this->assertNull(session('local_recovery_preview'));
        $this->post('/forgot-password/local/verify', ['code' => '123456'])->assertNotFound();
        $this->assertSame($original, $user->fresh()->password);
    }

    public function test_local_recovery_does_not_enable_disabled_or_missing_accounts(): void
    {
        $this->app['env'] = 'local';
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config(['auth.local_password_recovery' => true]);
        $user = $this->user('citizen', false);
        $original = $user->password;
        foreach ([$user->email, 'not-found@example.test'] as $email) {
            $this->post('/forgot-password', ['email' => $email])->assertRedirect()->assertSessionHasErrors('email')->assertSessionMissing('status');
            $this->assertSame($email, session()->getOldInput('email'));
            $this->assertNull(session('local_recovery_password'));
            $this->assertNull(session('local_recovery'));
        }
        $this->assertSame($original, $user->fresh()->password);
    }

    public function test_local_recovery_rejects_expired_and_incorrect_codes_without_changing_password(): void
    {
        $this->app['env'] = 'local';
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config(['auth.local_password_recovery' => true]);
        $user = $this->user();
        $original = $user->password;
        $this->post('/forgot-password', ['email' => $user->email]);
        $preview = json_decode(Crypt::decryptString(session('local_recovery_preview')), true);
        $wrong = $preview['code'] === '111111' ? '222222' : '111111';
        for ($i = 0; $i < 5; $i++) {
            $this->post('/forgot-password/local/verify', ['code' => $wrong])->assertSessionHasErrors('code');
        }
        $this->assertNull(session('local_recovery'));
        $this->travel(2)->minutes();
        $this->post('/forgot-password', ['email' => $user->email]);
        $preview = json_decode(Crypt::decryptString(session('local_recovery_preview')), true);
        $this->travel(11)->minutes();
        $this->post('/forgot-password/local/verify', ['code' => $preview['code']])->assertSessionHasErrors('recovery');
        $this->assertSame($original, $user->fresh()->password);
    }

    private function registration(array $overrides = []): array
    {
        return array_merge([
            'name' => 'مواطن تجريبي', 'national_id' => '1000000001', 'username' => 'citizen_test', 'identity_path' => 'has_identity',
            'email' => 'citizen@example.test', 'phone' => '777123456', 'confirmed_phone' => '777123456', 'terms' => '1', 'captcha' => 'ABC234',
            'password' => 'SecurePassword123!', 'password_confirmation' => 'SecurePassword123!',
        ], $overrides);
    }

    private function user(string $role = 'citizen', bool $active = true): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role, 'is_active' => $active, 'username' => 'ID'.$user->id])->save();

        return $user;
    }

    public function test_citizen_registration_hashes_password_and_preserves_session_login(): void
    {
        $this->post(route('register.post'), $this->registration())->assertRedirect(route('register.verify'));
        $this->assertGuest();
        $this->post(route('register.verify.post'), ['code' => $this->sms->code])->assertRedirect(route('register.complete'));
        $user = User::where('email', 'citizen@example.test')->firstOrFail();
        $this->assertSame('citizen', $user->role);
        $this->assertTrue($user->is_active);
        $this->assertSame('+967777123456', $user->phone);
        $this->assertSame('citizen_test', $user->username);
        $this->assertSame('1000000001', $user->national_id);
        $this->assertNotSame('SecurePassword123!', $user->password);
        $this->assertTrue(Hash::check('SecurePassword123!', $user->password));
        $this->assertArrayNotHasKey('password', $user->toArray());
        $this->assertArrayNotHasKey('remember_token', $user->toArray());
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_rejects_duplicate_email_and_national_id(): void
    {
        $this->post(route('register.post'), $this->registration());
        $this->post(route('register.verify.post'), ['code' => $this->sms->code]);
        auth()->logout();
        $this->post(route('register.post'), $this->registration())
            ->assertSessionHasErrors(['email', 'national_id']);
        $this->assertSame(1, User::count());
    }

    public function test_registration_validates_password_confirmation_and_phone(): void
    {
        $this->post(route('register.post'), $this->registration(['password_confirmation' => 'different', 'phone' => 'invalid']))
            ->assertSessionHasErrors(['password', 'phone']);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_cannot_set_employee_or_admin_role_or_account_status(): void
    {
        foreach (['admin', 'employee'] as $role) {
            $this->post(route('register.post'), $this->registration(['role' => $role, 'is_active' => true]))
                ->assertSessionHasErrors(['role', 'is_active']);
        }
        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
    }

    public function test_login_accepts_email_and_national_id_with_remember(): void
    {
        $user = $this->user();
        foreach ([$user->email, $user->username] as $identifier) {
            $this->post(route('login.post'), ['identifier' => $identifier, 'password' => 'password', 'remember' => '1'])
                ->assertRedirect(route('citizen.dashboard'));
            $this->assertAuthenticatedAs($user);
            $this->post(route('logout'));
        }
    }

    public function test_login_rejects_wrong_password_and_does_not_flash_it(): void
    {
        $user = $this->user();
        $this->post(route('login.post'), ['identifier' => $user->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors('identifier')->assertSessionMissing('_old_input.password');
        $this->assertGuest();
    }

    public function test_login_validates_missing_credentials(): void
    {
        $this->post(route('login.post'), [])->assertSessionHasErrors(['identifier', 'password']);
        $this->assertGuest();
    }

    public function test_disabled_accounts_cannot_log_in(): void
    {
        $user = $this->user(active: false);
        $this->post(route('login.post'), ['identifier' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('identifier');
        $this->assertGuest();
    }

    public function test_disabled_sessions_are_logged_out_on_next_request(): void
    {
        $user = $this->user();
        $this->actingAs($user);
        $user->forceFill(['is_active' => false])->save();
        $this->get(route('profile.edit'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_logout_ends_authentication_and_invalidates_session_data(): void
    {
        $user = $this->user();
        $this->actingAs($user)->withSession(['private_marker' => 'value'])->post(route('logout'))
            ->assertRedirect(route('home'))->assertSessionMissing('private_marker');
        $this->assertGuest();
        $this->get(route('citizen.dashboard'))->assertRedirect(route('login'));
    }

    public function test_guests_cannot_access_any_protected_area_or_profile(): void
    {
        foreach (['citizen.dashboard', 'employee.dashboard', 'admin.dashboard', 'profile.edit'] as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }
        $this->patch('/profile', ['name' => 'Changed'])->assertRedirect(route('login'));
        $this->post(route('logout'))->assertRedirect(route('login'));
    }

    public function test_roles_cannot_access_other_role_dashboards(): void
    {
        $citizen = $this->user();
        $this->actingAs($citizen)->get(route('citizen.dashboard'))->assertOk();
        $this->get(route('employee.dashboard'))->assertForbidden();
        $this->get(route('admin.dashboard'))->assertForbidden();
        $employee = $this->user('employee');
        $this->actingAs($employee)->get(route('employee.dashboard'))->assertOk();
        $this->get(route('admin.dashboard'))->assertForbidden();
        $admin = $this->user('admin');
        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
    }

    public function test_login_redirects_employee_and_admin_to_their_own_dashboard(): void
    {
        foreach (['employee', 'admin'] as $role) {
            $user = $this->user($role);
            $this->post(route('login.post'), ['identifier' => $user->email, 'password' => 'password'])
                ->assertRedirect(route($role.'.dashboard'));
            $this->post(route('logout'));
        }
    }

    public function test_login_does_not_redirect_to_external_sites_or_unauthorized_areas(): void
    {
        $user = $this->user();
        foreach (['https://example.org', '//example.org', '/admin/dashboard', '/\\example.org', '/login'] as $redirect) {
            $this->post(route('login.post'), ['identifier' => $user->email, 'password' => 'password', 'redirect' => $redirect])
                ->assertRedirect(route('citizen.dashboard'));
            $this->post(route('logout'));
        }
    }

    public function test_login_preserves_safe_local_profile_redirect(): void
    {
        $user = $this->user();
        $this->post(route('login.post'), ['identifier' => $user->email, 'password' => 'password', 'redirect' => '/profile'])
            ->assertRedirect('/profile');
    }

    public function test_profile_displays_and_updates_only_the_authenticated_user(): void
    {
        $user = $this->user();
        $other = $this->user();
        $this->actingAs($user)->get(route('profile.edit'))->assertOk()->assertSee($user->email);
        $this->patch(route('profile.update'), ['name' => 'الاسم الجديد', 'email' => 'new@example.test', 'phone' => '777123456'])
            ->assertRedirect(route('profile.edit'))->assertSessionHas('status');
        $this->assertSame('الاسم الجديد', $user->fresh()->name);
        $this->assertNull($user->fresh()->email_verified_at);
        $this->assertSame($other->email, $other->fresh()->email);
    }

    public function test_profile_rejects_privilege_and_ownership_fields(): void
    {
        $user = $this->user();
        $other = $this->user();
        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => 'Intruder', 'email' => $user->email, 'role' => 'admin', 'is_active' => false,
            'id' => $other->id, 'user_id' => $other->id,
        ])->assertSessionHasErrors(['role', 'is_active', 'id', 'user_id']);
        $this->assertSame('citizen', $user->fresh()->role);
        $this->assertNotSame('Intruder', $other->fresh()->name);
        $this->patch('/profile/'.$other->id, ['name' => 'Intruder'])->assertNotFound();
        $this->patch(route('admin.users.update', $other), ['role' => 'admin', 'is_active' => '1'])->assertForbidden();
    }

    public function test_profile_email_must_be_unique_and_existing_email_can_be_kept(): void
    {
        $user = $this->user();
        $other = $this->user();
        $this->actingAs($user)->patch(route('profile.update'), ['name' => $user->name, 'email' => $other->email])
            ->assertSessionHasErrors('email');
        $this->patch(route('profile.update'), ['name' => $user->name, 'email' => $user->email])->assertSessionHasNoErrors();
    }

    public function test_admin_can_manage_roles_and_account_status_and_invalid_roles_are_rejected(): void
    {
        $admin = $this->user('admin');
        $user = $this->user();
        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk()->assertSee($user->email);
        $this->patch(route('admin.users.update', $user), ['role' => 'employee', 'is_active' => '0'])
            ->assertRedirect(route('admin.users.index'));
        $this->assertSame('employee', $user->fresh()->role);
        $this->assertFalse($user->fresh()->is_active);
        $this->patch(route('admin.users.update', $user), ['role' => 'superadmin', 'is_active' => '1'])
            ->assertSessionHasErrors('role');
        $this->assertSame('employee', $user->fresh()->role);
    }

    public function test_admin_cannot_disable_or_demote_their_own_account(): void
    {
        $admin = $this->user('admin');
        $this->actingAs($admin)->patch(route('admin.users.update', $admin), ['role' => 'citizen', 'is_active' => '0'])
            ->assertSessionHasErrors('role');
        $this->assertTrue($admin->fresh()->is_active);
        $this->assertSame('admin', $admin->fresh()->role);
    }

    public function test_admin_listing_loads_only_display_fields_and_preserves_search_and_pagination(): void
    {
        $admin = $this->user('admin');
        User::factory()->count(16)->create(['name' => 'Listing match']);
        $response = $this->actingAs($admin)->get(route('admin.users.index', ['search' => 'Listing match', 'page' => 2]));
        $response->assertOk();
        $users = $response->viewData('users');
        $this->assertSame(16, $users->total());
        $this->assertSame(2, $users->currentPage());
        $this->assertCount(1, $users->items());
        $this->assertStringContainsString('search=Listing', $users->previousPageUrl());
        $attributes = $users->first()->getAttributes();
        $this->assertEqualsCanonicalizing(['id', 'name', 'username', 'email', 'role', 'is_active'], array_keys($attributes));
        $this->assertArrayNotHasKey('password', $attributes);
        $this->assertArrayNotHasKey('remember_token', $attributes);
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->post(route('login.post'), ['identifier' => 'unknown@example.test', 'password' => 'wrong-password']);
        }
        $this->post(route('login.post'), ['identifier' => 'unknown@example.test', 'password' => 'wrong-password'])->assertStatus(429);
    }

    public function test_existing_public_pages_remain_accessible(): void
    {
        foreach (['/', '/login', '/register', '/services/identity', '/services/passports', '/services/driving-licenses', '/appointments/book', '/contact'] as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_real_request_tracking_requires_authentication(): void
    {
        $this->get('/inquiry/request')->assertRedirect(route('login'));
        $this->get('/inquiry/my-requests')->assertRedirect(route('login'));
    }

    public function test_empty_immutable_profile_fields_are_rejected(): void
    {
        $user = $this->user();
        $password = $user->password;
        $username = $user->username;
        foreach ([null, ''] as $value) {
            $this->actingAs($user)->patch(route('profile.update'), [
                'name' => $user->name, 'email' => $user->email,
                'username' => $value, 'password' => $value, 'role' => $value, 'is_active' => $value,
            ])->assertSessionHasErrors(['username', 'password', 'role', 'is_active']);
        }
        $this->assertSame($username, $user->fresh()->username);
        $this->assertSame($password, $user->fresh()->password);
    }

    public function test_login_returns_to_original_protected_page(): void
    {
        $user = $this->user();
        $this->get('/profile')->assertRedirect(route('login'));
        $this->post(route('login.post'), ['identifier' => $user->email, 'password' => 'password'])
            ->assertRedirect('/profile');
    }
}
