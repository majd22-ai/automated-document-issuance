<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mock_civil_persons', function (Blueprint $table) {
            $table->string('phone', 30)->nullable();
        });

        Schema::create('mock_civil_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_id')->constrained('mock_civil_persons')->restrictOnDelete();
            $table->string('document_type', 40);
            $table->string('document_number', 100);
            $table->string('status', 20)->default('active');
            $table->string('issued_place', 150);
            $table->date('issued_at');
            $table->timestamps();
            $table->unique(['document_type', 'document_number'], 'mock_civil_document_lookup_unique');
            $table->index(['document_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mock_civil_documents');
        Schema::table('mock_civil_persons', function (Blueprint $table) {
            $table->dropColumn('phone');
        });
    }
};
