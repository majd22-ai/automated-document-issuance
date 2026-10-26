<?php

namespace Tests\Integration;

use App\Enums\ServiceRequestStatus;
use App\Models\GovernmentService;
use App\Models\ServiceReviewPermission;
use App\Models\User;
use App\Services\Review\ReviewSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\CleansAuditFixtures;
use Tests\TestCase;

class ApplicationReviewConcurrencyTest extends TestCase
{
    use CleansAuditFixtures;

    private ?GovernmentService $service = null;

    private array $users = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() !== 'mysql' || ! str_ends_with(DB::connection()->getDatabaseName(), '_testing')) {
            $this->markTestSkipped('Explicit MySQL testing database required.');
        }
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->beginAuditFixtures();
    }

    protected function tearDown(): void
    {
        $this->cleanAuditFixtures();
        if ($this->service) {
            $ids = DB::table('citizen_service_requests')->where('government_service_id', $this->service->id)->pluck('id');
            DB::table('request_deficiencies')->whereIn('citizen_service_request_id', $ids)->delete();
            DB::table('review_decisions')->whereIn('citizen_service_request_id', $ids)->delete();
            DB::table('service_review_permissions')->where('government_service_id', $this->service->id)->delete();
            DB::table('citizen_service_requests')->whereIn('id', $ids)->delete();
            $this->service->assessments()->delete();
            DB::table('government_services')->where('id', $this->service->id)->delete();
        }
        DB::table('users')->whereIn('id', $this->users)->delete();
        $this->cleanAuditFixtures();
        parent::tearDown();
    }

    private function fixtures(): array
    {
        $people = [];
        foreach (['citizen', 'employee', 'employee'] as $role) {
            $user = User::factory()->create(['username' => 'REVIEW-RACE-'.Str::uuid()]);
            $user->forceFill(['role' => $role])->save();
            $people[] = $user;
            $this->users[] = $user->id;
        }
        $this->service = GovernmentService::create(['code' => 'review-race-'.Str::uuid(), 'name' => 'TEST review concurrency', 'document_type' => 'identity', 'operation' => 'issue', 'is_active' => true, 'rules_approved' => true, 'source_reference' => 'TEST ONLY']);
        $this->service->refresh();
        foreach (array_slice($people, 1) as $employee) {
            (new ServiceReviewPermission)->forceFill(['user_id' => $employee->id, 'government_service_id' => $this->service->id, 'can_review' => true, 'can_approve' => true, 'granted_by' => $employee->id])->save();
        }
        $this->service->assessments()->create(['user_id' => $people[0]->id, 'rules_version' => $this->service->rules_version, 'identity_verified' => true, 'conditions_met' => true, 'verification_reference' => 'TEST ONLY', 'reviewed_by' => $people[1]->id, 'reviewed_at' => now()]);
        $request = $people[0]->serviceRequests()->create(['reference' => 'REVIEW-RACE-'.Str::uuid(), 'service_code' => $this->service->code, 'status' => 'submitted']);

        return [$people, $request->fresh()];
    }

    private function race(array $actors, $request, string $next): array
    {
        $release = (string) (microtime(true) + 2);
        $processes = [];
        foreach ($actors as $actor) {
            $context = ['expected_version' => $request->review_version, 'expected_rules_version' => $this->service->rules_version, 'expected_fingerprint' => app(ReviewSnapshot::class)->fingerprint($request), 'decision_token' => (string) Str::uuid()];
            $process = new Process([PHP_BINARY, base_path('tests/Support/application-review-worker.php'), (string) $actor->id, (string) $request->id, $next, base64_encode(json_encode($context, JSON_THROW_ON_ERROR)), $release], base_path());
            $process->setTimeout(45);
            $process->start();
            $processes[] = $process;
        }
        $results = [];
        foreach ($processes as $process) {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
            $results[] = json_decode($process->getOutput(), true, 16, JSON_THROW_ON_ERROR)['result'];
        }
        sort($results);

        return $results;
    }

    public function test_two_employees_cannot_claim_the_same_unassigned_request(): void
    {
        [$people,$request] = $this->fixtures();
        $this->assertSame(['blocked', 'saved'], $this->race(array_slice($people, 1), $request, 'under_review'));
        $this->assertSame('under_review', $request->fresh()->status);
        $this->assertContains($request->fresh()->assigned_to, [$people[1]->id, $people[2]->id]);
        $this->assertSame(1, $request->reviewDecisions()->count());
    }

    public function test_competing_approvals_write_one_final_decision(): void
    {
        [$people,$request] = $this->fixtures();
        $request = $request->transitionTo(ServiceRequestStatus::UnderReview, $people[1]);
        $request = $request->transitionTo(ServiceRequestStatus::Accepted, $people[1]);
        $this->assertSame(['blocked', 'saved'], $this->race(array_slice($people, 1), $request, 'approved'));
        $this->assertSame('approved', $request->fresh()->status);
        $this->assertSame(1, $request->reviewDecisions()->where('action', 'approve')->count());
    }
}
