<?php

namespace Tests\Feature;

use App\Models\ServiceReviewPermission;
use App\Models\User;
use App\Notifications\CitizenActivity;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WorkspaceExperienceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $db = DB::connection();
        if ($db->getDatabaseName() !== ':memory:' && ! str_ends_with($db->getDatabaseName(), '_testing')) {
            throw new \RuntimeException('Isolated test DB required');
        }
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $db->beginTransaction();
        $this->beforeApplicationDestroyed(function () use ($db) {
            $db->rollBack();
            $db->disconnect();
        });
    }

    public function test_admin_pages_have_workspace_navigation_and_keep_real_forms(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach (['users', 'employees', 'departments', 'services', 'review-permissions', 'reports', 'integrations', 'settings', 'audit-logs'] as $page) {
            $this->get('/admin/'.$page)->assertOk()->assertSee('id="ops-navigation"', false)->assertSee('operations-workspace.css');
        }
    }

    public function test_staff_workspace_hides_ungranted_sidebar_actions(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);
        $html = $this->actingAs($employee)->get('/employee/dashboard')->assertOk()->getContent();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($dom);
        $this->assertSame(1, $xpath->query('//*[@id="ops-navigation"]')->length);
        $this->assertSame(0, $xpath->query('//*[@id="ops-navigation"]//a[@href="'.route('employee.issuance.index').'"]')->length);
        $this->assertSame(0, $xpath->query('//*[@id="ops-navigation"]//a[@href="'.route('employee.accounts.index').'"]')->length);
        $this->get('/')->assertDontSee('id="ops-navigation"', false);
    }

    public function test_inbox_open_checks_current_request_permission_and_owns_read_actions(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);
        $other = User::factory()->create(['role' => 'employee']);
        $citizen = User::factory()->create();
        $application = $citizen->serviceRequests()->create(['reference' => 'WORKSPACE-TEST', 'service_code' => 'identity-first-issue', 'status' => 'submitted']);
        $permission = (new ServiceReviewPermission)->forceFill(['user_id' => $employee->id, 'government_service_id' => $application->government_service_id, 'can_review' => true, 'granted_by' => $employee->id]);
        $permission->save();
        $employee->notifyNow(new CitizenActivity('workspace-activity', ['kind' => 'request_activity', 'title' => 'Update', 'body' => 'Request changed'], $application->id));
        $row = $employee->notifications()->firstOrFail();
        $this->actingAs($other)->post('/employee/notifications/'.$row->id.'/read')->assertNotFound();
        $this->actingAs($employee)->post('/employee/notifications/'.$row->id.'/open')->assertRedirect(route('employee.tracking.show', $application->id));
        $permission->forceFill(['can_review' => false])->save();
        $this->post('/employee/notifications/'.$row->id.'/open')->assertForbidden();
    }

    public function test_account_notifications_open_own_profile_and_badge_count_is_private(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);
        $other = User::factory()->create(['role' => 'employee']);
        $employee->notifyNow(new CitizenActivity('workspace-account', ['kind' => 'employee_account', 'title' => 'Account changed', 'body' => 'Review your account', 'target_user_id' => $employee->id]));
        $other->notifyNow(new CitizenActivity('workspace-other', ['kind' => 'employee_account', 'title' => 'Other', 'body' => 'Other']));
        $row = $employee->notifications()->firstOrFail();
        $this->actingAs($employee)->getJson('/notifications/unread-count?user_id='.$other->id)->assertOk()->assertJson(['unread' => 1])->assertHeader('Cache-Control', 'no-store, private');
        $this->post('/employee/notifications/'.$row->id.'/open')->assertRedirect(route('profile.edit'));
        $this->getJson('/notifications/unread-count')->assertJson(['unread' => 0]);
        $this->post('/employee/notifications/read-all')->assertRedirect();
        $this->assertSame(1, $other->unreadNotifications()->count());
    }
}
