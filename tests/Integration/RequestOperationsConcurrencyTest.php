<?php

namespace Tests\Integration;

use App\Models\AppointmentSlot;
use App\Models\CitizenAppointment;
use App\Models\GovernmentService;
use App\Models\ManualPayment;
use App\Models\RequestCharge;
use App\Models\User;
use App\Services\Operations\RequestCharges;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\CleansAuditFixtures;
use Tests\TestCase;

class RequestOperationsConcurrencyTest extends TestCase
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
            $requestIds = DB::table('citizen_service_requests')->where('government_service_id', $this->service->id)->pluck('id');
            $chargeIds = DB::table('request_charges')->whereIn('citizen_service_request_id', $requestIds)->pluck('id');
            DB::table('manual_payments')->whereIn('request_charge_id', $chargeIds)->delete();
            DB::table('request_charges')->whereIn('id', $chargeIds)->delete();
            DB::table('citizen_appointments')->whereIn('citizen_service_request_id', $requestIds)->delete();
            DB::table('appointment_slots')->where('government_service_id', $this->service->id)->delete();
            DB::table('citizen_service_requests')->whereIn('id', $requestIds)->delete();
            DB::table('government_services')->where('id', $this->service->id)->delete();
        }
        DB::table('users')->whereIn('id', $this->users)->delete();
        $this->cleanAuditFixtures();
        parent::tearDown();
    }

    private function fixtures(): array
    {
        $this->service = GovernmentService::create(['code' => 'ops-race-'.Str::uuid(), 'name' => 'TEST parallel operations', 'document_type' => 'identity', 'operation' => 'issue', 'is_active' => true, 'rules_approved' => true, 'source_reference' => 'TEST ONLY', 'fee' => '10.25', 'currency' => 'TEST']);
        $requests = [];
        $citizens = [];
        for ($i = 0; $i < 2; $i++) {
            $citizens[$i] = User::factory()->create(['username' => 'OPS-RACE-'.Str::uuid()]);
            $this->users[] = $citizens[$i]->id;
            $requests[$i] = $citizens[$i]->serviceRequests()->create(['reference' => 'OPS-RACE-'.Str::uuid(), 'service_code' => $this->service->code, 'status' => 'submitted']);
        }

        return [$citizens, $requests];
    }

    private function runRace(string $kind, array $actors, array $requests, array $payload): array
    {
        $release = (string) (microtime(true) + 2);
        $processes = [];
        for ($i = 0; $i < 2; $i++) {
            $process = new Process([PHP_BINARY, base_path('tests/Support/request-operations-worker.php'), $kind, (string) $actors[$i]->id, (string) $requests[$i]->id, base64_encode(json_encode($payload, JSON_THROW_ON_ERROR)), $release], base_path());
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

    public function test_two_concurrent_citizens_cannot_overbook_the_last_place(): void
    {
        [$citizens,$requests] = $this->fixtures();
        $employee = User::factory()->create();
        $employee->forceFill(['role' => 'employee'])->save();
        $this->users[] = $employee->id;
        $slot = (new AppointmentSlot)->forceFill(['government_service_id' => $this->service->id, 'center' => 'TEST race center', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour(), 'capacity' => 1, 'is_active' => true, 'created_by' => $employee->id]);
        $slot->save();
        $this->assertSame(['blocked', 'saved'], $this->runRace('booking', $citizens, $requests, ['slot_id' => $slot->id]));
        $this->assertSame(1, CitizenAppointment::where('appointment_slot_id', $slot->id)->count());
    }

    public function test_concurrent_receipt_reuse_cannot_pay_two_different_requests(): void
    {
        [$citizens,$requests] = $this->fixtures();
        $employee = User::factory()->create();
        $employee->forceFill(['role' => 'employee'])->save();
        $this->users[] = $employee->id;
        foreach ($requests as $request) {
            app(RequestCharges::class)->ensure($request);
        }
        $data = ['amount' => '10.25', 'currency' => 'TEST', 'receipt_reference' => 'OPS-RACE-'.Str::uuid(), 'paid_at' => today()->toDateString(), 'method' => 'cash'];
        $this->assertSame(['blocked', 'saved'], $this->runRace('payment', [$employee, $employee], $requests, $data));
        $this->assertSame(1, ManualPayment::where('receipt_reference', mb_strtoupper($data['receipt_reference']))->count());
        $this->assertSame(1, RequestCharge::where('government_service_id', $this->service->id)->where('status', 'paid')->count());
        $this->assertSame(1, RequestCharge::where('government_service_id', $this->service->id)->where('status', 'due')->count());
    }
}
