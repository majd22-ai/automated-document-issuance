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
        config(['identity_card.simulation_enabled' => true, 'identity_card.neighborhood_test_code' => '123456']);
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    private function fixture(): array
    {
        $citizen = User::factory()->create();
        $application = $citizen->serviceRequests()->create(['reference' => 'REQ-'.Str::ulid(), 'service_code' => 'identity-first-issue', 'status' => 'submitted']);
        $form = (new IdentityCardForm)->forceFill(['user_id' => $citizen->id, 'government_service_id' => $application->government_service_id, 'citizen_service_request_id' => $application->id, 'reference' => 'IDFORM-'.Str::ulid(), 'status' => 'pending_attestation', 'submitted_at' => now(), 'current_step' => 7, 'data' => ['applicant_name' => 'مواطن اختبار', 'neighborhood' => 'حي الاختبار']]);
        $form->save();
        $district = ResidenceDistrict::firstOrFail();
        $official = (new NeighborhoodOfficial)->forceFill(['district_id' => $district->id, 'full_name' => 'عاقل تجريبي للاختبار فقط', 'national_id' => '12345678901', 'phone' => '+967777123456', 'neighborhood' => 'حي الاختبار', 'is_active' => true]);
        $official->save();

        return [$citizen, $form, $official, ['authority_type' => 'neighborhood', 'governorate_id' => $district->governorate_id, 'district_id' => $district->id, 'official_id' => $official->id, 'neighborhood' => $official->neighborhood]];
    }

    public function test_geography_is_persistent_and_admin_directory_is_protected(): void
    {
        $this->assertSame(22, ResidenceGovernorate::count());
        $this->assertGreaterThanOrEqual(333, ResidenceDistrict::count());
        [$citizen, , $official] = $this->fixture();
        $this->actingAs($citizen)->get('/admin/neighborhood-officials')->assertForbidden();
        $this->getJson('/residence/districts/'.$official->district->id.'/officials')->assertOk()->assertDontSee($official->national_id)->assertDontSee($official->phone);
        $this->actingAs(User::factory()->create(['role' => 'admin']))->get('/admin/neighborhood-officials')->assertOk();
    }

    public function test_citizen_requests_registered_official_and_only_staff_can_approve_after_verification(): void
    {
        [$citizen, $form, $official, $data] = $this->fixture();
        $url = '/citizen/identity-card/'.$form->id.'/attestation';
        $this->actingAs($citizen)->post($url, $data)->assertSessionHasNoErrors()->assertRedirect();
        $row = $form->attestation()->firstOrFail();
        $this->assertSame($official->id, $row->neighborhood_official_id);
        $decision = '/simulation/identity-attestations/'.$row->id.'/decision';
        $this->post($decision, ['decision' => 'approved', 'verification_method' => 'fingerprint'])->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->post($decision, ['decision' => 'approved'])->assertSessionHasErrors('verification_method');
        $this->post($decision, ['decision' => 'approved', 'verification_method' => 'code', 'verification_code' => '000000'])->assertSessionHasErrors('verification_code');
        $this->assertSame('pending', $row->fresh()->status);
        $this->assertSame(1, $row->fresh()->verification_attempts);
        $this->post($decision, ['decision' => 'approved', 'verification_method' => 'code', 'verification_code' => '123456'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('approved', $row->fresh()->status);
        $this->assertSame('local_code', $row->fresh()->verification_source);
        $this->assertSame('pending_security_review', $form->fresh()->status);
        $this->post($decision, ['decision' => 'approved', 'verification_method' => 'fingerprint'])->assertStatus(409);
        $this->assertStringNotContainsString('123456', (string) $row->fresh()->getRawOriginal('verification_source'));
    }

    public function test_invalid_scope_legacy_paths_and_wrong_neighborhood_are_rejected(): void
    {
        [$citizen, $form, $official, $data] = $this->fixture();
        $url = '/citizen/identity-card/'.$form->id.'/attestation';
        $this->actingAs($citizen)->post($url, array_replace($data, ['district_id' => ResidenceDistrict::where('governorate_id', '!=', $data['governorate_id'])->firstOrFail()->id]))->assertSessionHasErrors('district_id');
        $this->post($url, array_replace($data, ['neighborhood' => 'حي آخر']))->assertSessionHasErrors('neighborhood');
        $this->post($url, ['authority_type' => 'workplace', 'recipient_name' => 'جهة'])->assertSessionHasErrors('authority_type');
        $this->post($url, ['authority_type' => 'witnesses'])->assertSessionHasErrors('authority_type');
        $this->assertSame(0, $form->attestation()->count());
    }

    public function test_fingerprint_simulation_rejection_notifications_and_modal_access(): void
    {
        foreach (['approved', 'rejected'] as $decision) {
            [$citizen, $form, $official, $data] = $this->fixture();
            $official->forceFill(['national_id' => (string) random_int(100000000000, 999999999999)])->save();
            $this->actingAs($citizen)->post('/citizen/identity-card/'.$form->id.'/attestation', $data)->assertSessionHasNoErrors();
            $row = $form->attestation()->firstOrFail();
            $url = '/simulation/identity-attestations/'.$row->id;
            $this->get('/citizen/identity-card/'.$form->id)->assertOk()->assertDontSee('data-phone-dialog', false)->assertDontSee('123456');
            $admin = User::factory()->create(['role' => 'admin']);
            $this->actingAs($admin)->get($url)->assertOk()->assertSee('data-phone-dialog', false)->assertSee('طلب مصادقة على إقامة مواطن')->assertSee('123456');
            $this->post($url.'/decision', ['decision' => $decision, 'verification_method' => 'fingerprint', 'reason' => 'العنوان غير مطابق'])->assertSessionHasNoErrors()->assertRedirect();
            $this->assertSame($decision, $row->fresh()->status);
            $response = $this->get($url)->assertOk();
            if ($decision === 'approved') {
                $this->assertSame('local_fingerprint', $row->fresh()->verification_source);
                $response->assertSee('class="attestation-stamp"', false)->assertSee('محاكاة بصمة محلية');
            } else {
                $this->assertNull($row->fresh()->verified_at);
                $response->assertSee('العنوان غير مطابق')->assertDontSee('class="attestation-stamp"', false);
            }
            $this->assertSame(1, $citizen->notifications()->where('data->kind', 'request_activity')->count());
        }
    }

    public function test_attempt_limit_disabled_official_and_nonlocal_environment_block_approval(): void
    {
        [$citizen, $form, $official, $data] = $this->fixture();
        $this->actingAs($citizen)->post('/citizen/identity-card/'.$form->id.'/attestation', $data)->assertSessionHasNoErrors();
        $this->post('/citizen/identity-card/'.$form->id.'/attestation', $data)->assertSessionHasNoErrors();
        $this->assertSame(1, $form->attestation()->count());
        $row = $form->attestation()->firstOrFail();
        $url = '/simulation/identity-attestations/'.$row->id.'/decision';
        $this->actingAs(User::factory()->create(['role' => 'employee']))->post($url, ['decision' => 'approved', 'verification_method' => 'fingerprint'])->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $official->forceFill(['is_active' => false])->save();
        $this->post($url, ['decision' => 'approved', 'verification_method' => 'fingerprint'])->assertSessionHasErrors('official_id');
        $official->forceFill(['is_active' => true])->save();
        for ($i = 0; $i < 5; $i++) {
            $this->post($url, ['decision' => 'approved', 'verification_method' => 'code', 'verification_code' => '000000'])->assertSessionHasErrors('verification_code');
        }
        $this->post($url, ['decision' => 'approved', 'verification_method' => 'fingerprint'])->assertSessionHasErrors('verification_code');
        $this->assertSame(5, $row->fresh()->verification_attempts);
        $this->assertSame('pending', $row->fresh()->status);
        $this->app['env'] = 'production';
        $this->post($url, ['decision' => 'approved', 'verification_method' => 'fingerprint'])->assertForbidden();
    }

    public function test_admin_adds_directory_without_user_accounts_and_scoped_lists_hide_private_data(): void
    {
        [$citizen, $form, $official, $data] = $this->fixture();
        $users = User::count();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post('/admin/neighborhood-officials', ['full_name' => 'عاقل حارة جديد للاختبار', 'national_id' => '98765432101', 'phone' => '777123456', 'governorate_id' => $data['governorate_id'], 'district_id' => $data['district_id'], 'neighborhood' => 'حي آخر'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame($users + 1, User::count());
        $new = NeighborhoodOfficial::where('national_id_hash', hash('sha256', '98765432101'))->firstOrFail();
        $this->assertNotSame('98765432101', $new->getRawOriginal('national_id'));
        $this->assertNotSame('777123456', $new->getRawOriginal('phone'));
        $other = ResidenceDistrict::where('id', '!=', $data['district_id'])->firstOrFail();
        $this->actingAs($citizen)->getJson('/residence/districts/'.$other->id.'/officials')->assertExactJson([]);
        $this->getJson('/residence/districts/'.$data['district_id'].'/officials')->assertJsonCount(2)->assertDontSee('98765432101')->assertDontSee('777123456');
    }

    public function test_rejected_attestation_can_be_returned_for_correction_without_erasing_history(): void
    {
        [$citizen, $form, $official, $data] = $this->fixture();
        $this->actingAs($citizen)->post('/citizen/identity-card/'.$form->id.'/attestation', $data)->assertSessionHasNoErrors();
        $row = $form->attestation()->firstOrFail();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post('/simulation/identity-attestations/'.$row->id.'/decision', ['decision' => 'rejected', 'reason' => 'راجع العنوان'])->assertSessionHasNoErrors();
        $token = (string) Str::uuid();
        $payload = ['version' => $form->fresh()->version, 'action' => 'correction_required', 'decision_token' => $token, 'citizen_reason' => 'صحح عنوان الإقامة واختر العاقل المسؤول'];
        $this->post('/identity-workflow/'.$form->id.'/administration', $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(2, $form->fresh()->revision);
        $this->assertTrue($form->fresh()->correctionIsOpen());
        $this->assertNull($form->fresh()->attestation);
        $this->assertSame('rejected', $row->fresh()->status);
        $this->post('/identity-workflow/'.$form->id.'/administration', $payload)->assertStatus(409);
        $this->actingAs($citizen)->get('/citizen/identity-card/'.$form->id)->assertOk()->assertSee('صحح عنوان الإقامة');
    }
}
