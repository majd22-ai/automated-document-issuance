<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'can_access_demo_civil_registry')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('can_access_demo_civil_registry')->default(false);
            });
        }

        Schema::create('demo_civil_people', function (Blueprint $table) {
            $table->id();
            $table->string('fixture_key', 80)->nullable()->unique();
            $table->string('demo_identifier', 40)->unique();
            $table->string('full_name', 180)->index();
            $table->string('gender', 20);
            $table->date('birth_date');
            $table->string('birth_place', 160);
            $table->string('marital_status', 30)->default('single');
            $table->string('synthetic_address', 240);
            $table->string('photo_path')->nullable();
            $table->string('photo_sha256', 64)->nullable();
            $table->unsignedBigInteger('photo_byte_size')->nullable();
            $table->boolean('is_synthetic')->default(true);
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('demo_civil_families', function (Blueprint $table) {
            $table->id();
            $table->string('fixture_key', 80)->nullable()->unique();
            $table->string('demo_identifier', 40)->unique();
            $table->foreignId('head_person_id')->nullable()->constrained('demo_civil_people')->restrictOnDelete();
            $table->boolean('is_synthetic')->default(true);
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('demo_civil_family_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_id')->constrained('demo_civil_families')->restrictOnDelete();
            $table->foreignId('person_id')->constrained('demo_civil_people')->restrictOnDelete();
            $table->string('relationship', 30);
            $table->string('status', 30)->default('active');
            $table->date('joined_on')->nullable();
            $table->timestamps();
            $table->unique(['family_id', 'person_id']);
            $table->index(['person_id', 'status']);
        });

        Schema::create('demo_civil_records', function (Blueprint $table) {
            $table->id();
            $table->string('fixture_key', 100)->nullable()->unique();
            $table->string('record_type', 30)->index();
            $table->string('demo_identifier', 60)->unique();
            $table->foreignId('person_id')->constrained('demo_civil_people')->restrictOnDelete();
            $table->foreignId('family_id')->nullable()->constrained('demo_civil_families')->restrictOnDelete();
            $table->foreignId('related_record_id')->nullable()->constrained('demo_civil_records')->restrictOnDelete();
            $table->date('occurred_on')->nullable();
            $table->string('place', 180)->nullable();
            $table->string('status', 40)->default('active');
            $table->json('details')->nullable();
            $table->boolean('is_synthetic')->default(true);
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamps();
            $table->index(['person_id', 'record_type', 'archived_at']);
        });

        Schema::create('demo_civil_record_parties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('record_id')->constrained('demo_civil_records')->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('demo_civil_people')->restrictOnDelete();
            $table->string('party_role', 30);
            $table->timestamps();
            $table->unique(['record_id', 'person_id', 'party_role']);
            $table->index(['person_id', 'party_role']);
        });

        Schema::create('demo_civil_documents', function (Blueprint $table) {
            $table->id();
            $table->string('fixture_key', 100)->nullable()->unique();
            $table->foreignId('person_id')->constrained('demo_civil_people')->restrictOnDelete();
            $table->foreignId('record_id')->nullable()->constrained('demo_civil_records')->restrictOnDelete();
            $table->string('demo_identifier', 80)->unique();
            $table->string('label', 160);
            $table->string('storage_path', 240);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('byte_size');
            $table->string('sha256', 64);
            $table->boolean('is_synthetic')->default(true);
            $table->timestamp('archived_at')->nullable()->index();
            $table->timestamps();
            $table->index(['person_id', 'record_id', 'archived_at']);
        });

        Schema::create('demo_civil_registry_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor_role', 30)->default('guest');
            $table->string('action', 80)->index();
            $table->foreignId('person_id')->nullable()->constrained('demo_civil_people')->nullOnDelete();
            $table->foreignId('record_id')->nullable()->constrained('demo_civil_records')->nullOnDelete();
            $table->foreignId('document_id')->nullable()->constrained('demo_civil_documents')->nullOnDelete();
            $table->foreignId('citizen_service_request_id')->nullable()->constrained()->nullOnDelete();
            $table->string('outcome', 30);
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent_hash', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'created_at']);
            $table->index(['person_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_civil_registry_audits');
        Schema::dropIfExists('demo_civil_documents');
        Schema::dropIfExists('demo_civil_record_parties');
        Schema::dropIfExists('demo_civil_records');
        Schema::dropIfExists('demo_civil_family_members');
        Schema::dropIfExists('demo_civil_families');
        Schema::dropIfExists('demo_civil_people');
        if (Schema::hasColumn('users', 'can_access_demo_civil_registry')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('can_access_demo_civil_registry');
            });
        }
    }
};