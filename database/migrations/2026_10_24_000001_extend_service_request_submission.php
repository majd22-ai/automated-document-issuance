<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('government_services', 'application_fields')) {
            Schema::table('government_services', fn (Blueprint $table) => $table->json('application_fields')->nullable());
        }
        foreach (['request_type', 'request_data', 'notes', 'declared_at', 'submitted_at', 'submission_token', 'active_request_key', 'citizen_visible_reason'] as $column) {
            if (Schema::hasColumn('citizen_service_requests', $column)) {
                continue;
            }
            Schema::table('citizen_service_requests', function (Blueprint $table) use ($column) {
                match ($column) {
                    'request_type' => $table->string('request_type', 30)->nullable(),
                    'request_data' => $table->json('request_data')->nullable(),
                    'notes', 'citizen_visible_reason' => $table->text($column)->nullable(),
                    'declared_at', 'submitted_at' => $table->timestamp($column)->nullable(),
                    'submission_token' => $table->string($column, 36)->nullable()->unique(),
                    'active_request_key' => $table->string($column, 64)->nullable()->unique(),
                };
            });
        }
    }

    public function down(): void
    {
        // Preserve submitted requests and historical declarations; no destructive rollback.
    }
};
