<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('identity_card_witnesses', function (Blueprint $table) {
            $table->string('nearby_landmark')->nullable();
            $table->string('selfie_path')->nullable();
            $table->string('selfie_mime', 30)->nullable();
            $table->string('selfie_sha256', 64)->nullable();
            $table->unsignedInteger('selfie_size')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('identity_card_witnesses', function (Blueprint $table) {
            $table->dropColumn(['nearby_landmark', 'selfie_path', 'selfie_mime', 'selfie_sha256', 'selfie_size']);
        });
    }
};
