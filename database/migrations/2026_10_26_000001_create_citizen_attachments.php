<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('citizen_attachments')) {
            if (! Schema::hasIndex('citizen_attachments', 'attachments_owner_context')) {
                Schema::table('citizen_attachments', fn (Blueprint $table) => $table->index(['user_id', 'government_service_id', 'citizen_service_request_id'], 'attachments_owner_context'));
            }
            return;
        }
        Schema::create('citizen_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('government_service_id')->constrained()->restrictOnDelete();
            $table->foreignId('citizen_service_request_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('document_code', 40);
            $table->string('document_name', 500);
            $table->string('kind', 20);
            $table->unsignedInteger('rules_version');
            $table->string('slot_key', 64)->unique();
            $table->uuid('version_token');
            $table->string('storage_path');
            $table->string('display_name', 160);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size');
            $table->string('sha256', 64);
            $table->string('review_status', 30)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('public_reason', 500)->nullable();
            $table->date('expires_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'government_service_id', 'citizen_service_request_id'], 'attachments_owner_context');
        });
    }

    public function down(): void
    {
        // Preserve personal files and references; removal requires a deliberate retention procedure.
    }
};
