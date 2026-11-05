<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('citizen_activation_requests', function (Blueprint $table) {
            $table->string('workflow', 20)->default('in_person');
            $table->unsignedInteger('revision')->default(1);
            $table->string('document_kind', 30)->nullable();
            $table->longText('evidence')->nullable();
            $table->longText('evidence_history')->nullable();
            $table->text('review_reason')->nullable();
        });
    }

    public function down(): void
    {
        throw new RuntimeException('Activation evidence is retained; use a forward migration.');
    }
};
