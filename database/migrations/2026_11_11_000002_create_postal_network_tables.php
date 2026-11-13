<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Refuse collisions rather than adopting or altering an unverified table.
        foreach (['postal_offices', 'postal_collectors'] as $name) {
            if (Schema::hasTable($name)) {
                throw new RuntimeException('Postal table already exists; inspect its schema before migration.');
            }
        }

        Schema::create('postal_offices', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('code', 30)->unique();
            $table->string('area', 160);
            $table->string('address', 500);
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->boolean('is_demo')->default(true);
            $table->timestamps();
        });

        Schema::create('postal_collectors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('postal_office_id')->constrained('postal_offices')->restrictOnDelete();
            $table->string('name', 160);
            $table->string('username', 80)->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->boolean('can_collect')->default(false);
            $table->unsignedInteger('auth_version')->default(0);
            $table->string('fixture_key', 30)->nullable()->unique();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('postal_collectors');
        Schema::dropIfExists('postal_offices');
    }
};
