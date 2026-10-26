<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // MySQL DDL is not transactional: safely resume after an interrupted deployment.
        foreach (['national_id', 'registration_status', 'identity_path', 'phone_verified_at'] as $column) {
            if (! Schema::hasColumn('users', $column)) {
                Schema::table('users', function (Blueprint $table) use ($column) {
                    match ($column) {
                        'national_id' => $table->string('national_id', 20)->nullable()->unique(),
                        // NULL preserves legacy accounts; it does not assert verified civil identity.
                        'registration_status' => $table->string('registration_status', 40)->nullable(),
                        'identity_path' => $table->string('identity_path', 30)->nullable(),
                        'phone_verified_at' => $table->timestamp('phone_verified_at')->nullable(),
                    };
                });
            }
        }
        if (Schema::hasTable('registration_attempts')) {
            return;
        }
        Schema::create('registration_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('session_key', 64)->unique();
            $table->text('payload'); // encrypted; contains only a password hash, never a raw password
            $table->string('otp_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedTinyInteger('sends')->default(1);
            $table->dateTime('sent_at');
            $table->dateTime('expires_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Registration migration is intentionally irreversible. Retain identity data; use a reviewed forward migration.');
    }
};
