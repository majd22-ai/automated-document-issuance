<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('citizen_account_confirmations', function (Blueprint $table) {
            $table->string('fingerprint_source', 30)->nullable();
            $table->string('phone_verification_source', 30)->nullable();
            $table->uuid('activation_visit_token')->nullable();
        });
        Schema::table('citizen_activation_requests', function (Blueprint $table) {
            $table->uuid('visit_token')->nullable()->unique();
            $table->foreignId('visit_actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('identity_type', 30)->nullable();
            $table->text('identity_number')->nullable();
            $table->string('identity_source', 40)->nullable();
            $table->text('visit_phone')->nullable();
            $table->string('account_binding', 64)->nullable();
            $table->dateTime('visit_expires_at')->nullable();
            $table->string('otp_hash')->nullable();
            $table->string('otp_source', 30)->nullable();
            $table->unsignedInteger('otp_attempts')->default(0);
            $table->unsignedInteger('otp_sends')->default(0);
            $table->dateTime('otp_sent_at')->nullable();
            $table->dateTime('otp_expires_at')->nullable();
            $table->dateTime('otp_verified_at')->nullable();
            $table->dateTime('visit_consumed_at')->nullable();
        });
        Schema::create('activation_fingerprint_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activation_request_id')->constrained('citizen_activation_requests')->restrictOnDelete();
            $table->uuid('visit_token')->index();
            $table->uuid('operation_token')->unique();
            $table->string('finger', 15);
            $table->string('result', 15);
            $table->string('source', 30);
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->dateTime('performed_at');
            $table->string('failure_reason')->nullable();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Activation evidence is retained; use a reviewed forward migration.');
    }
};
