<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('government_services', 'require_separate_approver')) {
            Schema::table('government_services', fn (Blueprint $t) => $t->boolean('require_separate_approver')->default(false));
        }
        if (! Schema::hasColumn('citizen_service_requests', 'review_version')) {
            Schema::table('citizen_service_requests', function (Blueprint $t) {
                $t->unsignedInteger('review_version')->default(0);
                $t->foreignId('assigned_to')->nullable()->constrained('users')->restrictOnDelete();
                $t->foreignId('assigned_by')->nullable()->constrained('users')->restrictOnDelete();
                $t->dateTime('assigned_at')->nullable();
                $t->json('original_request_data')->nullable();
            });
        }
        if (! Schema::hasTable('service_review_permissions')) {
            Schema::create('service_review_permissions', function (Blueprint $t) {
                $t->id();
                $t->foreignId('user_id')->constrained()->restrictOnDelete();
                $t->foreignId('government_service_id')->constrained()->restrictOnDelete();
                $t->boolean('can_review')->default(false);
                $t->boolean('can_approve')->default(false);
                $t->boolean('can_assign')->default(false);
                $t->foreignId('granted_by')->constrained('users')->restrictOnDelete();
                $t->timestamps();
                $t->unique(['user_id', 'government_service_id'], 'review_permission_employee_service');
            });
        }
        if (! Schema::hasTable('review_decisions')) {
            Schema::create('review_decisions', function (Blueprint $t) {
                $t->id();
                $t->foreignId('citizen_service_request_id')->constrained()->restrictOnDelete();
                $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
                $t->string('action', 30);
                $t->string('from_status', 40)->nullable();
                $t->string('to_status', 40)->nullable();
                $t->uuid('decision_token')->unique();
                $t->string('command_hash', 64);
                $t->unsignedInteger('review_version');
                $t->text('public_reason')->nullable();
                $t->text('internal_notes')->nullable();
                $t->json('details')->nullable();
                $t->json('requirements_result')->nullable();
                $t->dateTime('decided_at');
                $t->timestamps();
                $t->index(['citizen_service_request_id', 'id'], 'review_decision_request_order');
            });
        }
        if (! Schema::hasTable('request_deficiencies')) {
            Schema::create('request_deficiencies', function (Blueprint $t) {
                $t->id();
                $t->foreignId('citizen_service_request_id')->constrained()->restrictOnDelete();
                $t->foreignId('review_decision_id')->constrained('review_decisions')->restrictOnDelete();
                $t->string('target_type', 30);
                $t->string('target_key', 100);
                $t->string('description', 500);
                $t->string('status', 30)->default('open');
                $t->string('citizen_response', 500)->nullable();
                $t->foreignId('resolved_by')->nullable()->constrained('users')->restrictOnDelete();
                $t->dateTime('resolved_at')->nullable();
                $t->string('resolution_note', 500)->nullable();
                $t->timestamps();
                $t->index(['citizen_service_request_id', 'status'], 'review_deficiency_request_state');
            });
        }
    }

    public function down(): void
    { /* Retain decisions, permissions, assignments and deficiency history. */
    }
};
