<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lost_card_cases', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('application_id')->nullable()->unique()->constrained('citizen_service_requests')->restrictOnDelete();
            $table->string('active_key', 64)->nullable()->unique();
            $table->uuid('submission_token')->unique();
            $table->string('holder_name');
            $table->string('national_id', 20);
            $table->string('card_number', 100);
            $table->date('previous_issued_on')->nullable();
            $table->date('lost_on');
            $table->string('loss_place', 150);
            $table->timestamps();
        });

        Schema::create('mock_police_loss_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lost_card_case_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->string('reference')->unique();
            $table->string('status')->default('valid');
            $table->timestamps();
        });

        Schema::create('mock_newspaper_loss_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mock_police_loss_report_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('published_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('reference')->unique();
            $table->string('status')->default('pending');
            $table->date('published_on')->nullable();
            $table->string('issue_number', 50)->nullable();
            $table->unsignedInteger('page_number')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mock_newspaper_loss_notices');
        Schema::dropIfExists('mock_police_loss_reports');
        Schema::dropIfExists('lost_card_cases');
    }
};
