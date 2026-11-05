<?php

namespace Tests\Feature;

use App\Models\GovernmentService;
use App\Models\NeighborhoodOfficial;
use App\Models\ResidenceDistrict;
use App\Models\ServiceReviewPermission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WorkspaceIsolationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDatabaseName() !== ':memory:') {
            throw new \RuntimeException('Isolated DB required');
        } $this->artisan('migrate', ['--force' => true])->assertSuccessful();
    }

    private function employee(array $abilities = []): User
    {
        $u = User::factory()->create(['role' => 'employee']);
        if ($abilities) {
            (new ServiceReviewPermission)->forceFill(['user_id' => $u->id, 'government_service_id' => GovernmentService::where('code', 'identity-first-issue')->value('id'), 'granted_by' => $u->id] + array_fill_keys($abilities, true))->save();
        }

return $u;
    }

    public function test_employee_without_grants_cannot_open_other_modules_or_see_their_links(): void
    {
        $this->actingAs($this->employee());
        $this->get('/employee/dashboard')->assertOk()->assertDontSee('href="'.route('employee.citizens.index').'"', false)->assertDontSee('portal-demo', false);
        foreach (['/employee/citizens', '/employee/attachments', '/employee/appointments', '/employee/appointment-slots', '/employee/payments', '/employee/tracking', '/employee/verifications'] as $url) {
            $this->get($url)->assertForbidden();
        }
    }

    public function test_verifier_sees_only_verification_work_and_profile(): void
    {
        $this->actingAs($this->employee(['can_verify']));
        $this->get('/employee/dashboard')->assertOk()->assertSee('href="'.route('employee.verifications.index').'"', false)->assertDontSee('href="'.route('employee.applications.index').'"', false)->assertDontSee('href="'.route('employee.payments.index').'"', false);
        $this->get('/employee/payments')->assertForbidden();
        $this->get('/employee/verifications')->assertOk();
    }

    public function test_neighborhood_official_and_regular_employee_have_separate_modules(): void
    {
        $official = $this->employee();
        $district = ResidenceDistrict::firstOrFail();
        (new NeighborhoodOfficial)->forceFill(['user_id' => $official->id, 'district_id' => $district->id, 'full_name' => $official->name, 'national_id' => '123456789012', 'phone' => '+967777123456', 'neighborhood' => 'حي الاختبار', 'is_active' => true, 'can_attest' => true])->save();
        $this->actingAs($official)->get('/employee/dashboard')->assertOk()->assertSee('href="'.route('employee.neighborhood-attestations.index').'"', false)->assertDontSee('href="'.route('employee.applications.index').'"', false)->assertDontSee('href="'.route('employee.citizens.index').'"', false);
        foreach (['/employee/applications', '/employee/citizens', '/employee/attachments', '/employee/payments', '/employee/accounts', '/employee/issuance'] as $url) {
            $this->get($url)->assertForbidden();
        }
        $reviewer = $this->employee(['can_review']);
        $this->actingAs($reviewer)->get('/employee/dashboard')->assertOk()->assertDontSee('href="'.route('employee.neighborhood-attestations.index').'"', false);
        $this->get('/employee/neighborhood-attestations')->assertForbidden();
    }

    public function test_revocation_removes_links_and_prevents_direct_access(): void
    {
        $employee = $this->employee(['can_review']);
        $this->actingAs($employee)->get('/employee/attachments')->assertOk();
        $employee->reviewPermissions()->update(['can_review' => false]);
        $this->get('/employee/attachments')->assertForbidden();
        $this->get('/employee/dashboard')->assertOk()->assertDontSee('href="'.route('employee.attachments.index').'"', false);
    }

    public function test_workspace_has_one_section_link_and_user_management_filters_work(): void
    {
        $this->actingAs($this->employee(['can_review', 'can_verify']));
        $html = $this->get('/employee/dashboard')->assertOk()->getContent();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($dom);
        foreach (['employee.applications.index', 'employee.attachments.index', 'employee.verifications.index'] as $destination) {
            $this->assertSame(1, $xpath->query('//a[@href="'.route($destination).'"]')->length);
        }
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = $this->employee();
        $citizen = User::factory()->create(['name' => 'PRIVATE-CITIZEN-NAME']);
        $this->actingAs($admin)->get('/admin/users?role=employee')->assertOk()->assertSee($employee->email)->assertDontSee($citizen->email)->assertSee('البحث والتصفية');
        $this->get('/admin/users?role=invalid')->assertSessionHasErrors('role');
    }

    public function test_request_data_is_scoped_to_the_employees_service(): void
    {
        $staff = $this->employee(['can_review']);
        $citizen = User::factory()->create();
        $own = $citizen->serviceRequests()->create(['reference' => 'OWN-SCOPE', 'service_code' => 'identity-first-issue', 'status' => 'submitted']);
        $other = $citizen->serviceRequests()->create(['reference' => 'OTHER-SCOPE', 'service_code' => 'passport-first-issue', 'status' => 'submitted']);
        $this->assertNotEquals($own->government_service_id, $other->government_service_id);
        $this->assertTrue($staff->can('view', $own));
        $this->assertFalse($staff->can('view', $other));
        $this->actingAs($staff)->get(route('employee.citizens.show', $citizen))->assertOk()->assertSee('OWN-SCOPE')->assertDontSee('OTHER-SCOPE');
    }
}
