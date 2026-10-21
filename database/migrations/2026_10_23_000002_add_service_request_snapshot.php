<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('citizen_service_requests', 'service_snapshot')) {
            Schema::table('citizen_service_requests', fn (Blueprint $table) => $table->json('service_snapshot')->nullable());
        }
    }

    public function down(): void
    {
        // Preserve historical service details; do not delete request snapshots.
    }
};
