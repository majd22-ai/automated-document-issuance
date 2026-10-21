<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('government_services', 'verification_rules')) {
            Schema::table('government_services', fn (Blueprint $table) => $table->json('verification_rules')->nullable());
        }
        if (! Schema::hasColumn('citizen_service_assessments', 'public_block_reason')) {
            Schema::table('citizen_service_assessments', fn (Blueprint $table) => $table->string('public_block_reason')->nullable());
        }
        if (! Schema::hasColumn('citizen_service_requests', 'requirements_result')) {
            Schema::table('citizen_service_requests', fn (Blueprint $table) => $table->json('requirements_result')->nullable());
        }
        if (! Schema::hasColumn('citizen_service_requests', 'requirements_checked_at')) {
            Schema::table('citizen_service_requests', fn (Blueprint $table) => $table->timestamp('requirements_checked_at')->nullable());
        }
    }

    public function down(): void
    {
        // Retain requirement results and approved configuration; no destructive rollback.
    }
};
