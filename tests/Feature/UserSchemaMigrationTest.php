<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserSchemaMigrationTest extends TestCase
{
    public function test_additive_migration_preserves_legacy_accounts_and_can_run_twice(): void
    {
        $previous = config('database.default');
        config(['database.connections.legacy_auth_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], 'database.default' => 'legacy_auth_test']);
        try {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('password');
            });
            DB::table('users')->insert(['name' => 'Existing account', 'email' => 'legacy@example.test', 'password' => 'existing-hash']);
            $migration = require database_path('migrations/2026_10_21_000001_complete_user_authentication_fields.php');
            $migration->up();
            $migration->up();
            $user = DB::table('users')->first();
            $this->assertSame(1, DB::table('users')->count());
            $this->assertSame('existing-hash', $user->password);
            $this->assertSame('legacy@example.test', $user->email);
            $this->assertSame('citizen', $user->role);
            $this->assertEquals(1, $user->is_active);
            $this->assertTrue(Schema::hasColumns('users', ['username', 'role', 'phone', 'is_active']));
        } finally {
            DB::disconnect('legacy_auth_test');
            config(['database.default' => $previous]);
        }
    }
}
