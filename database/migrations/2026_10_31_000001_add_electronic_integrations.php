<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('government_services', 'integration_rules')) {
            Schema::table('government_services', fn (Blueprint $t) => $t->json('integration_rules')->nullable());
        }
        if (! Schema::hasColumn('service_review_permissions', 'can_verify')) {
            Schema::table('service_review_permissions', fn (Blueprint $t) => $t->boolean('can_verify')->default(false));
        }
        if (! Schema::hasTable('integration_attempts')) {
            Schema::create('integration_attempts', function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->foreignId('citizen_service_request_id')->constrained()->cascadeOnDelete();
                $t->foreignId('user_id')->constrained()->cascadeOnDelete();
                $t->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $t->foreignId('target_document_id')->nullable()->constrained('citizen_documents')->nullOnDelete();
                $t->string('kind', 20);
                $t->string('provider', 60);
                $t->string('status', 30);
                $t->string('idempotency_hash', 64)->unique();
                $t->string('active_key', 64)->nullable()->unique();
                $t->string('input_fingerprint', 64);
                $t->string('error_code', 40)->nullable();
                $t->unsignedSmallInteger('http_status')->nullable();
                $t->unsignedTinyInteger('attempts')->default(0);
                $t->unsignedInteger('duration_ms')->nullable();
                $t->dateTime('started_at');
                $t->dateTime('finished_at')->nullable();
                $t->dateTime('expires_at')->nullable();
                $t->timestamps();
                $t->index(['citizen_service_request_id', 'kind', 'created_at'], 'integration_request_history');
                $t->index(['status', 'started_at']);
            });
        }
    }

    public function down(): void
    { /* Retain verification audit and configuration. */
    }
};
