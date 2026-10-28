<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_card_forms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('government_service_id')->constrained()->restrictOnDelete();
            $table->foreignId('citizen_service_request_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('citizen_attachment_id')->nullable()->constrained('citizen_attachments')->restrictOnDelete();
            $table->string('reference', 40)->unique();
            $table->string('status', 50)->default('draft')->index();
            $table->unsignedTinyInteger('current_step')->default(1);
            $table->unsignedInteger('version')->default(1);
            $table->json('data')->nullable();
            $table->timestamp('declared_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
        Schema::create('identity_card_witnesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('identity_card_form_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('position');
            foreach (['name', 'document_type', 'document_number', 'issued_place', 'employer', 'work_address', 'phone', 'address'] as $field) {
                $table->string($field)->nullable();
            }
            $table->date('issued_at')->nullable();
            $table->string('verification_status')->default('not_verified');
            $table->string('attendance_status')->default('not_recorded');
            $table->timestamps();
            $table->unique(['identity_card_form_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('identity_card_witnesses');
        Schema::dropIfExists('identity_card_forms');
    }
};
