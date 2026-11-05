<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mock_civil_families', function (Blueprint $t) {
            $t->id();
            $t->string('reference')->unique();
            $t->timestamps();
        });
        Schema::create('mock_civil_persons', function (Blueprint $t) {
            $t->id();
            $t->string('fixture_key')->unique();
            $t->string('name');
            $t->string('national_id')->nullable()->unique();
            $t->date('birth_date');
            $t->string('birth_place');
            $t->foreignId('family_id')->nullable()->constrained('mock_civil_families');
            $t->string('family_role', 20);
            $t->string('father_name')->nullable();
            $t->string('mother_name')->nullable();
            $t->string('reference_photo')->nullable();
            $t->string('face_scenario', 30)->default('REVIEW');
            $t->boolean('liveness_pass')->default(true);
            $t->boolean('complete')->default(true);
            $t->timestamps();
        });
        Schema::create('mock_identity_cards', function (Blueprint $t) {
            $t->id();
            $t->foreignId('person_id')->unique()->constrained('mock_civil_persons');
            $t->string('number')->unique();
            $t->string('status')->default('active');
            $t->timestamps();
        });
        Schema::create('mock_birth_records', function (Blueprint $t) {
            $t->id();
            $t->foreignId('person_id')->unique()->constrained('mock_civil_persons');
            $t->string('number')->unique();
            $t->timestamps();
        });
        Schema::table('users', function (Blueprint $t) {
            $t->uuid('digital_account_id')->nullable()->unique();
            $t->foreignId('mock_person_id')->nullable()->unique()->constrained('mock_civil_persons');
            $t->string('activation_source', 40)->nullable();
        });
        Schema::create('mock_face_attempts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained();
            $t->uuid('operation_token')->unique();
            $t->string('image_path');
            $t->string('result', 30);
            $t->decimal('confidence', 5, 2)->nullable();
            $t->boolean('liveness_pass');
            $t->string('source')->default('deterministic_local_mock');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Retain historical simulation evidence; use a forward migration.');
    }
};
