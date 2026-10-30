<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('residence_governorates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
        });
        Schema::create('residence_districts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('governorate_id')->constrained('residence_governorates')->restrictOnDelete();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
        });
        foreach (json_decode(file_get_contents(database_path('data/yemen-divisions.json')), true, flags: JSON_THROW_ON_ERROR) as $governorate) {
            $id = DB::table('residence_governorates')->insertGetId(['code' => $governorate['code'], 'name' => $governorate['name']]);
            DB::table('residence_districts')->insert(array_map(fn ($district) => ['governorate_id' => $id, 'code' => $district['code'], 'name' => $district['name']], $governorate['districts']));
        }
        Schema::create('neighborhood_officials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('district_id')->constrained('residence_districts')->restrictOnDelete();
            $table->string('full_name', 150);
            $table->text('national_id');
            $table->string('national_id_hash', 64)->unique();
            $table->text('phone');
            $table->string('neighborhood', 150);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::table('identity_card_attestations', function (Blueprint $table) {
            $table->foreignId('neighborhood_official_id')->nullable()->constrained('neighborhood_officials')->restrictOnDelete();
            $table->json('recipient_scope')->nullable();
            $table->unsignedSmallInteger('verification_attempts')->default(0);
            $table->string('verification_source', 30)->nullable();
            $table->timestamp('verified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('identity_card_attestations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('neighborhood_official_id');
            $table->dropColumn(['recipient_scope', 'verification_attempts', 'verification_source', 'verified_at']);
        });
        Schema::dropIfExists('neighborhood_officials');
        Schema::dropIfExists('residence_districts');
        Schema::dropIfExists('residence_governorates');
    }
};
