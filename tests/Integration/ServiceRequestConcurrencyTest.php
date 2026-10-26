<?php

namespace Tests\Integration;

use App\Models\GovernmentService;
use App\Models\User;
use App\Services\ServiceRequestSubmission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\CleansAuditFixtures;
use Tests\TestCase;

class ServiceRequestConcurrencyTest extends TestCase
{
    use CleansAuditFixtures;

    private ?GovernmentService $service = null;

    private array $fixtureUsers = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() !== 'mysql' || ! str_ends_with(DB::connection()->getDatabaseName(), '_testing')) {
            $this->markTestSkipped('Explicit MySQL testing database required for parallel process tests.');
        }
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->beginAuditFixtures();
    }

    protected function tearDown(): void
    {
        $this->cleanAuditFixtures();
        // Only remove committed fixtures created by this test, never pre-existing records.
        if ($this->service) {
            DB::table('citizen_service_requests')->where('government_service_id', $this->service->id)->delete();
            DB::table('citizen_service_assessments')->where('government_service_id', $this->service->id)->delete();
            DB::table('government_services')->where('id', $this->service->id)->delete();
        }
        foreach ($this->fixtureUsers as $id) {
            DB::table('users')->where('id', $id)->delete();
        }
        $this->cleanAuditFixtures();
        parent::tearDown();
    }

    private function race(bool $sameToken): array
    {
        $citizen = User::factory()->create(['username' => 'RACE-'.Str::uuid()]);
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        $this->fixtureUsers = [$citizen->id, $admin->id];
        $this->service = GovernmentService::create(['code' => 'race-'.Str::uuid(), 'name' => 'TEST parallel service', 'document_type' => 'identity', 'operation' => 'issue', 'is_active' => true, 'rules_approved' => true, 'source_reference' => 'TEST ONLY']);
        $this->service->refresh();
        $this->service->assessments()->create(['user_id' => $citizen->id, 'rules_version' => $this->service->rules_version, 'identity_verified' => true, 'conditions_met' => true, 'verification_reference' => 'TEST ONLY', 'reviewed_by' => $admin->id, 'reviewed_at' => now()]);
        $preview = app(ServiceRequestSubmission::class)->preview($citizen, $this->service, []);
        $token = (string) Str::uuid();
        $releaseAt = (string) (microtime(true) + 2);
        $processes = [];
        for ($i = 0; $i < 2; $i++) {
            $process = new Process([PHP_BINARY, base_path('tests/Support/service-request-worker.php'), (string) $citizen->id, (string) $this->service->id, $i === 0 || $sameToken ? $token : (string) Str::uuid(), base64_encode(json_encode($preview, JSON_THROW_ON_ERROR)), $releaseAt], base_path());
            $process->setTimeout(30);
            $process->start();
            $processes[] = $process;
        }
        $results = [];
        foreach ($processes as $process) {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
            $results[] = json_decode($process->getOutput(), true, 32, JSON_THROW_ON_ERROR);
        }
        $this->assertSame(1, $citizen->serviceRequests()->count());

        return $results;
    }

    public function test_parallel_identical_confirmation_is_idempotent(): void
    {
        $results = $this->race(true);
        $this->assertSame(['submitted', 'submitted'], array_column($results, 'result'));
        $this->assertSame($results[0]['id'], $results[1]['id']);
    }

    public function test_parallel_distinct_confirmations_cannot_duplicate_active_service(): void
    {
        $results = array_column($this->race(false), 'result');
        sort($results);
        $this->assertSame(['blocked', 'submitted'], $results);
    }
}
