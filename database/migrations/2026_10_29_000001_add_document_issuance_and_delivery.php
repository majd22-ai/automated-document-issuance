<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Never delete or renumber legacy documents to make a constraint fit.
        if (DB::table('citizen_documents')->select('document_number')->groupBy('document_number')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Duplicate legacy document numbers require authorized reconciliation before this migration; no records were changed.');
        }
        if (! Schema::hasColumn('government_services', 'issuance_rules')) {
            Schema::table('government_services', fn (Blueprint $t) => $t->json('issuance_rules')->nullable());
        }
        if (! Schema::hasColumn('service_review_permissions', 'can_issue')) {
            Schema::table('service_review_permissions', function (Blueprint $t) {
                $t->boolean('can_issue')->default(false);
                $t->boolean('can_deliver')->default(false);
            });
        }
        if (! Schema::hasColumn('citizen_service_requests', 'completed_at')) {
            Schema::table('citizen_service_requests', function (Blueprint $t) {
                $t->dateTime('completed_at')->nullable();
                $t->foreignId('completed_by')->nullable()->constrained('users')->restrictOnDelete();
            });
        }
        if (! Schema::hasColumn('citizen_documents', 'citizen_service_request_id')) {
            Schema::table('citizen_documents', function (Blueprint $t) {
                $t->foreignId('citizen_service_request_id')->nullable()->constrained()->restrictOnDelete();
                $t->unique('citizen_service_request_id', 'document_once_per_request');
                $t->foreignId('government_service_id')->nullable()->constrained()->restrictOnDelete();
                $t->foreignId('previous_document_id')->nullable()->constrained('citizen_documents')->restrictOnDelete();
                $t->unique('previous_document_id', 'document_one_successor');
                $t->foreignId('issued_by')->nullable()->constrained('users')->restrictOnDelete();
                $t->dateTime('issued_recorded_at')->nullable();
                $t->json('issuance_rules')->nullable();
                $t->text('holder_snapshot')->nullable();
                $t->unsignedInteger('workflow_version')->default(0);
                $t->unique('document_number', 'document_number_global_unique');
                $t->index(['user_id', 'status'], 'document_citizen_status');
            });
        }
        if (! Schema::hasTable('document_copies')) {
            Schema::create('document_copies', function (Blueprint $t) {
                $t->id();
                $t->foreignId('citizen_document_id')->constrained()->restrictOnDelete();
                $t->string('storage_path')->unique();
                $t->string('sha256', 64);
                $t->unsignedBigInteger('byte_size');
                $t->string('template', 50);
                $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $t->timestamps();
                $t->index(['citizen_document_id', 'id'], 'document_copy_history');
            });
        }
        if (! Schema::hasTable('document_deliveries')) {
            Schema::create('document_deliveries', function (Blueprint $t) {
                $t->id();
                $t->foreignId('citizen_document_id')->unique()->constrained()->restrictOnDelete();
                $t->string('method', 20)->nullable();
                $t->string('center', 200)->nullable();
                $t->text('address')->nullable();
                $t->string('status', 30)->default('awaiting_choice');
                $t->dateTime('delivered_at')->nullable();
                $t->foreignId('delivered_by')->nullable()->constrained('users')->restrictOnDelete();
                $t->string('proof_reference', 150)->nullable();
                $t->timestamps();
                $t->index(['status', 'id'], 'delivery_queue_status');
            });
        }
        if (! Schema::hasTable('document_events')) {
            Schema::create('document_events', function (Blueprint $t) {
                $t->id();
                $t->foreignId('citizen_document_id')->constrained()->restrictOnDelete();
                $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
                $t->string('action', 40);
                $t->string('from_status', 40)->nullable();
                $t->string('to_status', 40)->nullable();
                $t->uuid('operation_token')->unique();
                $t->string('command_hash', 64);
                $t->string('reason', 500)->nullable();
                $t->json('details')->nullable();
                $t->dateTime('occurred_at');
                $t->timestamps();
                $t->index(['citizen_document_id', 'id'], 'document_event_history');
            });
        }
    }

    public function down(): void
    { /* Retain document, file, delivery and audit history. */
    }
};
