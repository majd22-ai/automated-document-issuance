<?php

namespace Tests\Integration;

use App\Models\CitizenAttachment;
use App\Models\GovernmentService;
use App\Models\IdentityAdministrativeDecision;
use App\Models\IdentityCardAttestation;
use App\Models\IdentityCardForm;
use App\Models\IdentityCardWitness;
use App\Models\User;
use App\Services\FingerprintService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\CleansAuditFixtures;
use Tests\TestCase;

class IdentityFingerprintConcurrencyTest extends TestCase
{
    use CleansAuditFixtures;

    private array $users = [];

    private array $formIds = [];

    private array $requestIds = [];

    private array $attachmentIds = [];

    private ?string $photoPath = null;

    private ?array $serviceSnapshot = null;

    private ?int $serviceId = null;

    protected function setUp(): void
    {
        parent::setUp();
        if (! app()->environment('testing') || DB::getDriverName() !== 'mysql'
            || ! str_ends_with(DB::connection()->getDatabaseName(), '_testing')) {
            $this->markTestSkipped('Explicit isolated MySQL testing database required.');
        }
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->beginAuditFixtures();
    }

    protected function tearDown(): void
    {
        if ($this->users) {
            $this->cleanAuditFixtures();
            DB::table('notifications')->whereIn('user_id', $this->users)->delete();
            DB::table('identity_administrative_decisions')->whereIn('identity_card_form_id', $this->formIds)->delete();
            DB::table('identity_fingerprint_operations')->whereIn('identity_card_form_id', $this->formIds)->delete();
            DB::table('identity_card_attestations')->whereIn('identity_card_form_id', $this->formIds)->delete();
            DB::table('identity_card_witnesses')->whereIn('identity_card_form_id', $this->formIds)->delete();
            DB::table('identity_card_forms')->whereIn('id', $this->formIds)->delete();
            DB::table('citizen_attachments')->whereIn('id', $this->attachmentIds)->delete();
            DB::table('citizen_service_requests')->whereIn('id', $this->requestIds)->delete();
            DB::table('users')->whereIn('id', $this->users)->delete();
            $this->cleanAuditFixtures();
        }
        if ($this->serviceSnapshot !== null) {
            DB::table('government_services')->where('id', $this->serviceId)->update($this->serviceSnapshot);
        }
        if ($this->photoPath !== null) {
            Storage::disk('attachments')->delete($this->photoPath);
        }
        parent::tearDown();
    }

    private function fixtures(): array
    {
        $service = GovernmentService::where('code', 'identity-first-issue')->firstOrFail();
        $this->serviceId = $service->id;
        $this->serviceSnapshot = (array) DB::table('government_services')->where('id', $service->id)
            ->first(['is_active', 'rules_approved', 'source_reference', 'rules_version']);
        DB::table('government_services')->where('id', $service->id)->update(['is_active' => true, 'rules_approved' => true, 'source_reference' => 'TEST ONLY fingerprint race']);
        $service->refresh();
        $citizen = User::factory()->create(['role' => 'citizen', 'is_active' => true]);
        $this->users[] = $citizen->id;
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->users[] = $admin->id;
        $request = $citizen->serviceRequests()->create(['reference' => 'FP-RACE-'.Str::ulid(), 'service_code' => $service->code, 'status' => 'submitted']);
        $this->requestIds[] = $request->id;
        $data = [];
        foreach (config('identity_card.fields') as $fields) {
            foreach ($fields as $key => $field) {
                $data[$key] = isset($field['options']) ? array_key_first($field['options'])
                    : (($field['type'] ?? '') === 'date' ? '1990-01-01' : 'TEST ONLY');
            }
        }
        $data['phone'] = $data['alternate_phone'] = '777123456';
        // Real private bytes are shared with both child processes; no fake disk.
        $chunk = static fn (string $type, string $bytes): string => pack('N', strlen($bytes)).$type.$bytes.pack('N', crc32($type.$bytes));
        $png = "\x89PNG\r\n\x1a\n".$chunk('IHDR', pack('NNCCCCC', 600, 600, 8, 2, 0, 0, 0))
            .$chunk('IDAT', gzcompress(str_repeat("\0".str_repeat("\xff", 1800), 600))).$chunk('IEND', '');
        $this->photoPath = 'citizen-files/'.Str::uuid().'.png';
        $this->assertTrue(Storage::disk('attachments')->put($this->photoPath, $png, ['visibility' => 'private']));
        $photo = new CitizenAttachment;
        $photo->forceFill(['user_id' => $citizen->id, 'government_service_id' => $service->id, 'citizen_service_request_id' => $request->id,
            'slot_key' => CitizenAttachment::slot($citizen->id, $service->id, $request->id, 'identity_photo'), 'document_code' => 'identity_photo',
            'document_name' => 'TEST photo', 'display_name' => 'test-photo.png', 'kind' => 'photo', 'mime_type' => 'image/png', 'size' => strlen($png), 'sha256' => hash('sha256', $png),
            'storage_path' => $this->photoPath, 'version_token' => (string) Str::uuid(), 'rules_version' => $service->rules_version,
            'review_status' => 'approved', 'reviewed_by' => $admin->id, 'reviewed_at' => now()])->save();
        $this->attachmentIds[] = $photo->id;
        $form = new IdentityCardForm;
        $form->forceFill(['user_id' => $citizen->id, 'government_service_id' => $service->id, 'citizen_service_request_id' => $request->id,
            'citizen_attachment_id' => $photo->id, 'reference' => 'IDFORM-'.Str::ulid(), 'data' => $data, 'status' => 'pending_fingerprint_attendance',
            'revision' => 1, 'version' => 1, 'current_step' => 7, 'declared_at' => now(), 'submitted_at' => now(), 'arrived_at' => now(),
            'received_by' => $admin->id, 'identity_documents_checked_at' => now(), 'arrival_reference' => 'TEST ONLY arrival'])->save();
        $this->formIds[] = $form->id;
        foreach ([1, 2] as $position) {
            (new IdentityCardWitness)->forceFill(['identity_card_form_id' => $form->id, 'position' => $position, 'name' => 'TEST witness '.$position,
                'document_type' => 'identity', 'document_number' => 'TEST-'.$position, 'issued_place' => 'TEST place', 'issued_at' => '2020-01-01',
                'employer' => 'TEST employer', 'work_address' => 'TEST work', 'phone' => '777123456', 'address' => 'TEST address'])->save();
        }
        (new IdentityCardAttestation)->forceFill(['identity_card_form_id' => $form->id, 'form_revision' => 1, 'reference' => 'ATT-'.Str::ulid(),
            'authority_type' => 'workplace', 'recipient_name' => 'TEST workplace', 'created_by' => $citizen->id, 'decided_by' => $admin->id,
            'decided_at' => now(), 'status' => 'approved', 'security_required' => false, 'security_status' => 'not_required'])->save();

        return [$admin, $citizen, $form];
    }

    private function race(User $actor, IdentityCardForm $form, string $mode, array $inputs): array
    {
        $release = (string) (microtime(true) + 2);
        $processes = [];
        foreach ($inputs as $data) {
            $process = new Process([PHP_BINARY, base_path('tests/Support/identity-fingerprint-worker.php'), (string) $actor->id,
                (string) $form->id, $mode, base64_encode(json_encode($data, JSON_THROW_ON_ERROR)), $release], base_path(), ['APP_ENV' => 'testing']);
            $process->setTimeout(40);
            $process->start();
            $processes[] = $process;
        }
        $results = [];
        foreach ($processes as $process) {
            $process->wait();
            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
            $result = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            if ($result['result'] === 'blocked') {
                $this->assertSame(409, $result['status']);
            }
            $results[] = $result['result'];
        }
        sort($results);

        return $results;
    }

    public function test_distinct_capture_tokens_cannot_duplicate_a_success(): void
    {
        [$actor, $citizen, $form] = $this->fixtures();
        $inputs = array_map(fn () => ['version' => $form->version, 'operation_token' => (string) Str::uuid()], [1, 2]);
        $this->assertSame(['blocked', 'saved'], $this->race($actor, $form, 'capture', $inputs));
        $this->assertSame(1, $form->operations()->where('result', 'success')->count());
        $this->assertSame(1, $citizen->notifications()->where('data->kind', 'request_activity')->count());
        $this->assertSame('simulation', $form->operations()->sole()->source);
    }

    public function test_competing_administrative_decisions_write_one_event(): void
    {
        [$actor, $citizen, $form] = $this->fixtures();
        $this->app->instance('env', 'local');
        config(['identity_card.simulation_enabled' => true, 'identity_card.fingerprint.simulation_enabled' => true]);
        $operation = app(FingerprintService::class)->capture($actor, $form, ['version' => $form->version, 'operation_token' => (string) Str::uuid()]);
        $form->refresh();
        $inputs = array_map(fn ($action) => ['version' => $form->version, 'action' => $action,
            'decision_token' => (string) Str::uuid(), 'citizen_reason' => 'TEST ONLY decision reason'], ['approved', 'rejected']);
        $this->assertSame(['blocked', 'saved'], $this->race($actor, $form, 'administer', $inputs));
        $this->assertSame(1, $form->administrativeDecisions()->count());
        $this->assertSame('simulation', $operation->fresh()->source);
        $this->assertSame(1, $form->operations()->count());
        $this->assertSame(2, $citizen->notifications()->where('data->kind', 'request_activity')->count());
        $this->assertSame(1, DB::table('audit_events')->where('event', 'identity_trial_administration')
            ->where('subject_type', IdentityAdministrativeDecision::class)
            ->where('subject_id', (string) $form->administrativeDecisions()->sole()->id)->count());
    }
}
