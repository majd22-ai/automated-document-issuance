<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('identity_card_forms', function (Blueprint $t) {
            $t->unsignedSmallInteger('journey_version')->nullable();
            $t->text('witness_attendance_reason')->nullable();
            $t->foreignId('witness_attendance_requested_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('witness_attendance_requested_at')->nullable();
        });
        Schema::table('request_charges', fn (Blueprint $t) => $t->string('invoice_number', 50)->nullable()->unique());
        Schema::create('postal_collections', function (Blueprint $t) {
            $t->id();
            $t->foreignId('request_charge_id')->unique()->constrained()->restrictOnDelete();
            $t->foreignId('postal_collector_id')->constrained()->restrictOnDelete();
            $t->foreignId('postal_office_id')->constrained()->restrictOnDelete();
            $t->string('receipt_reference', 50)->unique();
            $t->uuid('operation_token')->unique();
            $t->string('command_hash', 64);
            $t->decimal('amount', 12, 2);
            $t->string('currency', 20);
            $t->timestamp('collected_at');
            $t->timestamps();
        });
        Schema::create('identity_visit_evidence', function (Blueprint $t) {
            $t->id();
            $t->foreignId('identity_card_form_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('form_revision');
            $t->string('kind', 30);
            $t->foreignId('citizen_attachment_id')->constrained()->restrictOnDelete();
            $t->string('status', 30)->default('pending');
            $t->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->text('reason')->nullable();
            $t->timestamps();
            $t->index(['identity_card_form_id', 'form_revision', 'kind'], 'identity_visit_lookup');
        });
    }

    public function down(): void
    {
        // Preserve financial and identity history. Rollback requires a reviewed forward migration.
    }
};
