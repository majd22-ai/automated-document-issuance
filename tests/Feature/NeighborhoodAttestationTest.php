<?php

namespace Tests\Feature;

use App\Models\IdentityCardForm;
use App\Models\NeighborhoodOfficial;
use App\Models\ResidenceDistrict;
use App\Models\ResidenceGovernorate;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class NeighborhoodAttestationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDatabaseName() !== ':memory:' && ! str_ends_with(DB::connection()->getDatabaseName(), '_testing')) {
            throw new \RuntimeException('Isolated testing database required');
        }
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        DB::beginTransaction();
        $this->beforeApplicationDestroyed(fn () => DB::rollBack());
        $this->app['env'] = 'local';
        config(['identity_card.simulation_enabled' => true]);
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    private function fixture(): array
    {
        $citizen = User::factory()->create();
        $employee = User::factory()->create(['role' => 'employee']);
        $application = $citizen->serviceRequests()->create(['reference' => 'REQ-'.Str::ulid(), 'service_code' => 'identity-first-issue', 'status' => 'submitted']);
        $form = (new IdentityCardForm)->forceFill(['user_id' => $citizen->id, 'government_service_id' => $application->government_service_id, 'citizen_service_request_id' => $application->id, 'reference' => 'IDFORM-'.Str::ulid(), 'status' => 'pending_attestation', 'submitted_at' => now(), 'current_step' => 7, 'data' => ['applicant_name' => 'Test citizen', 'neighborhood' => 'Test neighborhood']]);
        $form->save();
        $district = ResidenceDistrict::firstOrFail();
        $official = (new NeighborhoodOfficial)->forceFill(['district_id' => $district->id, 'full_name' => 'Test official full name', 'national_id' => (string) random_int(100000000000, 999999999999), 'phone' => '+967777123456', 'neighborhood' => 'Test neighborhood', 'is_active' => true, 'user_id' => $employee->id, 'can_attest' => true]);
        $official->save();

        return [$citizen, $form, $official, ['authority_type' => 'neighborhood', 'governorate_id' => $district->governorate_id, 'district_id' => $district->id, 'official_id' => $official->id, 'neighborhood' => $official->neighborhood]];
    }

    private function requestApproval(array $fixture)
    {
        [$citizen, $form, , $data] = $fixture;
        $this->actingAs($citizen)->post(route('citizen.identity-card.attestation.store', $form), $data)->assertSessionHasNoErrors();

        return $form->attestation()->firstOrFail();
    }

    public function test_geography_is_persistent_and_admin_directory_is_protected(): void
    {
        $this->assertSame(22, ResidenceGovernorate::count());
        $this->assertGreaterThanOrEqual(333, ResidenceDistrict::count());
        [$citizen, , $official] = $this->fixture();
        $this->actingAs($citizen)->get('/admin/neighborhood-officials')->assertForbidden();
        $this->getJson('/residence/districts/'.$official->district_id.'/officials')->assertOk()->assertJsonFragment(['phone' => $official->phone])->assertDontSee($official->national_id);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/admin/neighborhood-officials')->assertOk();
    }

    public function test_citizen_requests_registered_official_and_only_assigned_employee_can_approve(): void
    {
        $fixture = $this->fixture();
        [$citizen, $form, $official] = $fixture;
        $row = $this->requestApproval($fixture);
        $this->assertSame(1, $official->user->notifications()->where('data->kind', 'neighborhood_attestation')->count());
        $url = route('employee.neighborhood-attestations.decide', $row);
        $this->post($url, ['decision' => 'approved'])->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'employee']))->get(route('employee.neighborhood-attestations.show', $row))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post('/simulation/identity-attestations/'.$row->id.'/decision', ['decision' => 'approved', 'verification_method' => 'fingerprint'])->assertForbidden();
        $this->actingAs($official->user)->post($url, ['decision' => 'approved'])->assertStatus(422);
        $this->post(route('employee.neighborhood-attestations.confirm', $row), ['method' => 'simulated_fingerprint', 'simulation_acknowledged' => 1])->assertSessionHas('confirmation_token');
        $token = session('confirmation_token');
        $this->post($url, ['decision' => 'approved', 'confirmation_token' => $token])->assertSessionHasNoErrors();
        $this->assertSame('approved', $row->fresh()->status);
        $this->assertSame('local_employee_confirmation', $row->fresh()->verification_source);
        $this->assertSame('pending_security_review', $form->fresh()->status);
        $notice = $citizen->notifications()->where('data->identity_form_id', $form->id)->firstOrFail();
        $this->assertSame('تمت المصادقة على شهادة السكن', $notice->data['title']);
        $this->assertSame(1, $citizen->notifications()->where('data->identity_form_id', $form->id)->count());
        $this->post($url, ['decision' => 'approved', 'confirmation_token' => $token])->assertStatus(409);
        $this->assertSame(1, $citizen->notifications()->where('data->kind', 'request_activity')->count());
        $this->actingAs($citizen)->get(route('citizen.notifications.index'))->assertSee('تمت المصادقة على شهادة السكن')->assertSee('فتح التفاصيل');
        $this->post(route('citizen.notifications.open', $notice->id))
            ->assertRedirect(route('citizen.identity-card.show', ['form' => $form, 'step' => 7]));
        $this->assertNotNull($notice->fresh()->read_at);
        $this->actingAs($official->user)->get(route('employee.neighborhood-attestations.show', $row))->assertSee('class="attestation-stamp"', false);
    }

    public function test_old_attestation_notification_redirects_when_official_assignment_changes(): void
    {
        $fixture = $this->fixture();
        [, , $official] = $fixture;
        $attestation = $this->requestApproval($fixture);
        $formerOfficial = $official->user;
        $newOfficial = User::factory()->create(['role' => 'employee']);
        $notification = $formerOfficial->notifications()
            ->where('data->kind', 'neighborhood_attestation')
            ->firstOrFail();

        $official->forceFill(['user_id' => $newOfficial->id])->save();

        $this->actingAs($formerOfficial)
            ->post(route('employee.notifications.open', $notification->id))
            ->assertRedirect(route('employee.notifications.index'))
            ->assertSessionHas('status', 'تعذر فتح هذا الإشعار لأن التكليف أو صلاحيات حسابك تغيّرت. تم تعليم الإشعار كمقروء، وما زالت بيانات الطلب محمية.');

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_pre_submission_attestation_gates_review_until_official_approval(): void
    {
        $fixture = $this->fixture();
        [$citizen, $form, $official, $data] = $fixture;
        $district = $official->district;
        $form->forceFill([
            'citizen_service_request_id' => null, 'submitted_at' => null, 'status' => 'awaiting_data',
            'current_step' => 6, 'version' => 1,
            'data' => ['applicant_name' => 'Test citizen', 'neighborhood' => 'Test neighborhood',
                'residence_governorate' => $district->governorate->name, 'residence_district' => $district->name],
        ])->save();

        $this->actingAs($citizen)->get(route('citizen.identity-card.show', ['form' => $form, 'step' => 7]))
            ->assertOk()->assertSee('مصادقة العاقل')->assertDontSee('أقر بصحة البيانات المدخلة');
        $row = $this->requestApproval($fixture);
        $this->assertSame('pending', $row->fresh()->status);
        $this->get(route('citizen.identity-card.show', ['form' => $form, 'step' => 7]))
            ->assertOk()->assertSee('مصادقة العاقل')->assertDontSee('أقر بصحة البيانات المدخلة');

        $url = route('employee.neighborhood-attestations.decide', $row);
        $this->actingAs($official->user)->post(route('employee.neighborhood-attestations.confirm', $row), [
            'method' => 'simulated_fingerprint', 'simulation_acknowledged' => 1,
        ])->assertSessionHas('confirmation_token');
        $this->post($url, ['decision' => 'approved', 'confirmation_token' => session('confirmation_token')])
            ->assertSessionHasNoErrors();
        $this->assertSame(7, $form->fresh()->current_step);
        $this->assertNull($form->fresh()->submitted_at);
        $this->actingAs($citizen)->get(route('citizen.identity-card.show', ['form' => $form, 'step' => 7]))
            ->assertOk()->assertSee('المراجعة والإقرار')->assertSee('أقر بصحة البيانات المدخلة');
        $this->assertSame(1, $citizen->notifications()->where('data->identity_form_id', $form->id)->count());
    }

    public function test_invalid_scope_legacy_paths_and_wrong_neighborhood_are_rejected(): void
    {
        [$citizen, $form, $official, $data] = $this->fixture();
        $url = route('citizen.identity-card.attestation.store', $form);
        $this->actingAs($citizen)->post($url, array_replace($data, ['district_id' => ResidenceDistrict::where('governorate_id', '!=', $data['governorate_id'])->firstOrFail()->id]))->assertSessionHasErrors('district_id');
        $this->post($url, array_replace($data, ['neighborhood' => 'Other neighborhood']))->assertSessionHasErrors('neighborhood');
        $this->post($url, ['authority_type' => 'workplace'])->assertSessionHasErrors('authority_type');
        $this->post($url, ['authority_type' => 'witnesses'])->assertSessionHasErrors('authority_type');
        $official->forceFill(['can_attest' => false])->save();
        $this->post($url, $data)->assertSessionHasErrors('official_id');
        $this->assertSame(0, $form->attestation()->count());
    }

    public function test_rejection_has_reason_notification_and_no_stamp(): void
    {
        $fixture = $this->fixture();
        [$citizen, , $official] = $fixture;
        $row = $this->requestApproval($fixture);
        $url = route('employee.neighborhood-attestations.decide', $row);
        $this->actingAs($official->user)->post($url, ['decision' => 'rejected'])->assertSessionHasErrors('reason');
        $this->post($url, ['decision' => 'rejected', 'reason' => 'Address mismatch'])->assertSessionHasNoErrors();
        $this->assertSame('Address mismatch', $row->fresh()->rejection_reason);
        $this->assertNull($row->fresh()->verified_at);
        $this->get(route('employee.neighborhood-attestations.show', $row))->assertOk()->assertDontSee('class="attestation-stamp"', false);
        $this->assertSame(1, $citizen->notifications()->where('data->kind', 'request_activity')->count());
    }

    public function test_revoked_permission_expired_confirmation_and_nonlocal_environment_block_approval(): void
    {
        $fixture = $this->fixture();
        [, , $official] = $fixture;
        $row = $this->requestApproval($fixture);
        $url = route('employee.neighborhood-attestations.decide', $row);
        $this->actingAs($official->user)->withSession(['neighborhood_confirmation.'.$row->id => ['actor' => $official->user_id, 'revision' => $row->form->revision, 'expires' => time() - 1, 'token_hash' => hash('sha256', 'expired')]])->post($url, ['decision' => 'approved', 'confirmation_token' => 'expired'])->assertStatus(422);
        $official->forceFill(['can_attest' => false])->save();
        $this->post($url, ['decision' => 'rejected', 'reason' => 'Test'])->assertForbidden();
        $official->forceFill(['can_attest' => true])->save();
        $this->app['env'] = 'production';
        $this->post($url, ['decision' => 'approved'])->assertForbidden();
        $this->assertSame('pending', $row->fresh()->status);
    }

    public function test_admin_links_existing_employee_without_other_permissions_and_inbox_opens_correct_request(): void
    {
        $fixture = $this->fixture();
        [$citizen, , $official] = $fixture;
        $row = $this->requestApproval($fixture);
        $notice = $official->user->notifications()->where('data->kind', 'neighborhood_attestation')->firstOrFail();
        $this->actingAs($official->user)->post(route('employee.notifications.open', $notice->id))->assertRedirect(route('employee.neighborhood-attestations.show', $row));
        $this->assertSame(0, $official->user->reviewPermissions()->count());
        $this->get(route('employee.citizens.index'))->assertForbidden();
        $this->get(route('employee.appointments.index'))->assertForbidden();
        $this->actingAs($citizen)->patch(route('admin.neighborhood-officials.update', $official), ['is_active' => 1, 'can_attest' => 1, 'user_id' => $citizen->id])->assertForbidden();
        $admin = User::factory()->create(['role' => 'admin']);
        $users = User::count();
        $this->actingAs($admin)->patch(route('admin.neighborhood-officials.update', $official), ['is_active' => 1, 'can_attest' => 1, 'user_id' => $citizen->id])->assertSessionHasErrors('user_id');
        $this->patch(route('admin.neighborhood-officials.update', $official), ['is_active' => 1, 'can_attest' => 1, 'user_id' => $official->user_id])->assertSessionHasNoErrors();
        $this->assertSame($users, User::count());
        $this->assertNotSame($official->phone, $official->getRawOriginal('phone'));
    }

    public function test_rejected_attestation_can_be_returned_for_correction_without_erasing_history(): void
    {
        $fixture = $this->fixture();
        [$citizen, $form, $official] = $fixture;
        $row = $this->requestApproval($fixture);
        $this->actingAs($official->user)->post(route('employee.neighborhood-attestations.decide', $row), ['decision' => 'rejected', 'reason' => 'Review address'])->assertSessionHasNoErrors();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post('/identity-workflow/'.$form->id.'/administration', ['version' => $form->fresh()->version, 'action' => 'correction_required', 'decision_token' => (string) Str::uuid(), 'citizen_reason' => 'Correct the address'])->assertSessionHasNoErrors();
        $this->assertSame(2, $form->fresh()->revision);
        $this->assertTrue($form->fresh()->correctionIsOpen());
        $this->assertNull($form->fresh()->attestation);
        $this->assertSame('rejected', $row->fresh()->status);
    }
}
