<?php

namespace Tests\Integration;

use App\Models\IdentityCardForm;
use App\Models\NeighborhoodOfficial;
use App\Models\ResidenceDistrict;
use App\Models\User;
use App\Services\IdentityAttestations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\CleansAuditFixtures;
use Tests\TestCase;

class IdentityAttestationConcurrencyTest extends TestCase
{
    use CleansAuditFixtures;

    private array $users = [];

    private ?int $formId = null;

    private ?int $requestId = null;

    private ?int $officialId = null;

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
        if ($this->formId) {
            DB::table('identity_card_attestations')->where('identity_card_form_id', $this->formId)->delete();
            DB::table('identity_card_forms')->where('id', $this->formId)->delete();
        }
        if ($this->requestId) {
            DB::table('citizen_service_requests')->where('id', $this->requestId)->delete();
        }
        if ($this->officialId) {
            DB::table('neighborhood_officials')->where('id', $this->officialId)->delete();
        }
        $this->cleanAuditFixtures();
        if ($this->users) {
            DB::table('users')->whereIn('id', $this->users)->delete();
        }
        parent::tearDown();
    }

    public function test_racing_approval_and_rejection_save_exactly_one_decision_and_notice(): void
    {
        $citizen = User::factory()->create();
        $actor = User::factory()->create(['role' => 'admin']);
        $this->users = [$citizen->id, $actor->id];
        $request = $citizen->serviceRequests()->create(['reference' => 'REQ-'.Str::ulid(), 'service_code' => 'identity-first-issue', 'status' => 'submitted']);
        $this->requestId = $request->id;
        $form = new IdentityCardForm;
        $form->forceFill(['user_id' => $citizen->id, 'government_service_id' => $request->government_service_id, 'citizen_service_request_id' => $request->id, 'reference' => 'IDFORM-'.Str::ulid(), 'status' => 'pending_photo_review', 'submitted_at' => now(), 'current_step' => 7, 'data' => ['applicant_name' => 'TEST fixture']])->save();
        $this->formId = $form->id;
        $this->app['env'] = 'local';
        config(['identity_card.simulation_enabled' => true]);
        $district = ResidenceDistrict::firstOrFail();
        $official = (new NeighborhoodOfficial)->forceFill(['full_name' => 'TEST official fixture only', 'national_id' => (string) random_int(100000000000, 999999999999), 'phone' => '777123456', 'district_id' => $district->id, 'neighborhood' => 'TEST neighborhood', 'is_active' => true]);
        $official->save();
        $this->officialId = $official->id;
        $form->forceFill(['data' => ['applicant_name' => 'TEST fixture', 'neighborhood' => 'TEST neighborhood']])->save();
        $row = app(IdentityAttestations::class)->choose($citizen, $form, ['authority_type' => 'neighborhood', 'official_id' => $official->id, 'governorate_id' => $district->governorate_id, 'district_id' => $district->id, 'neighborhood' => 'TEST neighborhood']);
        $release = (string) (microtime(true) + 2);
        $processes = [];
        foreach (['approved', 'rejected'] as $decision) {
            $process = new Process([PHP_BINARY, base_path('tests/Support/identity-attestation-worker.php'), (string) $actor->id, (string) $row->id, $decision, $release], base_path());
            $process->setTimeout(40);
            $process->start();
            $processes[] = $process;
        }
        $results = [];
        foreach ($processes as $process) {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
            $results[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR)['result'];
        }
        sort($results);
        $this->assertSame(['blocked', 'saved'], $results);
        $this->assertContains($row->fresh()->status, ['approved', 'rejected']);
        $this->assertSame(1, $citizen->notifications()->where('data->kind', 'request_activity')->count());
        $this->assertSame(1, DB::table('audit_events')->where('event', 'identity_attestation_decided')->where('subject_id', (string) $row->id)->count());
    }
}
