<?php

namespace Tests\Integration;

use App\Enums\ServiceRequestStatus;
use App\Models\CitizenDocument;
use App\Models\GovernmentService;
use App\Models\ServiceReviewPermission;
use App\Models\User;
use App\Services\Review\ReviewSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class DocumentIssuanceConcurrencyTest extends TestCase
{
    private array $users = [];

    private array $services = [];

    private ?int $ownerId = null;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() !== 'mysql' || ! str_ends_with(DB::connection()->getDatabaseName(), '_testing')) {
            $this->markTestSkipped('Explicit MySQL testing database required');
        }
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
    }

    protected function tearDown(): void
    {
        if ($this->ownerId) {
            $ids = DB::table('citizen_documents')->where('user_id', $this->ownerId)->pluck('id');
            DB::table('document_events')->whereIn('citizen_document_id', $ids)->delete();
            DB::table('document_copies')->whereIn('citizen_document_id', $ids)->delete();
            DB::table('document_deliveries')->whereIn('citizen_document_id', $ids)->delete();
            foreach ($ids->sortDesc() as $id) {
                DB::table('citizen_documents')->where('id', $id)->delete();
            }
            $requestIds = DB::table('citizen_service_requests')->where('user_id', $this->ownerId)->pluck('id');
            DB::table('review_decisions')->whereIn('citizen_service_request_id', $requestIds)->delete();
            DB::table('citizen_service_requests')->whereIn('id', $requestIds)->delete();
            config(['filesystems.disks.documents.root' => storage_path('framework/testing/issuance-race-'.$this->ownerId)]);
            Storage::forgetDisk('documents');
            Storage::disk('documents')->deleteDirectory('copies');
        }
        foreach ($this->services as $service) {
            $service->assessments()->delete();
            DB::table('service_review_permissions')->where('government_service_id', $service->id)->delete();
            DB::table('government_services')->where('id', $service->id)->delete();
        }
        DB::table('users')->whereIn('id', $this->users)->delete();
        parent::tearDown();
    }

    private function fixtures(bool $renewal = false): array
    {
        $people = [];
        foreach (['citizen', 'employee', 'employee'] as $role) {
            $user = User::factory()->create(['username' => 'ISSUANCE-RACE-'.Str::uuid()]);
            $user->forceFill(['role' => $role])->save();
            $people[] = $user;
            $this->users[] = $user->id;
        }
        $this->ownerId = $people[0]->id;
        $requests = [];
        $rules = ['enabled' => true, 'source_reference' => 'TEST ONLY', 'expiry' => ['required' => true, 'validity_days' => 30], 'include_year' => true, 'electronic_copy' => 'internal_html', 'citizen_download' => true, 'delivery' => ['methods' => ['pickup'], 'centers' => ['TEST center']], 'completion_stage' => 'delivery'];
        $previous = $renewal ? $people[0]->documents()->create(['document_type' => 'TEST identity', 'document_type_code' => 'identity', 'document_number' => 'RACE-OLD-'.Str::uuid(), 'status' => 'active', 'issued_at' => today()->subYear(), 'expires_at' => today()->subDay()]) : null;
        for ($i = 0; $i < ($renewal ? 2 : 1); $i++) {
            $service = GovernmentService::create(['code' => 'issuance-race-'.Str::uuid(), 'name' => 'TEST concurrent issuance', 'document_type' => 'identity', 'operation' => $renewal ? 'renewal' : 'issue', 'renewal_statuses' => ['active'], 'is_active' => true, 'rules_approved' => true, 'source_reference' => 'TEST ONLY', 'issuance_rules' => $rules]);
            $service->refresh();
            $this->services[] = $service;
            foreach (array_slice($people, 1) as $employee) {
                (new ServiceReviewPermission)->forceFill(['user_id' => $employee->id, 'government_service_id' => $service->id, 'can_review' => true, 'can_approve' => true, 'can_issue' => true, 'granted_by' => $employee->id])->save();
            }
            $service->assessments()->create(['user_id' => $people[0]->id, 'rules_version' => $service->rules_version, 'identity_verified' => true, 'conditions_met' => true, 'verification_reference' => 'TEST ONLY', 'reviewed_by' => $people[1]->id, 'reviewed_at' => now()]);
            $request = $people[0]->serviceRequests()->create(['reference' => 'ISSUANCE-RACE-'.Str::uuid(), 'service_code' => $service->code, 'status' => 'submitted']);
            $request = $request->transitionTo(ServiceRequestStatus::UnderReview, $people[1]);
            $request = $request->transitionTo(ServiceRequestStatus::Accepted, $people[1]);
            $requests[] = $request->transitionTo(ServiceRequestStatus::Approved, $people[1]);
        }

        return [$people, $requests, $previous];
    }

    private function race(array $actors, array $requests, ?CitizenDocument $previous = null, bool $sameToken = false): array
    {
        $release = (string) (microtime(true) + 2);
        $processes = [];
        $token = (string) Str::uuid();
        foreach ($actors as $i => $actor) {
            $request = $requests[$i];
            $data = ['operation_token' => $sameToken ? $token : (string) Str::uuid(), 'expected_version' => $request->review_version, 'expected_rules_version' => $request->service->rules_version, 'expected_fingerprint' => app(ReviewSnapshot::class)->fingerprint($request)];
            if ($previous) {
                $data['previous_document_id'] = $previous->id;
            }
            $process = new Process([PHP_BINARY, base_path('tests/Support/document-issuance-worker.php'), (string) $actor->id, (string) $request->id, base64_encode(json_encode($data, JSON_THROW_ON_ERROR)), $release], base_path());
            $process->setTimeout(45);
            $process->start();
            $processes[] = $process;
        }
        $results = [];
        foreach ($processes as $process) {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
            $results[] = json_decode($process->getOutput(), true, 16, JSON_THROW_ON_ERROR);
        }

        return $results;
    }

    public function test_competing_employees_issue_one_document_for_the_same_request(): void
    {
        [$people,$requests] = $this->fixtures();
        $results = array_column($this->race([$people[1], $people[2]], [$requests[0], $requests[0]]), 'result');
        sort($results);
        $this->assertSame(['blocked', 'saved'], $results);
        $this->assertSame(1, CitizenDocument::where('user_id', $this->ownerId)->count());
        $this->assertSame(1, $requests[0]->fresh()->issuedDocument->events()->where('action', 'issued')->count());
    }

    public function test_parallel_identical_issuance_is_idempotent(): void
    {
        [$people,$requests] = $this->fixtures();
        $results = $this->race([$people[1], $people[1]], [$requests[0], $requests[0]], sameToken: true);
        $this->assertSame(['saved', 'saved'], array_column($results, 'result'));
        $this->assertSame($results[0]['id'], $results[1]['id']);
        $this->assertSame(1, CitizenDocument::where('user_id', $this->ownerId)->count());
    }

    public function test_competing_renewals_of_one_prior_document_cannot_create_two_successors(): void
    {
        [$people,$requests,$previous] = $this->fixtures(true);
        $results = array_column($this->race([$people[1], $people[2]], $requests, $previous), 'result');
        sort($results);
        $this->assertSame(['blocked', 'saved'], $results);
        $this->assertSame(1,CitizenDocument::where('previous_document_id',$previous->id)->count());
        $this->assertSame('superseded',$previous->fresh()->status);
    }
}
