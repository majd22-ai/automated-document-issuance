<?php

namespace Tests\Feature;

use App\Enums\ServiceRequestStatus;
use App\Models\GovernmentService;
use App\Models\ServiceReviewPermission;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ServiceRequestSubmissionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $connection = DB::connection();
        if ($connection->getDatabaseName() !== ':memory:' && ! str_ends_with($connection->getDatabaseName(), '_testing')) {
            throw new \RuntimeException('Isolated test database required.');
        }
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $connection->beginTransaction();
        $this->beforeApplicationDestroyed(function () use ($connection) {
            $connection->rollBack();
            $connection->disconnect();
        });
    }

    private function user(string $role = 'citizen'): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role, 'username' => 'SUBMIT-'.$user->id])->save();

        return $user;
    }

    private function ready(User $citizen, string $code = 'identity-first-issue'): GovernmentService
    {
        $service = GovernmentService::where('code', $code)->firstOrFail();
        $service->update(['is_active' => true, 'rules_approved' => true, 'source_reference' => 'TEST approval', 'application_fields' => [['key' => 'delivery_address', 'label' => 'عنوان المراجعة', 'type' => 'text', 'required' => true, 'max_length' => 100]]]);
        $service->assessments()->create(['user_id' => $citizen->id, 'rules_version' => $service->rules_version, 'identity_verified' => true, 'conditions_met' => true, 'reviewed_by' => $this->user('admin')->id, 'reviewed_at' => now(), 'verification_reference' => 'TEST review']);

        return $service;
    }

    private function preview(User $citizen, GovernmentService $service): string
    {
        $response = $this->actingAs($citizen)->post('/citizen/services/'.$service->id.'/preview', ['data' => ['delivery_address' => 'عنوان اختباري'], 'notes' => 'TEST notes']);
        $response->assertRedirect();
        $token = basename(parse_url($response->headers->get('Location'), PHP_URL_PATH));
        $this->get('/citizen/applications/preview/'.$token)->assertOk()->assertSee('عنوان اختباري');

        return $token;
    }

    public function test_valid_submission_persists_own_request_declaration_and_unique_reference(): void
    {
        $citizen = $this->user();
        $service = $this->ready($citizen);
        $token = $this->preview($citizen, $service);
        $this->assertSame(0, $citizen->serviceRequests()->count());
        $response = $this->post('/citizen/services/'.$service->id.'/submit', ['preview_token' => $token, 'declaration' => '1']);
        $request = $citizen->serviceRequests()->firstOrFail();
        $response->assertRedirect('/citizen/applications/'.$request->id.'/success');
        $this->assertMatchesRegularExpression('/^REQ-[0-9A-Z]{26}$/', $request->reference);
        $this->assertTrue($request->service->is($service));
        $this->assertSame('issue', $request->request_type);
        $this->assertSame('submitted', $request->status);
        $this->assertSame('عنوان اختباري', $request->request_data['delivery_address']);
        $this->assertNotNull($request->declared_at);
        $this->assertNotNull($request->submitted_at);
        $this->assertNotNull($request->active_request_key);
        $this->get($response->headers->get('Location'))->assertOk()->assertSee($request->reference)->assertSee('مقدّم');
    }

    public function test_final_confirmation_requires_declaration_and_rejects_overposted_identity_or_state(): void
    {
        $citizen = $this->user();
        $service = $this->ready($citizen);
        $token = $this->preview($citizen, $service);
        $url = '/citizen/services/'.$service->id.'/submit';
        $this->post($url, ['preview_token' => $token])->assertSessionHasErrors('declaration');
        $this->post($url, ['preview_token' => $token, 'declaration' => true, 'user_id' => $this->user()->id, 'status' => 'completed', 'data' => []])->assertSessionHasErrors(['user_id', 'status', 'data']);
        $this->assertSame(0, $citizen->serviceRequests()->count());
    }

    public function test_dynamic_request_fields_are_server_validated_before_review(): void
    {
        $citizen = $this->user();
        $service = $this->ready($citizen);
        $this->actingAs($citizen)->post('/citizen/services/'.$service->id.'/preview', [])->assertSessionHasErrors('data.delivery_address');
        $this->post('/citizen/services/'.$service->id.'/preview', ['data' => ['delivery_address' => 'A', 'unconfigured' => 'B']])->assertSessionHasErrors('data');
        $this->post('/citizen/services/'.$service->id.'/preview', ['data' => ['delivery_address' => ['bad']]])->assertSessionHasErrors('data.delivery_address');
    }

    public function test_preview_is_owner_service_and_time_bound(): void
    {
        $owner = $this->user();
        $service = $this->ready($owner);
        $token = $this->preview($owner, $service);
        $otherService = GovernmentService::where('code', 'passport-first-issue')->firstOrFail();
        $this->post('/citizen/services/'.$otherService->id.'/submit', ['preview_token' => $token, 'declaration' => true])->assertNotFound();
        $this->actingAs($this->user())->get('/citizen/applications/preview/'.$token)->assertNotFound();
        $this->actingAs($owner);
        $this->travel(31)->minutes();
        $this->post('/citizen/services/'.$service->id.'/submit', ['preview_token' => $token, 'declaration' => true])->assertStatus(410);
        $this->assertSame(0, $owner->serviceRequests()->count());
    }

    public function test_changes_after_preview_are_rechecked_at_final_submission(): void
    {
        $citizen = $this->user();
        $service = $this->ready($citizen);
        $token = $this->preview($citizen, $service);
        $service->update(['is_active' => false]);
        $this->post('/citizen/services/'.$service->id.'/submit', ['preview_token' => $token, 'declaration' => true])->assertSessionHasErrors();
        $this->assertSame(0, $citizen->serviceRequests()->count());
    }

    public function test_replayed_confirmation_is_idempotent_and_new_preview_cannot_duplicate_active_request(): void
    {
        $citizen = $this->user();
        $service = $this->ready($citizen);
        $token = $this->preview($citizen, $service);
        $url = '/citizen/services/'.$service->id.'/submit';
        $first = $this->post($url, ['preview_token' => $token, 'declaration' => true]);
        $this->post($url, ['preview_token' => $token, 'declaration' => true])->assertRedirect($first->headers->get('Location'));
        $this->assertSame(1, $citizen->serviceRequests()->count());
        $this->post('/citizen/services/'.$service->id.'/preview', ['data' => ['delivery_address' => 'Another']])->assertSessionHasErrors('service');
    }

    public function test_terminal_requests_allow_new_submission_and_references_remain_unique(): void
    {
        $citizen = $this->user();
        $service = $this->ready($citizen);
        foreach (['rejected', 'cancelled', 'completed'] as $status) {
            $token = $this->preview($citizen, $service);
            $this->post('/citizen/services/'.$service->id.'/submit', ['preview_token' => $token, 'declaration' => true])->assertRedirect();
            // Trusted backend fixture simulates the future review/issuance module.
            DB::table('citizen_service_requests')->where('user_id', $citizen->id)->update(['status' => $status, 'active_request_key' => null]);
        }
        $this->assertSame(3, $citizen->serviceRequests()->count());
        $this->assertSame(3, $citizen->serviceRequests()->distinct()->count('reference'));
    }

    public function test_guests_and_non_citizens_cannot_submit_or_preview(): void
    {
        $citizen = $this->user();
        $service = $this->ready($citizen);
        foreach (['preview', 'submit'] as $action) {
            $this->post('/citizen/services/'.$service->id.'/'.$action)->assertRedirect('/login');
        }
        foreach (['employee', 'admin'] as $role) {
            $this->actingAs($this->user($role))->post('/citizen/services/'.$service->id.'/submit')->assertForbidden();
        }
    }

    public function test_lists_filters_ownership_and_employee_readonly_integration(): void
    {
        $owner = $this->user();
        $service = $this->ready($owner);
        $token = $this->preview($owner, $service);
        $this->post('/citizen/services/'.$service->id.'/submit', ['preview_token' => $token, 'declaration' => true]);
        $request = $owner->serviceRequests()->firstOrFail();
        $this->get('/citizen/applications?search='.$request->reference.'&status=submitted')->assertOk()->assertSee($request->reference);
        $this->get('/citizen/applications?status=rejected')->assertOk()->assertDontSee($request->reference);
        $this->actingAs($this->user())->get('/citizen/applications/'.$request->id)->assertNotFound();
        $this->get('/citizen/applications/'.$request->id.'/success')->assertNotFound();
        $this->get('/citizen/applications?search='.$request->reference)->assertOk()
            ->assertViewHas('applications', fn ($items) => $items->total() === 0);
        $employee = $this->user('employee');
        (new ServiceReviewPermission)->forceFill(['user_id' => $employee->id, 'government_service_id' => $service->id, 'can_review' => true, 'granted_by' => $this->user('admin')->id])->save();
        $this->actingAs($employee)->get('/employee/applications')->assertOk()->assertSee($request->reference);
        $this->get('/employee/applications/'.$request->id.'/review')->assertOk()->assertSee($request->reference)->assertDontSee('name="status"', false);
        $this->actingAs($owner)->patch('/citizen/applications/'.$request->id, ['status' => 'completed'])->assertStatus(405);
        $this->assertSame('submitted', $request->fresh()->status);
    }

    public function test_invalid_array_input_returns_to_usable_form_and_stale_review_is_not_a_redirect_loop(): void
    {
        $citizen = $this->user();
        $service = $this->ready($citizen);
        $url = '/citizen/applications/new?service='.$service->code;
        $this->actingAs($citizen)->from($url)->post('/citizen/services/'.$service->id.'/preview', ['data' => ['delivery_address' => ['bad']], 'notes' => ['bad']])->assertRedirect($url);
        $this->get($url)->assertOk();
        $token = $this->preview($citizen, $service);
        $citizen->update(['name' => 'Changed account name']);
        $this->get('/citizen/applications/preview/'.$token)->assertStatus(422)->assertSee('تغيرت بيانات');
    }

    public function test_canonical_active_status_cannot_be_configured_as_terminal_and_active_key_is_unique(): void
    {
        $citizen = $this->user();
        $service = $this->ready($citizen);
        $service->update(['terminal_request_statuses' => ['submitted']]);
        $service->assessments()->update(['rules_version' => $service->rules_version]);
        $token = $this->preview($citizen, $service);
        $this->post('/citizen/services/'.$service->id.'/submit', ['preview_token' => $token, 'declaration' => true]);
        $request = $citizen->serviceRequests()->firstOrFail();
        $this->post('/citizen/services/'.$service->id.'/preview', ['data' => ['delivery_address' => 'Again']])->assertSessionHasErrors('service');
        $this->assertFalse($citizen->can('transition', $request));
        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('citizen_service_requests')->insert(['reference' => 'OTHER-REF', 'user_id' => $citizen->id, 'service_code' => $service->code, 'active_request_key' => $request->active_request_key]);
    }

    public function test_direct_model_state_changes_are_rejected_and_completed_is_not_a_valid_jump(): void
    {
        $citizen = $this->user();
        $service = $this->ready($citizen);
        $token = $this->preview($citizen, $service);
        $this->post('/citizen/services/'.$service->id.'/submit', ['preview_token' => $token, 'declaration' => true]);
        $request = $citizen->serviceRequests()->firstOrFail();
        $this->assertFalse(ServiceRequestStatus::Submitted->canTransitionTo(ServiceRequestStatus::Completed));
        $this->assertTrue(ServiceRequestStatus::Submitted->canTransitionTo(ServiceRequestStatus::UnderReview));
        $this->expectException(ValidationException::class);
        $request->update(['status' => 'completed']);
    }

    public function test_admin_application_field_schema_rejects_unknown_types_and_reserved_keys(): void
    {
        $citizen = $this->user();
        $service = $this->ready($citizen);
        $this->actingAs($this->user('admin'))->patch('/admin/services/'.$service->id, [
            'name' => $service->name,
            'application_fields' => [['key' => 'user_id', 'label' => 'Unsafe', 'type' => 'file', 'required' => true]],
        ])->assertSessionHasErrors(['application_fields.0.key', 'application_fields.0.type']);
    }

    public function test_removing_submission_token_does_not_bypass_historical_record_protection(): void
    {
        $citizen = $this->user();
        $service = $this->ready($citizen);
        $token = $this->preview($citizen, $service);
        $this->post('/citizen/services/'.$service->id.'/submit', ['preview_token' => $token, 'declaration' => true]);
        $application = $citizen->serviceRequests()->firstOrFail();
        $this->expectException(ValidationException::class);
        $application->forceFill(['submission_token' => null, 'reference' => 'CHANGED'])->save();
    }

    public function test_revoked_eligibility_after_preview_is_rechecked_without_catalog_change(): void
    {
        $citizen = $this->user();
        $service = $this->ready($citizen);
        $token = $this->preview($citizen, $service);
        $service->assessments()->update(['conditions_met' => false]);
        $this->post('/citizen/services/'.$service->id.'/submit', ['preview_token' => $token, 'declaration' => true])->assertSessionHasErrors('service');
        $this->assertSame(0, $citizen->serviceRequests()->count());
    }
}
