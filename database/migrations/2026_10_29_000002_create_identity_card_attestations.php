<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_card_attestations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('identity_card_form_id')->unique()->constrained()->restrictOnDelete();
            $table->string('reference', 40)->unique();
            $table->string('authority_type', 30);
            $table->string('recipient_name', 150);
            $table->string('status', 20)->default('pending')->index();
            // One simulated message belongs to this attestation; no external identity/account is created.
            $table->timestamp('notification_created_at')->nullable();
            $table->timestamp('notification_read_at')->nullable();
            $table->foreignId('notification_read_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            // Security-office paperwork remains a separate, unperformed procedure.
            $table->boolean('security_required')->default(false);
            $table->string('security_status', 20)->default('not_required');
            $table->string('security_reference')->nullable();
            $table->timestamps();
        });
        Schema::table('identity_card_witnesses', function (Blueprint $table) {
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('review_note', 1000)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('identity_card_witnesses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['reviewed_at', 'review_note']);
        });
        Schema::dropIfExists('identity_card_attestations');
    }
};
