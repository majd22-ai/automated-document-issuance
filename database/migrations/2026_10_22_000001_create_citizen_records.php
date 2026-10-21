<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('citizen_documents')) {
            Schema::create('citizen_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->restrictOnDelete();
                $table->string('document_type', 100);
                $table->string('document_number', 100);
                $table->string('status', 100)->nullable();
                $table->date('issued_at')->nullable();
                $table->date('expires_at')->nullable();
                $table->timestamps();
                $table->unique(['user_id', 'document_type', 'document_number'], 'citizen_document_identity');
            });
        }
        if (! Schema::hasTable('citizen_service_requests')) {
            Schema::create('citizen_service_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->restrictOnDelete();
                $table->string('reference', 100)->unique();
                $table->string('service_code', 100);
                $table->string('status', 100)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Retain official records when reverting application code; no destructive rollback.
    }
};
