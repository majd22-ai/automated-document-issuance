<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('government_services', 'workflow_rules')) {
            Schema::table('government_services', fn (Blueprint $t) => $t->json('workflow_rules')->nullable());
        }
        if (! Schema::hasTable('appointment_slots')) {
            Schema::create('appointment_slots', function (Blueprint $t) {
                $t->id();
                $t->foreignId('government_service_id')->constrained()->restrictOnDelete();
                $t->string('center', 200);
                $t->dateTime('starts_at')->index();
                $t->dateTime('ends_at');
                $t->unsignedInteger('capacity');
                $t->boolean('is_active')->default(true);
                $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('citizen_appointments')) {
            Schema::create('citizen_appointments', function (Blueprint $t) {
                $t->id();
                $t->foreignId('user_id')->constrained()->restrictOnDelete();
                $t->foreignId('government_service_id')->constrained()->restrictOnDelete();
                $t->foreignId('citizen_service_request_id')->constrained()->restrictOnDelete();
                $t->foreignId('appointment_slot_id')->constrained()->restrictOnDelete();
                $t->string('status', 30)->default('booked');
                $t->unsignedBigInteger('active_request_key')->nullable()->unique();
                $t->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
                $t->timestamps();
                $t->index(['appointment_slot_id', 'status'], 'booking_slot_status');
            });
        }
        if (! Schema::hasTable('medical_examinations')) {
            Schema::create('medical_examinations', function (Blueprint $t) {
                $t->id();
                $t->foreignId('user_id')->constrained()->restrictOnDelete();
                $t->foreignId('government_service_id')->constrained()->restrictOnDelete();
                $t->foreignId('citizen_service_request_id')->constrained()->restrictOnDelete();
                $t->foreignId('citizen_appointment_id')->nullable()->constrained()->restrictOnDelete();
                $t->unsignedInteger('rules_version');
                $t->string('result', 30);
                $t->date('examined_at')->nullable();
                $t->date('expires_at')->nullable();
                $t->string('source_reference');
                $t->text('private_notes')->nullable();
                $t->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
                $t->dateTime('recorded_at');
                $t->timestamps();
                $t->index(['citizen_service_request_id', 'id'], 'exam_request_version');
            });
        }
        if (! Schema::hasTable('request_charges')) {
            Schema::create('request_charges', function (Blueprint $t) {
                $t->id();
                $t->foreignId('user_id')->constrained()->restrictOnDelete();
                $t->foreignId('government_service_id')->constrained()->restrictOnDelete();
                $t->foreignId('citizen_service_request_id')->unique()->constrained()->restrictOnDelete();
                $t->decimal('amount', 12, 2);
                $t->string('currency', 20);
                $t->string('status', 30)->default('due');
                $t->string('source_reference')->nullable();
                $t->timestamps();
            });
        }
        if (! Schema::hasTable('manual_payments')) {
            Schema::create('manual_payments', function (Blueprint $t) {
                $t->id();
                $t->foreignId('request_charge_id')->unique()->constrained()->restrictOnDelete();
                $t->decimal('amount', 12, 2);
                $t->string('currency', 20);
                $t->string('receipt_reference', 150)->unique();
                $t->string('method', 30);
                $t->date('paid_at');
                $t->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
                $t->dateTime('recorded_at');
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Preserve bookings, medical history and financial records. No destructive rollback.
    }
};
