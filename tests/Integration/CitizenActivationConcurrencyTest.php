<?php

namespace Tests\Integration;

use App\Models\CitizenDocument;
use App\Models\GovernmentService;
use App\Models\ServiceReviewPermission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\CleansAuditFixtures;
use Tests\TestCase;

class CitizenActivationConcurrencyTest extends TestCase
{
    use CleansAuditFixtures;

    private array $userIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() !== 'mysql' || ! str_ends_with(DB::connection()->getDatabaseName(), '_testing')) {
            $this->markTestSkipped('Explicit isolated MySQL testing DB required');
        }
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->beginAuditFixtures();
    }

    protected function tearDown(): void
    {
        if ($this->userIds) {
            $this->cleanAuditFixtures();
            DB::table('notifications')->whereIn('user_id', $this->userIds)->delete();
            DB::table('citizen_account_confirmations')->whereIn('user_id', $this->userIds)->delete();
            DB::table('citizen_documents')->whereIn('user_id', $this->userIds)->delete();
            DB::table('citizen_service_requests')->whereIn('user_id', $this->userIds)->delete();
            DB::table('service_review_permissions')->whereIn('user_id', $this->userIds)->delete();
            DB::table('users')->whereIn('id', $this->userIds)->delete();
        }
        parent::tearDown();
    }

    private function user(string $role, bool $initial = false): User
    {
        $user = User::factory()->create(['role' => $role]);
        $this->userIds[] = $user->id;
        if ($role === 'citizen') {
            $user->forceFill(['username' => 'ACT-RACE-'.Str::uuid(), 'registration_status' => $initial ? 'initial_account' : 'identity_verification_pending', 'identity_path' => $initial ? 'no_identity' : 'has_identity', 'national_id' => $initial ? null : (string) (70000000000 + $user->id)])->save();
        }

        return $user;
    }

    private function race(bool $sameToken, bool $competingAccounts = false): array
    {
        $actor = $this->user('employee');
        $citizen = $this->user('citizen', $competingAccounts);
        $service = GovernmentService::where('code', 'identity-first-issue')->firstOrFail();
        (new ServiceReviewPermission)->forceFill(['user_id' => $actor->id, 'government_service_id' => $service->id, 'can_activate' => true, 'granted_by' => $actor->id])->save();
        $citizens = [$citizen, $competingAccounts ? $this->user('citizen', true) : $citizen];
        $token = (string) Str::uuid();
        $release = (string) (microtime(true) + 2);
        $processes = [];
        foreach ($citizens as $index => $owner) {
            $data = ['action' => 'activate', 'national_id' => $owner->national_id, 'identity_confirmed' => true, 'official_issuance_confirmed' => false, 'evidence_reference' => 'TEST-ONLY concurrency official reference', 'operation_token' => $sameToken ? $token : (string) Str::uuid()];
            if ($competingAccounts) {
                $request = $owner->serviceRequests()->create(['reference' => 'ACT-RACE-'.Str::uuid(), 'service_code' => $service->code, 'status' => 'submitted']);
                DB::table('citizen_service_requests')->where('id', $request->id)->update(['request_type' => 'issue', 'status' => 'completed', 'completed_at' => now(), 'completed_by' => $actor->id]);
                (new CitizenDocument)->forceFill(['user_id' => $owner->id, 'government_service_id' => $service->id, 'citizen_service_request_id' => $request->id, 'document_type' => 'TEST-ONLY identity', 'document_type_code' => 'identity', 'document_number' => 'ACT-RACE-'.Str::uuid(), 'status' => 'active', 'issued_at' => today(), 'issued_by' => $actor->id, 'issued_recorded_at' => now()])->save();
                $data = array_replace($data, ['action' => 'confirm_issuance', 'national_id' => '9988112233', 'official_issuance_confirmed' => true, 'request_id' => $request->id]);
            }
            $process = new Process([PHP_BINARY, base_path('tests/Support/activation-worker.php'), (string) $actor->id, (string) $owner->id, base64_encode(json_encode($data)), $release], base_path());
            $process->setTimeout(40);
            $process->start();
            $processes[] = $process;
        }
        $results = [];
        foreach ($processes as $process) {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
            $results[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        }
        $this->assertSame(1, DB::table('citizen_account_confirmations')->whereIn('user_id', $this->userIds)->where('result', 'confirmed')->count());
        $this->assertSame($competingAccounts ? 2 : 1, DB::table('notifications')->whereIn('user_id', $this->userIds)->count());

        return $results;
    }

    public function test_simultaneous_identical_confirmation_replays_one_record(): void
    {
        $results = $this->race(true);
        $this->assertSame(['confirmed', 'confirmed'], array_column($results, 'result'));
        $this->assertSame($results[0]['id'], $results[1]['id']);
    }

    public function test_simultaneous_new_tokens_do_not_repeat_activation(): void
    {
        $results = array_column($this->race(false), 'result');
        sort($results);
        $this->assertSame(['blocked', 'confirmed'], $results);
    }

    public function test_simultaneous_official_link_of_same_identity_never_merges_accounts(): void
    {
        $results = array_column($this->race(false, true), 'result');
        sort($results);
        $this->assertSame(['blocked', 'confirmed'], $results);
        $this->assertSame(1, User::whereIn('id', $this->userIds)->where('national_id', '9988112233')->count());
        $this->assertSame(1, User::whereIn('id', $this->userIds)->where('registration_status', 'review_required')->count());
    }
}
