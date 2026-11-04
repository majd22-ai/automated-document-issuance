<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('neighborhood_officials', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->boolean('can_attest')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('neighborhood_officials', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn('can_attest');
        });
    }
};
