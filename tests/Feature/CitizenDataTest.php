<?php

namespace Tests\Feature;

use App\Models\CitizenDocument;
use App\Models\CitizenServiceRequest;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CitizenDataTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $connection = DB::connection();
        if ($connection->getDatabaseName() !== ':memory:' && ! str_ends_with($connection->getDatabaseName(), '_testing')) {
            throw new \RuntimeException('An isolated database is required.');
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
        $user->forceFill(['role' => $role, 'username' => 'NATIONAL-'.$user->id])->save();

        return $user;
    }

    public function test_citizen_sees_only_their_data_and_documents(): void
    {
        $owner = $this->user();
        $other = $this->user();
        $owner->documents()->create(['document_type' => 'بطاقة شخصية', 'document_number' => 'OWN-DOC', 'status' => 'سارية', 'issued_at' => '2025-01-01', 'expires_at' => '2030-01-01']);
        $other->documents()->create(['document_type' => 'جواز', 'document_number' => 'SECRET-DOC', 'status' => 'سارية']);
        $this->actingAs($owner)->get('/citizen/data?user_id='.$other->id)->assertOk()->assertSee($owner->username)->assertDontSee($other->email)->assertHeader('Cache-Control', 'no-store, private');
        $this->get('/citizen/documents?user_id='.$other->id)->assertOk()->assertSee('OWN-DOC')->assertSee('2030-01-01')->assertDontSee('SECRET-DOC');
        $this->get('/citizen/data/'.$other->id)->assertNotFound();
        $this->get('/citizen/documents/'.$other->documents()->first()->id)->assertNotFound();
    }

    public function test_requests_are_persisted_linked_and_scoped_to_owner(): void
    {
        $owner = $this->user();
        $other = $this->user();
        $own = $owner->serviceRequests()->create(['reference' => 'REAL-OWN', 'service_code' => 'identity-renewal', 'status' => 'قيد المراجعة']);
        $foreign = $other->serviceRequests()->create(['reference' => 'REAL-SECRET', 'service_code' => 'passports-renewal', 'status' => 'قيد المراجعة']);
        $this->assertTrue($own->citizen->is($owner));
        $this->assertInstanceOf(CitizenServiceRequest::class, $owner->serviceRequests()->first());
        $this->actingAs($owner)->get('/citizen/applications')->assertOk()->assertSee('REAL-OWN')->assertDontSee('REAL-SECRET');
        $this->get('/citizen/applications/'.$own->id)->assertOk()->assertSee('REAL-OWN');
        $this->get('/citizen/applications/'.$foreign->id)->assertNotFound();
        $this->get('/citizen/applications/999999')->assertNotFound();
    }

    public function test_empty_records_are_explicit_and_never_seeded(): void
    {
        $user = $this->user();
        $this->actingAs($user)->get('/citizen/documents')->assertOk()->assertSee('لا توجد وثائق حكومية مرتبطة بحسابك')->assertDontSee('SAMPLE-');
        $this->get('/citizen/applications')->assertOk()->assertSee('لا توجد طلبات مرتبطة بحسابك')->assertDontSee('DEMO-1001');
        $this->assertSame(0, CitizenDocument::count());
        $this->assertSame(0, CitizenServiceRequest::count());
    }

    public function test_missing_national_id_blocks_service_entry_without_blocking_profile(): void
    {
        $user = $this->user();
        $user->forceFill(['username' => null])->save();
        $this->actingAs($user)->get('/citizen/applications/new?service=identity-renewal')->assertRedirect('/citizen/data')->assertSessionHas('citizen_data_error');
        $this->get('/citizen/data')->assertOk()->assertSee('الرقم الوطني')->assertSee('بياناتك غير مكتملة');
        $this->get('/profile')->assertOk();
    }

    public function test_required_fields_and_optional_phone_follow_existing_account_contract(): void
    {
        $user = $this->user();
        foreach (['name', 'email', 'username'] as $field) {
            $original = $user->{$field};
            $user->forceFill([$field => '   '])->save();
            $this->actingAs($user)->get('/citizen/applications/new')->assertRedirect('/citizen/data');
            $user->forceFill([$field => $original])->save();
        }
        $user->forceFill(['phone' => null])->save();
        $this->actingAs($user)->get('/citizen/applications/new')->assertOk();
    }

    public function test_employee_search_requires_input_and_returns_paginated_citizens_only(): void
    {
        $employee = $this->user('employee');
        User::factory()->count(16)->create(['name' => 'Matching Citizen']);
        $this->actingAs($employee)->get('/employee/citizens')->assertOk()->assertSee('أدخل الاسم أو الرقم الوطني')->assertViewHas('citizens', null);
        $response = $this->get('/employee/citizens?search=Matching&page=2')->assertOk();
        $this->assertSame(16, $response->viewData('citizens')->total());
        $this->assertCount(1, $response->viewData('citizens')->items());
        $citizen = $this->user();
        $citizen->documents()->create(['document_type' => 'بطاقة', 'document_number' => 'EMP-DOC']);
        $this->get('/employee/citizens?search='.$citizen->username)->assertOk()->assertSee($citizen->name)->assertDontSee($employee->email);
        $this->get('/employee/citizens/'.$citizen->id)->assertOk()->assertSee($citizen->username)->assertSee('EMP-DOC');
        $this->get('/employee/citizens/'.$employee->id)->assertForbidden();
        $this->get('/employee/citizens?search=does-not-exist')->assertOk()->assertSee('لم يتم العثور على مواطن');
        $this->get('/employee/citizens?search[]=bad')->assertSessionHasErrors('search');
        $this->get('/employee/citizens?search=a')->assertSessionHasErrors('search');
    }

    public function test_guest_citizen_and_admin_cannot_use_employee_lookup(): void
    {
        $citizen = $this->user();
        foreach (['/citizen/data', '/citizen/documents', '/employee/citizens', '/employee/citizens/'.$citizen->id] as $path) {
            $this->get($path)->assertRedirect('/login');
        }
        foreach ([$citizen, $this->user('admin')] as $user) {
            $this->actingAs($user)->get('/employee/citizens')->assertForbidden();
            $this->get('/employee/citizens/'.$citizen->id)->assertForbidden();
        }
        $this->actingAs($this->user('employee'))->get('/citizen/data')->assertForbidden();
        $citizen->forceFill(['is_active' => false])->save();
        $this->actingAs($citizen)->get('/citizen/data')->assertRedirect('/login');
    }

    public function test_documents_have_owner_relationship_and_restrict_account_deletion(): void
    {
        $owner = $this->user();
        $document = $owner->documents()->create(['document_type' => 'هوية', 'document_number' => 'PERSISTED']);
        $this->assertTrue($document->citizen->is($owner));
        $this->assertInstanceOf(CitizenDocument::class, $owner->documents()->first());
        $this->expectException(QueryException::class);
        $owner->delete();
    }

    public function test_policy_rejects_other_citizen_and_privileged_account_targets(): void
    {
        $owner = $this->user();
        $other = $this->user();
        $employee = $this->user('employee');
        $this->assertTrue($owner->can('viewCitizenData', $owner));
        $this->assertFalse($owner->can('viewCitizenData', $other));
        $this->assertFalse($employee->can('viewCitizenData', $this->user('admin')));
        $this->assertTrue($employee->can('viewCitizenData', $owner));
    }

    public function test_migration_reapplication_preserves_existing_documents_and_requests(): void
    {
        $owner = $this->user();
        $document = $owner->documents()->create(['document_type' => 'بطاقة', 'document_number' => 'KEEP-DOC']);
        $application = $owner->serviceRequests()->create(['reference' => 'KEEP-REQUEST', 'service_code' => 'identity-renewal']);
        $migration = require database_path('migrations/2026_10_22_000001_create_citizen_records.php');
        $migration->down();
        $migration->up();
        $this->assertTrue($document->fresh()->citizen->is($owner));
        $this->assertTrue($application->fresh()->citizen->is($owner));
    }
}
