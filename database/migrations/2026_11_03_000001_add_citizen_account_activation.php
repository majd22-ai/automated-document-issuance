<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['account_activated_at', 'identity_verified_at'] as $column) {
            if (! Schema::hasColumn('users', $column)) {
                Schema::table('users', fn (Blueprint $table) => $table->dateTime($column)->nullable());
            }
        }
        if (! Schema::hasColumn('service_review_permissions', 'can_activate')) {
            Schema::table('service_review_permissions', fn (Blueprint $table) => $table->boolean('can_activate')->default(false));
        }
        if (! Schema::hasTable('citizen_account_confirmations')) {
            Schema::create('citizen_account_confirmations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->restrictOnDelete();
                $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
                $table->foreignId('citizen_service_request_id')->nullable()->constrained()->restrictOnDelete();
                $table->uuid('operation_token')->unique();
                $table->string('action', 30);
                $table->string('result', 20);
                $table->text('evidence_reference'); // encrypted, never part of the operational audit payload
                $table->string('payload_fingerprint', 64);
                $table->boolean('identity_confirmed');
                $table->dateTime('confirmed_at');
                $table->index(['user_id', 'confirmed_at']);
            });
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Citizen activation history is retained. Use a reviewed forward migration.');
    }
};
