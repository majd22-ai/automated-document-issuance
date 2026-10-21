<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Complete an interrupted MySQL migration without recreating or removing its table.
        if (Schema::hasTable('citizen_attachments') && ! Schema::hasIndex('citizen_attachments', 'attachments_owner_context')) {
            Schema::table('citizen_attachments', fn (Blueprint $table) => $table->index(['user_id', 'government_service_id', 'citizen_service_request_id'], 'attachments_owner_context'));
        }
    }

    public function down(): void {}
};
