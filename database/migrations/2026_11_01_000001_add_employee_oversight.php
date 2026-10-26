<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $t) {
            $t->id();
            $t->string('name', 150)->unique();
            $t->string('description', 500)->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('employee_number', 50)->nullable()->unique();
            $t->string('job_title', 150)->nullable();
            $t->string('report_scope', 20)->default('none');
            $t->boolean('can_export_reports')->default(false);
            $t->timestamp('last_login_at')->nullable();
            $t->unsignedInteger('auth_version')->default(0);
            $t->index(['role', 'is_active', 'department_id'], 'staff_filter');
        });
        Schema::create('audit_events', function (Blueprint $t) {
            $t->id();
            $t->uuid('trace_id')->index();
            $t->string('event', 100)->index();
            $t->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('subject_type', 50);
            $t->string('subject_id', 100)->nullable();
            $t->string('result', 30);
            $t->json('changes')->nullable();
            $t->timestamp('occurred_at')->index();
            $t->index(['subject_type', 'subject_id']);
            $t->index(['actor_id', 'occurred_at']);
            $t->index(['department_id', 'occurred_at']);
        });
        Schema::table('citizen_service_requests', fn (Blueprint $t) => $t->index(['created_at', 'status'], 'request_reporting_period'));
        Schema::table('manual_payments', fn (Blueprint $t) => $t->index('paid_at', 'payment_reporting_period'));
        Schema::table('citizen_documents', fn (Blueprint $t) => $t->index('issued_recorded_at', 'document_reporting_period'));
        Schema::table('review_decisions', fn (Blueprint $t) => $t->index(['actor_id', 'decided_at'], 'review_reporting_actor'));
        Schema::table('integration_attempts', fn (Blueprint $t) => $t->string('origin', 30)->default('unknown'));
    }

    public function down(): void
    { /* Retain staff organization and audit history. */
    }
};
