<?php

namespace Tests\Feature;

use App\Models\CitizenActivationRequest;
use App\Models\GovernmentService;
use App\Models\ServiceReviewPermission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** The former in-person HTTP workflow is retired; historical rows must remain unchanged. */
class InPersonActivationTest extends TestCase
{
    public static function retiredActions(): array
    {
        $cases = [];
        foreach (['prepare', 'finger', 'code', 'verify-code'] as $action) {
            foreach (['employee', 'citizen', 'ungranted'] as $role) {
                $cases[$action.'-'.$role] = [$action, $role];
            }
        }

        return $cases;
    }

    #[DataProvider('retiredActions')]
    public function test_retired_employee_actions_cannot_create_or_change_evidence(string $action, string $role): void
    {
        if (DB::connection()->getDatabaseName() !== ':memory:') {
            throw new \RuntimeException('Isolated database required');
        }
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $citizen = User::factory()->create(['role' => 'citizen', 'national_id' => '123456789', 'registration_status' => 'identity_verification_pending']);
        $staff = User::factory()->create(['role' => 'employee']);
        (new ServiceReviewPermission)->forceFill(['user_id' => $staff->id, 'government_service_id' => GovernmentService::where('code', 'identity-first-issue')->value('id'), 'can_activate' => true, 'granted_by' => $staff->id])->save();
        $row = (new CitizenActivationRequest)->forceFill(['user_id' => $citizen->id, 'submitted_at' => now(), 'status' => 'pending', 'workflow' => 'in_person']);
        $row->save();
        $before = $row->fresh()->getRawOriginal();
        $actor = match ($role) {
            'employee' => $staff,'citizen' => $citizen,default => User::factory()->create(['role' => 'employee'])
        };
        $response = $this->actingAs($actor)->post(route('employee.accounts.'.$action, $citizen), ['identity_number' => '123456789', 'phone' => '+967777123456', 'finger' => 'right_index', 'otp' => '123456']);
        if ($role !== 'employee') {
            $response->assertForbidden();
        } else {
            $response->assertStatus(410);
        }
        $this->assertSame($before, $row->fresh()->getRawOriginal());
        $this->assertDatabaseCount('activation_fingerprint_attempts', 0);
        $this->assertDatabaseCount('citizen_account_confirmations', 0);
        $this->actingAs($staff)->get(route('employee.accounts.show', $citizen))->assertOk()->assertDontSee('data-finger=', false)->assertDontSee('in-person-activation.js');
    }
}
