<?php

namespace Tests\Integration;

use App\Models\GovernmentService;
use App\Models\ServiceReviewPermission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\CleansAuditFixtures;
use Tests\TestCase;

class ElectronicIntegrationConcurrencyTest extends TestCase
{
    use CleansAuditFixtures;

    private array $users = [];

    private ?GovernmentService $service = null;

    private array $original = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() !== 'mysql' || ! str_ends_with(DB::connection()->getDatabaseName(), '_testing')) {
            $this->markTestSkipped('Explicit isolated MySQL testing database required');
        }
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->beginAuditFixtures();
        $this->service = GovernmentService::where('code', 'identity-first-issue')->firstOrFail();
        $this->original = $this->service->only(['is_active', 'rules_approved', 'source_reference', 'integration_rules']);
        $this->service->update(['is_active' => true, 'rules_approved' => true, 'source_reference' => 'TEST ONLY', 'integration_rules' => ['identity' => ['required' => true, 'provider' => 'test', 'max_age_minutes' => 60]]]);
    }

    protected function tearDown(): void
    {
        $this->cleanAuditFixtures();
        foreach ($this->users as $user) {
            DB::table('citizen_service_requests')->where('user_id', $user->id)->delete();
            DB::table('service_review_permissions')->where('user_id', $user->id)->orWhere('granted_by', $user->id)->delete();
            DB::table('users')->where('id', $user->id)->delete();
        }
        if ($this->service) {
            $this->service->forceFill($this->original)->save();
        }
        $this->cleanAuditFixtures();
        parent::tearDown();
    }

    private function race(bool $sameKey): array
    {
        $citizen = User::factory()->create(['username' => 'INTEGRATION-RACE-'.Str::uuid()]);
        $employee = User::factory()->create(['role' => 'employee']);
        $this->users = [$citizen, $employee];
        $request = $citizen->serviceRequests()->create(['reference' => 'INTEGRATION-RACE-'.Str::uuid(), 'service_code' => $this->service->code, 'status' => 'submitted']);
        (new ServiceReviewPermission)->forceFill(['user_id' => $employee->id, 'government_service_id' => $this->service->id, 'can_verify' => true, 'granted_by' => $employee->id])->save();
        $key = (string) Str::uuid();
        $release = (string) (microtime(true) + 2);
        $processes = [];
        for ($i = 0; $i < 2; $i++) {
            $process = new Process([PHP_BINARY, base_path('tests/Support/integration-worker.php'), (string) $employee->id, (string) $request->id, $sameKey ? $key : (string) Str::uuid(), $release], base_path());
            $process->setTimeout(40);
            $process->start();
            $processes[] = $process;
        }
        $results = [];
        foreach ($processes as $process) {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
            $results[] = json_decode($process->getOutput(), true, 16, JSON_THROW_ON_ERROR);
        }
        $this->assertSame(1, $request->integrationAttempts()->count());
        $this->assertSame('verified', $request->integrationAttempts()->first()->status);
        $calls = array_column($results, 'calls');
        sort($calls);
        $this->assertSame([0, 1], $calls);

        return $results;
    }

    public function test_concurrent_replay_returns_same_attempt_and_only_one_outbound_query(): void
    {
        $results = $this->race(true);
        $this->assertSame($results[0]['id'], $results[1]['id']);
        $this->assertSame([200, 200], array_column($results, 'status'));
    }

    public function test_concurrent_new_operation_is_rejected_until_active_query_finishes(): void
    {
        $results = $this->race(false);
        $statuses = array_column($results, 'status');
        sort($statuses);
        $this->assertSame([200, 409], $statuses);
    }
}
